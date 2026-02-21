<?php

namespace App\Http\Controllers\Transaction;

use App\Enums\OrderStatus;
use App\Http\Controllers\Controller;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use Illuminate\Http\Request;

class OrderItemController extends Controller
{
    /**
     * Validate AWB code and return order details
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateAwb(Request $request)
    {
        $request->validate([
            'awb_code' => 'required|string'
        ]);

        $awbCode = $request->input('awb_code');

        // Find order by AWB code
        $order = Order::where('awb_code', $awbCode)
            ->with(['marketplace', 'online_store'])
            ->first();

        // Check if order exists
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'AWB code tidak ditemukan dalam sistem'
            ], 404);
        }

        // Check if order status is ready_to_ship
        if ($order->status->value !== 'ready_to_ship' && $order->status->value !== 'ready_to_pickup') {
            return response()->json([
                'success' => false,
                'message' => "Order dengan status '{$order->status->value}' tidak valid. Hanya order dengan status 'ready_to_ship' yang dapat diproses.",
                'current_status' => $order->status->value
            ], 400);
        }

        // Return order details
        return response()->json([
            'success' => true,
            'message' => 'Order ditemukan dan valid',
            'data' => [
                'order' => [
                    'id' => $order->id,
                    'order_sn' => $order->order_sn,
                    'awb_code' => $order->awb_code,
                    'status' => $order->status,
                    'item_count' => $order->item_count,
                    'unique_item_count' => $order->unique_item_count,
                    'total_weight' => $order->total_weight,
                    'total_price' => $order->total_price,
                    'total_shipping' => $order->total_shipping,
                    'total_amount' => $order->total_amount,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'customer_address' => $order->customer_address,
                    'readytoship_at' => $order->readytoship_at,
                    'marketplace' => $order->marketplace ? [
                        'id' => $order->marketplace->id,
                        'name' => $order->marketplace->name,
                    ] : null,
                    'online_store' => $order->online_store ? [
                        'id' => $order->online_store->id,
                        'name' => $order->online_store->name,
                    ] : null,
                ]
            ]
        ], 200);
    }

    /**
     * Get order items expanded by quantity
     * 
     * @param string $orderId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOrderItems($orderId)
    {
        // Find order
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan'
            ], 404);
        }

        // Get all order items
        $orderItems = OrderItem::where('order_id', $orderId)
            ->with('product')
            ->get();

        // Expand items by quantity
        $expandedItems = [];

        foreach ($orderItems as $item) {
            // Create individual items based on quantity
            for ($i = 1; $i <= $item->quantity_purchased; $i++) {
                $expandedItems[] = [
                    'id' => $item->id,
                    'order_item_id' => $item->order_item_id,
                    'sku' => $item->sku,
                    'item_name' => $item->item_name,
                    'color' => $item->color,
                    'size' => $item->size,
                    'price' => $item->price,
                    'discounted_price' => $item->discounted_price,
                    'item_index' => $i,
                    'total_quantity' => $item->quantity_purchased,
                    'product' => $item->product ? [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                    ] : null,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order items berhasil diambil',
            'data' => [
                'order_id' => $orderId,
                'total_items' => count($expandedItems),
                'unique_items' => $orderItems->count(),
                'items' => $expandedItems
            ]
        ], 200);
    }

    /**
     * Validate product barcode during scanning
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function validateProductBarcode(Request $request)
    {
        $request->validate([
            'barcode' => 'required|string'
        ]);

        $barcode = $request->input('barcode');

        // Parse barcode format: {code_cmt}|{timestamp}|{sku}|{code_color}|{code_size}|{dozen_numbering}|{piece_numbering}
        $parts = explode('|', $barcode);

        if (count($parts) !== 7) {
            return response()->json([
                'success' => false,
                'message' => 'Format barcode tidak valid. Expected format: CMT|timestamp|SKU|COLOR|SIZE|dozen|piece'
            ], 400);
        }

        // Find product by barcode
        $product = \App\Models\MasterData\Product::where('barcode', $barcode)->first();

        // Check if product exists
        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode tidak ditemukan dalam sistem'
            ], 404);
        }

        // Check if product is already soft-deleted (already scanned)
        if ($product->deleted_at !== null) {
            return response()->json([
                'success' => false,
                'message' => 'Produk dengan barcode ini sudah pernah di-scan sebelumnya',
                'scanned_at' => $product->deleted_at
            ], 400);
        }

        // Return product details
        return response()->json([
            'success' => true,
            'message' => 'Barcode valid dan produk tersedia',
            'data' => [
                'product' => [
                    'id' => $product->id,
                    'barcode' => $product->barcode,
                    'model_id' => $product->model_id,
                    'rack_id' => $product->rack_id,
                ]
            ]
        ], 200);
    }
}
