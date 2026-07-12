<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Http\Traits\MarketplaceApiTrait;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    use CrudTrait, MarketplaceApiTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'order_sn' => $data->order_sn,
            'awb_code' => $data->awb_code,
            'read_at' => $data->read_at?->utc()->toISOString(),
            'prepared_at' => $data->prepared_at?->utc()->toISOString(),
            'prepare_duration' => $data->prepare_duration,
            'readytoship_at' => $data->readytoship_at?->utc()->toISOString(),
            'readytoship_marketplace' => $data->readytoship_marketplace,
            'online_store_id' => $data->online_store_id,
            'online_store' => $data->online_store,
            'item_count' => $data->item_count,
            'unique_item_count' => $data->unique_item_count,
            'status' => $data->status,
            'total_weight' => $data->total_weight,
            'total_price' => $data->total_price,
            'total_shipping' => $data->total_shipping,
            'total_amount' => $data->total_amount,
            'preparist_user_id' => $data->preparist_user_id,
            'preparist_user' => $data->preparist_user,
            'customer_name' => $data->customer_name,
            'customer_phone' => $data->customer_phone,
            'customer_address' => $data->customer_address,
            'marketplace_id' => $data->marketplace_id,
            'marketplace' => $data->marketplace
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Order::class,
            [],
            ["marketplace_id", "order_sn", "awb_code", "status", "online_store_id"],
            $this->structure(),
            null,
            ['created_at' => 'desc']
        );
    }


    public function show($id)
    {
        return $this->baseShow(
            Order::class,
            $id,
            ['order_items'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Order::class,
            [
                'awb_code' => 'nullable|string|unique:trx_orders,awb_code|max:255',
                'read_at' => 'nullable|date',
                'prepared_at' => 'nullable|date',
                'prepare_duration' => 'nullable|integer|min:0',
                'readytoship_at' => 'nullable|date',
                'readytoship_marketplace' => 'nullable|string|max:255',
                'online_store_id' => 'required|exists:mdx_online_stores,id',
                'item_count' => 'required|integer|min:1',
                'unique_item_count' => 'required|integer|min:1',
                'status' => 'required|in:pending,read,prepared,ready_to_ship,shipped,delivered,cancelled,returned',
                'total_weight' => 'nullable|numeric|min:0',
                'total_price' => 'required|numeric|min:0',
                'total_shipping' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'preparist_user_id' => 'required|exists:users,id',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'customer_address' => 'nullable|string|max:500',
                'marketplace_id' => 'nullable|exists:mdx_marketplaces,id',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Order::class,
            $id,
            [
                'awb_code' => [
                    'nullable',
                    'string',
                    'max:255',
                    Rule::unique('trx_orders', 'awb_code')->ignore($id)
                ],
                'read_at' => 'nullable|date',
                'prepared_at' => 'nullable|date',
                'prepare_duration' => 'nullable|integer|min:0',
                'readytoship_at' => 'nullable|date',
                'readytoship_marketplace' => 'nullable|string|max:255',
                'online_store_id' => 'required|exists:mdx_online_stores,id',
                'item_count' => 'required|integer|min:1',
                'unique_item_count' => 'required|integer|min:1',
                'status' => 'required|in:pending,read,prepared,ready_to_ship,shipped,delivered,cancelled,returned',
                'total_weight' => 'nullable|numeric|min:0',
                'total_price' => 'required|numeric|min:0',
                'total_shipping' => 'required|numeric|min:0',
                'total_amount' => 'required|numeric|min:0',
                'preparist_user_id' => 'required|exists:users,id',
                'customer_name' => 'required|string|max:255',
                'customer_phone' => 'nullable|string|max:50',
                'customer_address' => 'nullable|string|max:500',
                'marketplace_id' => 'nullable|exists:mdx_marketplaces,id',
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Order::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Order::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Order::class,
            $request->all()
        );
    }

    public function getLazadaOrder($id)
    {
        // $token = config('marketplace.lazada.access_token');
        $access_token = '50000700931sNGv7jlNVPtDZZI0ipQDuQg9jDRBlSTF1fb7d45egGMiXDHpIoTos';
        $baseUrl = config('marketplace.lazada.base_url');
        $app_key = config('marketplace.lazada.app_key');
        $timestamp = (int) (microtime(true) * 1000);

        $signinmethod = 'sha256';
        $sign = '31BB8D408663C84BBE6D2431147CD98BEC909BB6F185F2ACC098C36BD5352846';

        $response = $this->getMarketplaceData(
            $baseUrl,
            '/order/get',
            ['order_id' => (int) $id, 'app_key' => $app_key, 'timestamp' => $timestamp, 'sign_method' => $signinmethod, 'sign' => $sign],
            // $token
        );

        return response()->json($response, $response['success'] ? 200 : 500);
    }

    /**
     * Submit order preparation with scanned products
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function submitPreparation(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|exists:trx_orders,id',
                'prepared_at' => 'required|date',
                'order_items' => 'required|array|min:1',
                'order_items.*.id' => 'required|exists:trx_order_items,id',
                'order_items.*.scanned_barcodes' => 'required|array|min:1',
                'order_items.*.scanned_barcodes.*' => 'string',
            ]);

            $orderId = $request->input('order_id');
            $preparedAt = $request->input('prepared_at');
            $orderItemsInput = $request->input('order_items');

            // Find order
            $order = Order::find($orderId);

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order tidak ditemukan'
                ], 404);
            }

            // Validate all barcodes per order item (check for concurrent deletions)
            $conflictingBarcodes = [];
            $itemsToProcess = []; // [ ['order_item_id' => ..., 'products' => [...]] ]
            $totalProductsScanned = 0;

            foreach ($orderItemsInput as $itemInput) {
                $orderItemId = $itemInput['id'];
                $scannedBarcodes = $itemInput['scanned_barcodes'];

                // Verify the order item belongs to this order
                $orderItem = OrderItem::where('id', $orderItemId)
                    ->where('order_id', $orderId)
                    ->first();

                if (!$orderItem) {
                    return response()->json([
                        'success' => false,
                        'message' => "Order item tidak ditemukan atau bukan milik order ini: $orderItemId"
                    ], 404);
                }

                $productsForItem = [];

                foreach ($scannedBarcodes as $barcode) {
                    $product = \App\Models\MasterData\Product::withTrashed()->where('barcode', $barcode)->first();

                    if (!$product) {
                        return response()->json([
                            'success' => false,
                            'message' => "Barcode tidak ditemukan: $barcode"
                        ], 404);
                    }

                    if ($product->deleted_at !== null) {
                        $conflictingBarcodes[] = [
                            'order_item_id' => $orderItemId,
                            'barcode' => $barcode,
                            'deleted_at' => $product->deleted_at
                        ];
                    } else {
                        $productsForItem[] = $product;
                    }
                }

                $itemsToProcess[] = [
                    'order_item_id' => $orderItemId,
                    'order_item' => $orderItem,
                    'products' => $productsForItem,
                ];
                $totalProductsScanned += count($productsForItem);
            }

            // If there are conflicting barcodes, return error
            if (count($conflictingBarcodes) > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Terdapat produk yang sudah di-scan oleh user lain. Silakan refresh dan scan ulang.',
                    'conflicting_barcodes' => $conflictingBarcodes
                ], 409);
            }

            // All validations passed, proceed with associating products to order items and soft-delete
            $readyToShipAt = now();

            foreach ($itemsToProcess as $item) {
                $orderItem = $item['order_item'];

                foreach ($item['products'] as $product) {
                    // Associate product to order item
                    $orderItem->product_id = $product->id;
                    $orderItem->save();

                    // Soft-delete the product
                    $product->delete();
                }

                // Mark order item as prepared
                $orderItem->item_prepared_at = $readyToShipAt;
                $orderItem->save();
            }

            // Calculate prepare_duration in seconds
            $preparedAtCarbon = \Carbon\Carbon::parse($preparedAt);
            $readyToShipAtCarbon = \Carbon\Carbon::parse($readyToShipAt);
            $prepareDuration = $preparedAtCarbon->diffInSeconds($readyToShipAtCarbon);

            // Update order
            $order->prepared_at = $preparedAt;
            $order->readytoship_at = $readyToShipAt;
            $order->prepare_duration = $prepareDuration;
            $order->save();

            return response()->json([
                'success' => true,
                'message' => 'Order berhasil diproses dan siap dikirim',
                'data' => [
                    'order_id' => $orderId,
                    'prepared_at' => $order->prepared_at,
                    'readytoship_at' => $order->readytoship_at,
                    'prepare_duration' => $order->prepare_duration,
                    'products_scanned' => $totalProductsScanned,
                    'order_items_processed' => count($itemsToProcess),
                ]
            ], 200);
        } catch (\Throwable $e) {
            return response()->json([
                'success' => false,
                'message' => 'Internal Server Error: ' . $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ], 500);
        }
    }

    /**
     * Assign the order to the currently authenticated user (preparist)
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignToMe(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:trx_orders,id',
        ]);

        $orderId = $request->input('order_id');
        $user = auth()->user();

        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan'
            ], 404);
        }

        // Check if already assigned to someone else
        if ($order->preparist_user_id && $order->preparist_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Order sudah di-assign ke user lain'
            ], 400);
        }

        $order->preparist_user_id = $user->id;
        $order->read_at = now();
        $order->status = \App\Enums\OrderStatus::READ;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil di-assign ke Anda',
            'data' => $this->structure()($order)
        ], 200);
    }

    /**
     * Get orders assigned to the currently authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function assignedOrders(Request $request)
    {
        $user = auth()->user();
        $isPrepared = $request->query('is_prepared');

        return $this->baseIndex(
            $request,
            Order::class,
            [],
            ["marketplace_id", "awb_code", "status", "online_store_id"],
            $this->structure(),
            function ($query) use ($user, $isPrepared) {
                $query->where('preparist_user_id', $user->id);
                if ($isPrepared === 'true') {
                    $query->whereNotNull('prepared_at');
                } elseif ($isPrepared === 'false') {
                    $query->whereNull('prepared_at');
                }
            }
        );
    }

    /**
     * Unassign the order from the currently authenticated user
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function unassignOrder(Request $request)
    {
        $request->validate([
            'order_id' => 'required|exists:trx_orders,id',
        ]);

        $orderId = $request->input('order_id');
        $user = auth()->user();

        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order tidak ditemukan'
            ], 404);
        }

        if ($order->preparist_user_id !== $user->id) {
            return response()->json([
                'success' => false,
                'message' => 'Anda tidak memiliki otoritas untuk melempar order ini'
            ], 403);
        }

        $order->preparist_user_id = null;
        $order->read_at = null;
        $order->status = \App\Enums\OrderStatus::PENDING;
        $order->save();

        return response()->json([
            'success' => true,
            'message' => 'Order berhasil dilepas',
            'data' => $this->structure()($order)
        ], 200);
    }
}
