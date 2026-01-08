<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Transactions\Order;
use App\Enums\OrderStatus;
use App\Services\ShopeeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShopeeController extends Controller
{
    protected ShopeeService $shopeeService;

    public function __construct(ShopeeService $shopeeService)
    {
        $this->shopeeService = $shopeeService;
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

            $statusValue = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            if (!in_array($statusValue, [OrderStatus::READY_TO_SHIP->value, OrderStatus::RETRY_SHIP->value])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order status must be ready_to_ship or retry_ship.'
                ], 422);
            }

            $data = $this->shopeeService->getShippingParameter($request->order_sn);
            return response()->json([
                'success' => true,
                'data' => $data
            ]);
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
            'address_id' => 'required', // ID can be int, but sometimes string from API, better not strict int
            'pickup_time_id' => 'required|string',
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

            $statusValue = $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status;
            if (!in_array($statusValue, [OrderStatus::READY_TO_SHIP->value, OrderStatus::RETRY_SHIP->value])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order status must be ready_to_ship or retry_ship.'
                ], 422);
            }

            $pickupData = [
                'address_id' => $request->address_id,
                'pickup_time_id' => $request->pickup_time_id
            ];

            // 1. Ship Order (Arrange Pickup)
            $shipResponse = $this->shopeeService->shipOrder($request->order_sn, $pickupData);

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

                // Try to fetch AWB
                $detailResponse = $this->shopeeService->getOrderDetail([$request->order_sn]);
                $detail = $detailResponse['response']['order_list'][0] ?? null;
                
                if ($detail) {
                    $awb = $detail['tracking_no'] ?? $detail['shipping_carrier'] ?? null;
                    if ($awb) {
                        $updateData['awb_code'] = $awb;
                    }
                    if (isset($detail['order_status'])) {
                        $updateData['readytoship_marketplace'] = $detail['order_status'];
                    }
                }

                $order->update($updateData);

            } catch (\Exception $e) {
                Log::warning("Failed to update local status/AWB after ship: " . $e->getMessage());
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
