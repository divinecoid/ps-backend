<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CheckerController extends Controller
{
    public function assignedOrders(Request $request): JsonResponse
    {
        $perPage = (int) $request->input('per_page', 15);

        $orders = Order::query()
            ->whereHas('marketplace', function ($query) {
                $query->where('is_need_checker', 1);
            })
            ->where('is_approved', 0)
            ->with(['online_store', 'marketplace', 'checkedBy'])
            ->latest()
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Assigned checker orders retrieved successfully',
            'data' => $orders,
        ]);
    }

    public function searchOrders(Request $request): JsonResponse
    {
        $search = trim($request->input('search', ''));
        $marketplaceId = $request->input('marketplace_id');
        $perPage = (int) $request->input('per_page', 15);

        $query = Order::query()
            ->whereHas('marketplace', function ($query) {
                $query->where('is_need_checker', 1);
            })
            ->where('is_approved', 0)
            ->with(['online_store', 'marketplace', 'checkedBy']);

        // Apply search filter if provided
        if (!empty($search)) {
            $query->where(function ($q) use ($search) {
                $q->whereRaw('LOWER(order_sn) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(awb_code) LIKE ?', ['%' . strtolower($search) . '%'])
                    ->orWhereRaw('LOWER(customer_name) LIKE ?', ['%' . strtolower($search) . '%']);
            });
        }

        // Apply marketplace filter if provided
        if (!empty($marketplaceId)) {
            $query->where('marketplace_id', $marketplaceId);
        }

        $orders = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => 'Search results retrieved successfully',
            'data' => $orders,
        ]);
    }

    public function getOrderBySerial(string $serial): JsonResponse
    {
        $order = Order::query()
            ->whereRaw('LOWER(order_sn) = ?', [strtolower(trim($serial))])
            ->whereHas('marketplace', function ($query) {
                $query->where('is_need_checker', 1);
            })
            ->where('is_approved', 0)
            ->with(['online_store', 'marketplace', 'checkedBy'])
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or no longer requires checker',
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order retrieved successfully',
            'data' => $order->toArray(),
        ]);
    }

    public function approveOrder(string $id): JsonResponse
    {
        $order = Order::find($id);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found',
            ], 404);
        }

        $order->loadMissing('marketplace');

        if (!$order->marketplace || (int) $order->marketplace->is_need_checker !== 1) {
            return response()->json([
                'success' => false,
                'message' => 'Order does not require checker',
            ], 400);
        }

        $validator = \Validator::make(request()->all(), [
            'scanned_barcodes' => 'required|array|min:1',
            'scanned_barcodes.*' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => $validator->errors()->first(),
            ], 422);
        }

        $orderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->get();

        if ($orderItems->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'Order items not found',
            ], 404);
        }

        $requiredQtyByKey = [];
        foreach ($orderItems as $item) {
            $key = strtolower(trim($item->sku)) . '|' . strtolower(trim($item->color ?? '')) . '|' . strtolower(trim($item->size ?? ''));
            $requiredQtyByKey[$key] = ($requiredQtyByKey[$key] ?? 0) + (int) $item->quantity_purchased;
        }

        $scannedQtyByKey = [];
        $sequenceBySku = [];

        foreach (request()->input('scanned_barcodes', []) as $barcode) {
            $parsed = $this->parseBarcode($barcode);
            if (!$parsed) {
                return response()->json([
                    'success' => false,
                    'message' => "Invalid barcode format: {$barcode}",
                ], 400);
            }

            $skuKey = strtolower($parsed['sku']);
            $sequence = $parsed['sequence'];

            if (isset($sequenceBySku[$skuKey][$sequence])) {
                return response()->json([
                    'success' => false,
                    'message' => "Duplicate sequence {$sequence} for SKU {$parsed['sku']}",
                ], 400);
            }

            $sequenceBySku[$skuKey][$sequence] = true;

            $key = $skuKey . '|' . strtolower($parsed['color']) . '|' . strtolower($parsed['size']);

            if (!isset($requiredQtyByKey[$key])) {
                return response()->json([
                    'success' => false,
                    'message' => "Scanned barcode not in this order: {$barcode}",
                ], 400);
            }

            $scannedQtyByKey[$key] = ($scannedQtyByKey[$key] ?? 0) + 1;

            if ($scannedQtyByKey[$key] > $requiredQtyByKey[$key]) {
                return response()->json([
                    'success' => false,
                    'message' => "Scanned qty exceeds required qty for SKU {$parsed['sku']}",
                ], 400);
            }
        }

        foreach ($requiredQtyByKey as $key => $requiredQty) {
            $scannedQty = $scannedQtyByKey[$key] ?? 0;
            if ($scannedQty !== $requiredQty) {
                return response()->json([
                    'success' => false,
                    'message' => 'All items must be scanned before approval',
                    'data' => [
                        'required_total' => array_sum($requiredQtyByKey),
                        'scanned_total' => array_sum($scannedQtyByKey),
                    ],
                ], 400);
            }
        }

        OrderItem::where('order_id', $order->id)->update(['is_checked' => 1]);

        $order->is_approved = 1;
        $order->is_need_checker = 0;
        $order->checked_by = auth()->id();
        $order->save();

        $order->load(['online_store', 'marketplace', 'checkedBy']);

        return response()->json([
            'success' => true,
            'message' => 'Order approved successfully',
            'data' => $order->toArray(),
        ]);
    }

    public function getOrderItems(string $orderId): JsonResponse
    {
        $order = Order::query()
            ->where('id', $orderId)
            ->whereHas('marketplace', function ($query) {
                $query->where('is_need_checker', 1);
            })
            ->where('is_approved', 0)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode not found or invalid',
            ]);
        }

        $orderItems = OrderItem::query()
            ->where('order_id', $orderId)
            ->get()
            ->unique('id');

        $expandedItems = [];

        foreach ($orderItems as $item) {
            for ($index = 1; $index <= (int) $item->quantity_purchased; $index++) {
                $expandedItems[] = [
                    'id' => $item->id,
                    'sku' => $item->sku,
                    'item_name' => $item->item_name,
                    'color' => $item->color,
                    'size' => $item->size,
                    'item_index' => $index,
                    'total_quantity' => (int) $item->quantity_purchased,
                    'is_checked' => (int) $item->is_checked,
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Order items retrieved successfully',
            'data' => [
                'order_id' => $orderId,
                'total_items' => count($expandedItems),
                'items' => $expandedItems,
            ],
        ]);
    }

    public function validateProductBarcode(Request $request): JsonResponse
    {
        $request->validate([
            'order_id' => 'required|exists:trx_orders,id',
            'barcode' => 'required|string',
        ]);

        $order = Order::query()
            ->where('id', $request->input('order_id'))
            ->whereHas('marketplace', function ($query) {
                $query->where('is_need_checker', 1);
            })
            ->where('is_approved', 0)
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found or no longer requires checker',
            ], 404);
        }

        $parsed = $this->parseBarcode($request->input('barcode'));

        if (!$parsed) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode not found or invalid',
            ]);
        }

        $matchedOrderItem = OrderItem::query()
            ->where('order_id', $order->id)
            ->whereRaw('LOWER(sku) = ?', [strtolower($parsed['sku'])])
            ->whereRaw("LOWER(COALESCE(color, '')) = ?", [strtolower($parsed['color'])])
            ->whereRaw("LOWER(COALESCE(size, '')) = ?", [strtolower($parsed['size'])])
            ->first();

        if (!$matchedOrderItem) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode not found or invalid',
                'data' => [
                    'sku' => $parsed['sku'],
                    'color' => $parsed['color'],
                    'size' => $parsed['size'],
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Barcode valid for this checker order',
            'data' => [
                'sku' => $parsed['sku'],
                'color' => $parsed['color'],
                'size' => $parsed['size'],
                'sequence' => $parsed['sequence'],
                'order_item_id' => $matchedOrderItem->id,
                'item_name' => $matchedOrderItem->item_name,
            ],
        ]);
    }

    private function parseBarcode(string $barcode): ?array
    {
        $parts = explode('|', $barcode);

        if (count($parts) !== 7) {
            return null;
        }

        return [
            'sku' => trim($parts[2]),
            'color' => trim($parts[3]),
            'size' => trim($parts[4]),
            'sequence' => trim($parts[5]) . '-' . trim($parts[6]),
        ];
    }
}