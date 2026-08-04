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

        // Expand order items similar to mobile app logic
        // Parse bundles like "PAKET3-LOGO48*POLOSPANJANG+POLOSPENDEK" with "BLUE=RED=WHITE"
        // into individual items: POLOSPANJANG BLUE, POLOSPANJANG RED, POLOSPANJANG WHITE, POLOSPENDEK Hitam
        $expandedRequired = [];
        
        foreach ($orderItems as $item) {
            $sku = trim($item->sku);
            $colorString = trim($item->color ?? '');
            $size = trim($item->size ?? '');
            
            // Check if this is a bundle (contains PAKET, *, or +)
            if (preg_match('/^(?:PAKET(\d+)-)?(?:(.+?)\*)?([^+]+)(?:\+(.+))?$/i', $sku, $matches)) {
                $quantity = isset($matches[1]) && $matches[1] ? (int)$matches[1] : 1;
                $logo = isset($matches[2]) ? $matches[2] : null;
                $mainSku = isset($matches[3]) ? trim($matches[3]) : '';
                $extraSku = isset($matches[4]) ? trim($matches[4]) : null;
                
                // Split colors by = or |
                $colors = $colorString ? preg_split('/[=|]/', $colorString) : [''];
                
                // Add main SKU items with their colors
                for ($i = 0; $i < $quantity; $i++) {
                    $itemColor = isset($colors[$i]) ? trim($colors[$i]) : 'Hitam';
                    $key = strtolower($mainSku) . '|' . strtolower($itemColor) . '|' . strtolower($size);
                    $expandedRequired[$key] = ($expandedRequired[$key] ?? 0) + 1;
                }
                
                // Add extra SKU (default color: Hitam/Black)
                if ($extraSku) {
                    $key = strtolower($extraSku) . '|hitam|' . strtolower($size);
                    $expandedRequired[$key] = ($expandedRequired[$key] ?? 0) + 1;
                }
            } else {
                // Simple SKU, not a bundle
                $key = strtolower($sku) . '|' . strtolower($colorString) . '|' . strtolower($size);
                $expandedRequired[$key] = ($expandedRequired[$key] ?? 0) + (int)$item->quantity_purchased;
            }
        }

        \Log::info('Expanded required items for approval', [
            'order_id' => $order->id,
            'expanded' => $expandedRequired
        ]);

        $scannedQtyByKey = [];
        $sequenceBySku = [];

        \Log::info('Processing scanned barcodes', [
            'count' => count(request()->input('scanned_barcodes', [])),
            'barcodes' => request()->input('scanned_barcodes', [])
        ]);

        foreach (request()->input('scanned_barcodes', []) as $barcode) {
            $parsed = $this->parseBarcode($barcode);
            if (!$parsed) {
                \Log::error('Failed to parse barcode in approval', ['barcode' => $barcode]);
                return response()->json([
                    'success' => false,
                    'message' => "Invalid barcode format: {$barcode}",
                ], 400);
            }

            $barcodeModel = strtolower($parsed['sku']);
            $barcodeColor = strtolower($parsed['color']);
            $sequence = $parsed['sequence'];

            // Check for duplicate sequences per SKU+Color combination
            $sequenceKey = $barcodeModel . '|' . $barcodeColor;
            if (isset($sequenceBySku[$sequenceKey][$sequence])) {
                \Log::warning('Duplicate sequence detected in approval', [
                    'sku' => $barcodeModel,
                    'color' => $barcodeColor,
                    'sequence' => $sequence
                ]);
                return response()->json([
                    'success' => false,
                    'message' => "Duplicate sequence {$sequence} for SKU {$parsed['sku']} {$parsed['color']}",
                ], 400);
            }

            $sequenceBySku[$sequenceKey][$sequence] = true;

            // Try to match with color variants
            $matched = false;
            foreach ($parsed['color_variants'] as $colorVariant) {
                $color = strtolower($colorVariant);
                $size = strtolower($parsed['size']);
                $key = $barcodeModel . '|' . $color . '|' . $size;

                \Log::info('Trying to match barcode', [
                    'barcode_sku' => $barcodeModel,
                    'color_variant' => $colorVariant,
                    'normalized_color' => $color,
                    'size' => $size,
                    'key' => $key,
                    'exists_in_required' => isset($expandedRequired[$key])
                ]);

                if (isset($expandedRequired[$key])) {
                    $scannedQtyByKey[$key] = ($scannedQtyByKey[$key] ?? 0) + 1;

                    if ($scannedQtyByKey[$key] > $expandedRequired[$key]) {
                        \Log::warning('Scanned qty exceeds required', [
                            'key' => $key,
                            'scanned' => $scannedQtyByKey[$key],
                            'required' => $expandedRequired[$key]
                        ]);
                        return response()->json([
                            'success' => false,
                            'message' => "Scanned qty exceeds required qty for SKU {$parsed['sku']}",
                        ], 400);
                    }

                    $matched = true;
                    \Log::info('Barcode matched successfully', ['key' => $key]);
                    break;
                }
            }

            if (!$matched) {
                \Log::warning('Barcode not matched in approval', [
                    'barcode' => $barcode,
                    'parsed' => $parsed,
                    'expanded_required' => $expandedRequired,
                    'tried_keys' => array_map(function($variant) use ($barcodeModel, $size) {
                        return $barcodeModel . '|' . strtolower($variant) . '|' . strtolower($size);
                    }, $parsed['color_variants'])
                ]);
                
                return response()->json([
                    'success' => false,
                    'message' => "Scanned barcode not in this order: {$barcode}",
                    'debug' => [
                        'parsed' => $parsed,
                        'required_keys' => array_keys($expandedRequired)
                    ]
                ], 400);
            }
        }

        \Log::info('Comparing scanned vs required', [
            'scanned' => $scannedQtyByKey,
            'required' => $expandedRequired
        ]);

        // Check if all required items were scanned
        foreach ($expandedRequired as $key => $requiredQty) {
            $scannedQty = $scannedQtyByKey[$key] ?? 0;
            if ($scannedQty !== $requiredQty) {
                return response()->json([
                    'success' => false,
                    'message' => 'All items must be scanned before approval',
                    'data' => [
                        'required_total' => array_sum($expandedRequired),
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
            \Log::error('Failed to parse barcode', [
                'barcode' => $request->input('barcode'),
                'order_id' => $request->input('order_id')
            ]);
            return response()->json([
                'success' => false,
                'message' => 'Barcode not found or invalid',
                'debug' => [
                    'barcode' => $request->input('barcode'),
                    'reason' => 'Failed to parse barcode format'
                ]
            ]);
        }

        // Get all order items for debugging
        $allOrderItems = OrderItem::query()
            ->where('order_id', $order->id)
            ->get(['sku', 'color', 'size', 'item_name'])
            ->map(function($item) {
                return [
                    'sku' => $item->sku,
                    'color' => $item->color,
                    'size' => $item->size,
                    'item_name' => $item->item_name,
                ];
            });

        // Try to find a matching order item
        // The barcode contains the model name (e.g., "POLOSPANJANG"), but the SKU in the database
        // might contain additional info like logo (e.g., "LOGO048*POLOSPANJANG+POLOSPENDEK").
        // The color field in the database might contain multiple colors separated by = (e.g., "BLUE=RED=WHITE")
        // while the barcode has a single color (e.g., "BLUE").
        $matchedOrderItem = OrderItem::query()
            ->where('order_id', $order->id)
            ->where(function ($query) use ($parsed) {
                // Try exact match first
                $query->whereRaw('LOWER(sku) = ?', [strtolower($parsed['sku'])])
                    // Or try to match if the SKU contains the model name
                    ->orWhereRaw('LOWER(sku) LIKE ?', ['%' . strtolower($parsed['sku']) . '%']);
            })
            ->whereRaw("LOWER(COALESCE(size, '')) = ?", [strtolower($parsed['size'])])
            ->first();

        // If no match found with size only, try with color validation
        if (!$matchedOrderItem) {
            $matchedOrderItem = OrderItem::query()
                ->where('order_id', $order->id)
                ->where(function ($query) use ($parsed) {
                    $query->whereRaw('LOWER(sku) = ?', [strtolower($parsed['sku'])])
                        ->orWhereRaw('LOWER(sku) LIKE ?', ['%' . strtolower($parsed['sku']) . '%']);
                })
                ->where(function ($query) use ($parsed) {
                    // Handle multi-color fields (e.g., "BLUE=RED=WHITE")
                    foreach ($parsed['color_variants'] as $colorVariant) {
                        $colorPattern = strtolower($colorVariant);
                        $query->orWhereRaw("LOWER(COALESCE(color, '')) = ?", [$colorPattern])
                            ->orWhereRaw("LOWER(COALESCE(color, '')) LIKE ?", [$colorPattern . '=%'])
                            ->orWhereRaw("LOWER(COALESCE(color, '')) LIKE ?", ['%=' . $colorPattern . '=%'])
                            ->orWhereRaw("LOWER(COALESCE(color, '')) LIKE ?", ['%=' . $colorPattern]);
                    }
                })
                ->whereRaw("LOWER(COALESCE(size, '')) = ?", [strtolower($parsed['size'])])
                ->first();
        }

        if (!$matchedOrderItem) {
            \Log::warning('Barcode did not match any order item', [
                'parsed' => $parsed,
                'order_items' => $allOrderItems
            ]);
            
            return response()->json([
                'success' => false,
                'message' => 'Barcode not found or invalid',
                'debug' => [
                    'parsed' => $parsed,
                    'order_items_in_order' => $allOrderItems,
                    'reason' => 'No matching order item found'
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Barcode valid for this checker order',
            'data' => [
                'sku' => $parsed['sku'], // Return the barcode SKU, not the database SKU
                'color' => $parsed['color'], // Return the original barcode color
                'size' => $parsed['size'],
                'sequence' => $parsed['sequence'],
                'order_item_id' => $matchedOrderItem->id,
                'item_name' => $matchedOrderItem->item_name,
                'warna_string' => $parsed['color'], // Use barcode color
            ],
        ]);
    }

    private function parseBarcode(string $barcode): ?array
    {
        $parts = explode('|', $barcode);

        // Log for debugging
        \Log::info('Parsing barcode', [
            'barcode' => $barcode,
            'parts_count' => count($parts),
            'parts' => $parts
        ]);

        // Accept both 7-part format (with type P/D) and potentially other variations
        if (count($parts) < 5) {
            \Log::warning('Barcode has too few parts', ['count' => count($parts)]);
            return null;
        }

        // Standard format: CMT|timestamp|SKU|COLOR|SIZE|TYPE|SEQ
        // parts[0] = CMT code
        // parts[1] = timestamp
        // parts[2] = SKU/Model
        // parts[3] = Color
        // parts[4] = Size
        // parts[5] = Type (P/D) - optional
        // parts[6] = Sequence number - optional

        $sku = isset($parts[2]) ? trim($parts[2]) : '';
        $color = isset($parts[3]) ? trim($parts[3]) : '';
        $size = isset($parts[4]) ? trim($parts[4]) : '';
        
        // Build sequence from parts 5 and 6 if they exist
        $sequence = '';
        if (isset($parts[5]) && isset($parts[6])) {
            $sequence = trim($parts[5]) . '-' . trim($parts[6]);
        } elseif (isset($parts[5])) {
            $sequence = trim($parts[5]);
        }

        if (empty($sku)) {
            \Log::warning('Barcode missing SKU field');
            return null;
        }

        // Normalize color (handle English to Indonesian translation if needed)
        $colorMap = [
            'black' => ['hitam', 'black'],
            'white' => ['putih', 'white'],
            'red' => ['merah', 'red'],
            'blue' => ['biru', 'blue'],
            'green' => ['hijau', 'green'],
            'yellow' => ['kuning', 'yellow'],
        ];

        return [
            'sku' => $sku,
            'color' => $color,
            'color_variants' => $this->getColorVariants($color, $colorMap),
            'size' => $size,
            'sequence' => $sequence,
        ];
    }

    private function getColorVariants(string $color, array $colorMap): array
    {
        $lowerColor = strtolower($color);
        $variants = [$color]; // Always include original

        foreach ($colorMap as $key => $values) {
            if (in_array($lowerColor, array_map('strtolower', $values))) {
                // Add all variants for this color
                $variants = array_merge($variants, $values);
                break;
            }
        }

        return array_unique($variants);
    }
}