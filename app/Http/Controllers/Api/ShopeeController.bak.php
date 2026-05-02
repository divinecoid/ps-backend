<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transactions\Order;
use App\Enums\OrderStatus;
use App\Services\ShopeeService;
use App\Models\MasterData\OnlineStore;
use App\Models\MasterData\ShippingLogistic;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class ShopeeController extends Controller
{
    protected ShopeeService $shopeeService;

    public function __construct(ShopeeService $shopeeService)
    {
        $this->shopeeService = $shopeeService;
    }

    /**
     * Sync Shipping Logistics from Shopee API
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function syncShippingLogistics(Request $request)
    {
        $request->validate([
            'online_store_id' => 'required|exists:mdx_online_stores,id',
        ]);

        try {
            $store = OnlineStore::findOrFail($request->online_store_id);
            $this->shopeeService->setStore($store);

            // 1. Hit Shopee API get_channel_list
            $response = $this->shopeeService->getChannelList();
            
            if (isset($response['error']) && !empty($response['error'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Shopee API Error: ' . ($response['message'] ?? $response['error']),
                    'shopee_response' => $response
                ], 400);
            }

            $logisticsList = $response['response']['logistics_channel_list'] ?? [];
            $count = 0;

            // 2. Process and Sync to Database
            foreach ($logisticsList as $item) {
                ShippingLogistic::updateOrCreate(
                    [
                        'marketplace_id' => $store->marketplace_id,
                        'logistic_id' => (string)$item['logistics_channel_id']
                    ],
                    [
                        'logistic_name' => $item['logistics_channel_name'],
                        'logistic_type' => $item['preferred_delivery_time'] ?? null,
                        'is_active' => $item['enabled'] ?? true
                    ]
                );
                $count++;
            }

            return response()->json([
                'success' => true,
                'message' => "Successfully synced {$count} shipping logistics for store: {$store->store_name}",
                'data' => $logisticsList
            ]);

        } catch (\Exception $e) {
            Log::error('Shopee syncShippingLogistics failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function redirectToShopee($id)
    {
        $store = OnlineStore::findOrFail($id);
        $this->shopeeService->setStore($store);
        $url = $this->shopeeService->generateAuthUrl();
        return redirect($url);
    }

    public function handleCallback(Request $request)
    {
        Log::info('Shopee Callback Received', $request->all());

        $state = $request->query('state'); // Partner ID
        $code = $request->query('code');
        $shopId = $request->query('shop_id');

        if (!$state || !$code || !$shopId) {
             Log::error('Shopee Callback Missing Parameters', ['state' => $state, 'code' => $code, 'shopId' => $shopId]);
             return response()->json(['error' => 'Missing required parameters (state/code/shop_id). Please regenerate auth URL.'], 400);
        }

        // Find store by Partner ID (state)
        // User requested to use state (PartnerID) to identify.
        // We try to find a store that matches this PartnerID.
        // Ideally we should also check shop_id if it exists, but for initial auth it might be null.
        $store = OnlineStore::where('client_id', $state)
                    ->where(function($q) use ($shopId) {
                        $q->where('shop_id', $shopId)
                          ->orWhereNull('shop_id')
                          ->orWhere('shop_id', '');
                    })
                    ->first();

        if (!$store) {
            // Fallback: Find any store with this Partner ID (Caution: ambiguous if multiple stores)
            $store = OnlineStore::where('client_id', $state)->first();
        }

        if (!$store) {
            return response()->json(['error' => 'Store not found for Partner ID: ' . $state], 404);
        }

        // Set store context
        $this->shopeeService->setStore($store);

        // Update Auth Code and Shop ID as requested
        $store->update([
            'auth_code' => $code,
            'shop_id' => $shopId
        ]);

        // Exchange for Token
        try {
            $this->shopeeService->exchangeAuthCodeForToken($code, (int)$shopId);
            return response('Shopee Auth Success! Token has been generated. You can close this window.');
        } catch (\Exception $e) {
            Log::error('Shopee Auth Failed', ['error' => $e->getMessage()]);
            return response('Auth Failed: ' . $e->getMessage(), 500);
        }
    }

    public function generateAuthUrl()
    {
        try {
            $url = $this->shopeeService->generateAuthUrl();
            return response()->json([
                'success' => true,
                'data' => [
                    'auth_url' => $url
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function refreshToken($id)
    {
        $store = OnlineStore::findOrFail($id);

        if (!$store->refresh_token) {
            return response()->json([
                'success' => false,
                'message' => 'No refresh token available',
            ], 400);
        }

        try {
            $this->shopeeService->setStore($store);
            $result = $this->shopeeService->refreshAccessToken();

            return response()->json([
                'success' => true,
                'message' => 'Token refreshed successfully',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function fetchOrders(Request $request)
    {
        $days = (int)($request->query('days', 1));
        if ($days < 1) {
            $days = 1;
        }

        Artisan::call('shopee:fetch-orders', [
            '--days' => $days,
        ]);

        $output = Artisan::output();

        return response()->json([
            'success' => true,
            'message' => 'Shopee fetch orders executed',
            'days' => $days,
            'raw_output' => $output,
        ]);
    }

    public function getShippingParameter(Request $request)
    {
        $request->validate([
            'order_sn' => 'required|string',
        ]);

        try {
            $order = Order::where('order_sn', $request->order_sn)->first();
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            if ($order->online_store) {
                $this->shopeeService->setStore($order->online_store);
            }

            $result = $this->shopeeService->getShippingParameter($request->order_sn);

            return response()->json($result);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    public function shipOrder(Request $request)
    {
        $request->validate([
            'order_sn' => 'required|string',
            // Optional for pickup, depends on shopee service implementation
            'address_id' => 'nullable',
            'pickup_time_id' => 'nullable',
        ]);

        try {
            $order = Order::where('order_sn', $request->order_sn)->first();
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            if ($order->online_store) {
                $this->shopeeService->setStore($order->online_store);
            }

            // Atur pengiriman (Arrange Shipment)
            $shipResponse = $this->shopeeService->shipOrder($request->order_sn, [
                'pickup' => [
                    'address_id' => (int)$request->address_id,
                    'pickup_time_id' => $request->pickup_time_id
                ]
            ]);

            // Trigger Shopee to generate shipping document (Print)
            $docResponse = $this->shopeeService->createShippingDocument($request->order_sn);

            return response()->json([
                'success' => true,
                'message' => 'Order shipped successfully.',
                'data' => [
                    'ship_order' => $shipResponse,
                    'create_document' => $docResponse
                ]
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create Shipping Document (Trigger Print)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function createShippingDocument(Request $request)
    {
        $request->validate([
            'order_sn' => 'required|string',
        ]);

        try {
            $order = Order::where('order_sn', $request->order_sn)->first();
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            if ($order->online_store) {
                $this->shopeeService->setStore($order->online_store);
            }

            $result = $this->shopeeService->createShippingDocument($request->order_sn);

            return response()->json([
                'success' => true,
                'message' => 'Shipping document creation initiated.',
                'data' => $result
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Download Shipping Document
     * 
     * @param Request $request
     * @return \Illuminate\Http\Response|\Illuminate\Http\JsonResponse
     */
    public function downloadShippingDocument(Request $request)
    {
        $request->validate([
            'order_sn' => 'required|string',
            'shipping_document_type' => 'nullable|string',
        ]);

        try {
            $order = Order::where('order_sn', $request->order_sn)->first();
            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found.'
                ], 404);
            }

            if ($order->online_store) {
                $this->shopeeService->setStore($order->online_store);
            }

            // Validasi status order sebelum download
            if ($order->status->value !== OrderStatus::READY_TO_SHIP->value && $order->status->value !== OrderStatus::READY_TO_PICKUP->value) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order status must be ready_to_ship or ready_to_pickup.'
                ], 422);
            }

            $type = $request->shipping_document_type ?? 'NORMAL_AIR_WAYBILL';
            $fileContent = $this->shopeeService->downloadShippingDocument($request->order_sn, $type);

            // Robust check: PDF files MUST start with %PDF-
            if (strpos($fileContent, '%PDF-') !== 0) {
                Log::warning('Shopee download returned invalid PDF content', ['order_sn' => $request->order_sn, 'preview' => substr($fileContent, 0, 100)]);
                
                // If it looks like JSON, return as JSON
                if (strpos(trim($fileContent), '{') === 0) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Shopee returned an error message instead of a PDF file.',
                        'shopee_response' => json_decode($fileContent)
                    ], 400);
                }

                return response()->json([
                    'success' => false,
                    'message' => 'Received invalid PDF content from Shopee.',
                    'preview' => substr($fileContent, 0, 50)
                ], 500);
            }

            // Return as downloadable PDF
            return response($fileContent)
                ->header('Content-Type', 'application/pdf')
                ->header('Content-Disposition', 'attachment; filename="shipping_document_' . $request->order_sn . '.pdf"');

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
