<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use App\Models\Transactions\ReturnReceipt;
use App\Models\Transactions\ReturnReceiptDetail;
use App\Models\MasterData\Product;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ReturnReceiptController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn(ReturnReceipt $data) => [
            'id' => $data->id,
            'order_id' => $data->order_id,
            'awb_code' => $data->awb_code,
            'received_at' => $data->received_at?->utc()->toISOString(),
            'return_status' => $data->return_status,
            'user' => $data->user ? [
                'id' => $data->user->id,
                'name' => $data->user->name,
            ] : null,
            'notes' => $data->notes,
            'created_at' => $data->created_at?->utc()->toISOString(),
            'order' => $data->order ? [
                'id' => $data->order->id,
                'order_sn' => $data->order->order_sn,
                'customer_name' => $data->order->customer_name,
                'marketplace' => $data->order->marketplace ? [
                    'name' => $data->order->marketplace->name,
                ] : null,
            ] : null,
            'details' => $data->details->map(fn(ReturnReceiptDetail $d) => [
                'id' => $d->id,
                'order_item_id' => $d->order_item_id,
                'barcode_scanned' => $d->barcode_scanned,
                'is_received' => $d->is_received,
                'order_item' => $d->orderItem ? [
                    'item_name' => $d->orderItem->item_name,
                    'color' => $d->orderItem->color,
                    'size' => $d->orderItem->size,
                    'quantity_purchased' => $d->orderItem->quantity_purchased,
                ] : null,
            ]),
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            ReturnReceipt::class,
            ['order.marketplace', 'user', 'details.orderItem'],
            ['return_status', 'awb_code'],
            $this->structure(),
            function ($query) use ($request) {
                if ($request->filled('start_date') && $request->filled('end_date')) {
                    $query->whereBetween('created_at', [
                        $request->input('start_date') . ' 00:00:00',
                        $request->input('end_date') . ' 23:59:59'
                    ]);
                }
                $query->orderByDesc('created_at');
            }
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            ReturnReceipt::class,
            $id,
            ['order.marketplace', 'user', 'details.orderItem'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        $request->validate([
            'awb_code' => 'required|string|max:255',
        ]);

        $awb = trim($request->input('awb_code'));

        // Cari order yang sesuai resi/awb nya atau order_sn nya
        $order = Order::where(function ($query) use ($awb) {
                $query->where('awb_code', $awb)
                    ->orWhere('order_sn', $awb);
            })
            ->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order dengan resi atau nomor pesanan tersebut tidak ditemukan.',
            ], 404);
        }

        // Jika TikTok Shop dan statusnya shipped/delivered, ubah ke returned secara otomatis
        $isTiktok = $order->marketplace && in_array(strtolower($order->marketplace->code), ['tiktok_shop', 'tiktok']);

        if ($order->status !== \App\Enums\OrderStatus::RETURNED) {
            if ($isTiktok && in_array($order->status, [\App\Enums\OrderStatus::SHIPPED, \App\Enums\OrderStatus::DELIVERED])) {
                $order->status = \App\Enums\OrderStatus::RETURNED;
                $order->save();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Order tersebut tidak dalam status returned.',
                ], 400);
            }
        }

        // Cek jika return receipt sudah ada
        $receipt = ReturnReceipt::where('order_id', $order->id)->first();

        if (!$receipt) {
            DB::transaction(function () use ($order, &$receipt) {
                $receipt = ReturnReceipt::create([
                    'order_id' => $order->id,
                    'awb_code' => $order->awb_code,
                    'return_status' => 'pending',
                ]);

                // Buat details placeholder dari order items
                foreach ($order->order_items as $item) {
                    // Buat detail penerimaan sejumlah quantity_purchased
                    for ($i = 0; $i < $item->quantity_purchased; $i++) {
                        ReturnReceiptDetail::create([
                            'return_receipt_id' => $receipt->id,
                            'order_item_id' => $item->id,
                            'is_received' => false,
                        ]);
                    }
                }
            });
        }

        return response()->json([
            'success' => true,
            'message' => 'Penerimaan retur berhasil diinisiasi.',
            'data' => $this->structure()($receipt->load(['order.marketplace', 'user', 'details.orderItem'])),
        ], 200);
    }

    public function validateBarcode(Request $request)
    {
        $request->validate([
            'return_receipt_id' => 'required|exists:trx_return_receipts,id',
            'barcode' => 'required|string',
        ]);

        $receiptId = $request->input('return_receipt_id');
        $barcode = trim($request->input('barcode'));

        $receipt = ReturnReceipt::findOrFail($receiptId);
        $orderItemIds = $receipt->order->order_items->pluck('id');

        // Cari product (termasuk yang soft-deleted saat outbound packaging)
        $product = Product::withTrashed()->where('barcode', $barcode)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Barcode produk tidak ditemukan di sistem.',
            ], 404);
        }

        // Cari order item yang terasosiasi dengan produk ini
        $matchingItem = OrderItem::whereIn('id', $orderItemIds)
            ->where('product_id', $product->id)
            ->first();

        if (!$matchingItem) {
            // Jika product_id tidak diset langsung atau tidak match, coba cocokkan model, color, size
            $matchingItem = OrderItem::whereIn('id', $orderItemIds)
                ->where('sku', 'LIKE', '%' . $product->model?->name . '%')
                ->first();

            if (!$matchingItem) {
                return response()->json([
                    'success' => false,
                    'message' => 'Produk dengan barcode ini tidak terdaftar pada pesanan retur ini.',
                ], 400);
            }
        }

        // Cari detail item yang belum diterima untuk order item ini
        $detail = ReturnReceiptDetail::where('return_receipt_id', $receiptId)
            ->where('order_item_id', $matchingItem->id)
            ->where('is_received', false)
            ->first();

        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Semua kuantitas untuk item ini sudah diterima.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'detail_id' => $detail->id,
                'order_item_id' => $matchingItem->id,
                'item_name' => $matchingItem->item_name,
                'color' => $matchingItem->color,
                'size' => $matchingItem->size,
                'barcode' => $barcode,
            ],
        ], 200);
    }

    public function submit(Request $request)
    {
        $request->validate([
            'return_receipt_id' => 'required|exists:trx_return_receipts,id',
            'details' => 'required|array',
            'details.*.id' => 'required|exists:trx_return_receipt_details,id',
            'details.*.barcode_scanned' => 'nullable|string',
            'details.*.is_received' => 'required|boolean',
            'notes' => 'nullable|string',
        ]);

        $receiptId = $request->input('return_receipt_id');
        $detailsInput = $request->input('details');
        $notes = $request->input('notes');

        $receipt = ReturnReceipt::findOrFail($receiptId);

        DB::transaction(function () use ($receipt, $detailsInput, $notes) {
            foreach ($detailsInput as $d) {
                $detail = ReturnReceiptDetail::where('return_receipt_id', $receipt->id)
                    ->where('id', $d['id'])
                    ->first();

                if ($detail) {
                    $detail->update([
                        'barcode_scanned' => $d['barcode_scanned'] ?? null,
                        'is_received' => $d['is_received'],
                    ]);
                }
            }

            // Hitung status penerimaan retur
            $totalDetails = ReturnReceiptDetail::where('return_receipt_id', $receipt->id)->count();
            $receivedDetails = ReturnReceiptDetail::where('return_receipt_id', $receipt->id)
                ->where('is_received', true)
                ->count();

            if ($receivedDetails === 0) {
                $status = 'pending';
            } elseif ($receivedDetails < $totalDetails) {
                $status = 'partial';
            } else {
                $status = 'received';
            }

            $receipt->update([
                'return_status' => $status,
                'received_at' => $receivedDetails > 0 ? now() : null,
                'received_by' => Auth::id(),
                'notes' => $notes,
            ]);
        });

        return response()->json([
            'success' => true,
            'message' => 'Penerimaan retur berhasil disimpan.',
            'data' => $this->structure()($receipt->load(['order.marketplace', 'user', 'details.orderItem'])),
        ], 200);
    }
}
