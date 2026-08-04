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
     * Ensure the store's access token is valid before making API calls.
     * Checks access_token_expires_at against now. If expired, attempts to refresh.
     * Throws an exception if there is no refresh token or if the refresh fails,
     * so the caller can return an error response without proceeding with the fetch.
     *
     * @param OnlineStore $store
     * @throws \Exception
     */
    private function ensureValidToken(OnlineStore $store): void
    {
        if (!$store->isTokenExpired()) {
            return;
        }

        if (!$store->refresh_token) {
            throw new \Exception("Access token expired and no refresh token available for store: {$store->store_name}");
        }

        Log::info("Shopee access token expired for store [{$store->store_name}], attempting refresh.");

        try {
            $this->shopeeService->setStore($store);
            $this->shopeeService->refreshAccessToken();
            $store->refresh(); // Reload updated token from DB
        } catch (\Exception $e) {
            Log::error("Shopee token refresh failed for store [{$store->store_name}]", ['error' => $e->getMessage()]);
            throw new \Exception("Access token expired and refresh failed for store [{$store->store_name}]: " . $e->getMessage());
        }
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
            $this->ensureValidToken($store);
            $this->shopeeService->setStore($store);

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

            foreach ($logisticsList as $item) {
                ShippingLogistic::updateOrCreate(
                    [
                        'marketplace_id' => $store->marketplace_id,
                        'logistic_id' => (string)$item['logistics_channel_id']
                    ],
                    [
                        'logistic_name' => $item['logistics_channel_name'],
                        'logistic_type' => $item['preferred_delivery_time'] ?? null, // Or any other field you prefer
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
                'message' => 'Refresh token berhasil',
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            Log::error('Shopee refresh token failed', [
                'error' => $e->getMessage(),
                'store_id' => $store->id,
            ]);

            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function fetchOrders(Request $request)
    {
        $days = (int)($request->query('days', 1));
        if ($days < 1) {
            $days = 1;
        }

        // Token validation and refresh (or skip on failure) is handled
        // per-store inside the Artisan command before each store is processed.
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
                $this->ensureValidToken($order->online_store);
                $this->shopeeService->setStore($order->online_store);
            }

            $statusValue = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            if (!in_array($statusValue, [OrderStatus::READY_TO_SHIP->value, OrderStatus::RETRY_SHIP->value])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order status must be ready_to_ship or retry_ship.'
                ], 422);
            }

            $data = $this->shopeeService->getShippingParameter($request->order_sn);
            return $data;
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Ship Order (Request Pickup) & Create Shipping Document
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function shipOrder(Request $request)
    {
        $request->validate([
            'order_sn' => 'required|string',
            'address_id' => 'required_without:dropoff',
            'pickup_time_id' => 'required_without:dropoff|string',
            'dropoff' => 'nullable|array',
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
                $this->ensureValidToken($order->online_store);
                $this->shopeeService->setStore($order->online_store);
            }

            $statusValue = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            if (!in_array($statusValue, [OrderStatus::READY_TO_SHIP->value, OrderStatus::RETRY_SHIP->value])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order status must be ready_to_ship or retry_ship.'
                ], 422);
            }

            if ($request->has('dropoff')) {
                $shipResponse = $this->shopeeService->shipOrderDropoff($request->order_sn, $request->dropoff);
            } else {
                $pickupData = [
                    'address_id' => $request->address_id,
                    'pickup_time_id' => $request->pickup_time_id
                ];
                $shipResponse = $this->shopeeService->shipOrder($request->order_sn, $pickupData);
            }

            if (isset($shipResponse['error']) && !empty($shipResponse['error'])) {
                 // Check if it's "Order has been shipped" error, treat as success (maybe state mismatch)
                 if (isset($shipResponse['message']) && stripos($shipResponse['message'], 'shipped') !== false) {
                      // It is shipped, proceed to update local status
                 } else {
                      return response()->json([
                          'success' => false,
                          'message' => 'Shopee API Error: ' . ($shipResponse['message'] ?? 'Unknown error'),
                          'data' => $shipResponse
                      ], 400);
                 }
            }

            // Update Local Status & Fetch AWB
            try {
                $updateData = [
                    'status' => OrderStatus::READY_TO_PICKUP,
                    'readytoship_at' => now(),
                ];
                
                Log::info("Attempting to update order status to READY_TO_PICKUP for order: " . $request->order_sn);

                // Try to fetch AWB + logistics channel mapping
                $detailResponse = $this->shopeeService->getOrderDetail([$request->order_sn]);
                $detail = $detailResponse['response']['order_list'][0] ?? null;
                
                if ($detail) {
                    $awb = $detail['order_sn'] ?? null;
                    if ($awb) {
                        $updateData['awb_code'] = $awb;
                    }
                    if (isset($detail['order_status'])) {
                        $updateData['readytoship_marketplace'] = $detail['order_status'];
                    }

                    // Map logistics_channel_id from package_list to local ShippingLogistic
                    $logisticsChannelId = $detail['package_list'][0]['logistics_channel_id'] ?? null;
                    if ($logisticsChannelId) {
                        $logistic = \App\Models\MasterData\ShippingLogistic::where('marketplace_id', $order->marketplace_id)
                            ->where('logistic_id', (string) $logisticsChannelId)
                            ->first();
                        if ($logistic) {
                            $updateData['shipping_logistic_id'] = $logistic->id;
                            Log::info("Mapped logistics_channel_id {$logisticsChannelId} to ShippingLogistic: {$logistic->logistic_name}");
                        } else {
                            Log::warning("No local ShippingLogistic found for logistics_channel_id: {$logisticsChannelId} (marketplace: {$order->marketplace_id})");
                        }
                    }
                }

                $order->update($updateData);

                // Verify update
                $order->refresh();
                if ($order->status !== OrderStatus::READY_TO_PICKUP) {
                     $currentStatus = $order->status instanceof \BackedEnum ? $order->status->value : $order->status;
                     Log::warning("Order status update verification failed. Status is still: " . $currentStatus);
                     // Force direct update
                     DB::table('trx_orders')
                        ->where('id', $order->id)
                        ->update(['status' => 'ready_to_pickup', 'readytoship_at' => now()]);
                }

            } catch (\Exception $e) {
                Log::warning("Failed to update local status/AWB after ship: " . $e->getMessage());
                $shipResponse['local_update_warning'] = $e->getMessage();
            }

            // 2. Automatically Create Shipping Document
            // Note: This might take a moment to be available for download
            // We use try-catch here so if document creation fails, we still return success for ship order but with warning
            try {
                $docResponse = $this->shopeeService->createShippingDocument($request->order_sn);
            } catch (\Exception $e) {
                $docResponse = ['error' => 'Failed to initiate shipping document creation: ' . $e->getMessage()];
                // Log warning but don't fail the whole request
                Log::warning('Auto create shipping document failed after ship order', ['order_sn' => $request->order_sn, 'error' => $e->getMessage()]);
            }

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
                $this->ensureValidToken($order->online_store);
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
            'shipping_document_type' => 'nullable|string' // Default NORMAL_AIR_WAYBILL
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
                $this->ensureValidToken($order->online_store);
                $this->shopeeService->setStore($order->online_store);
            }

            $statusValue = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            $allowed = [
                OrderStatus::READY_TO_SHIP->value,
                OrderStatus::RETRY_SHIP->value,
                OrderStatus::READY_TO_PICKUP->value,
                OrderStatus::SHIPPED->value,
            ];
            if (!in_array($statusValue, $allowed, true)) {
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

            $order->update(['is_label_printed' => true]);

            // Return as downloadable PDF
            return response($fileContent)
                ->header('Access-Control-Expose-Headers', 'Content-Disposition')
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
