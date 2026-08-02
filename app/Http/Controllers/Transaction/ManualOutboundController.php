<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Product;
use App\Models\Transactions\ManualOutbound;
use App\Models\Transactions\ManualOutboundDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ManualOutboundController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn(ManualOutbound $data) => [
            'id'             => $data->id,
            'outbound_date'  => $data->outbound_date?->utc()->toISOString(),
            'notes'          => $data->notes,
            'marketplace'    => $data->marketplace ? [
                'id'    => $data->marketplace->id,
                'name'  => $data->marketplace->name,
                'alias' => $data->marketplace->alias,
            ] : null,
            'user'           => $data->user ? [
                'id'   => $data->user->id,
                'name' => $data->user->name,
            ] : null,
            'total_items'    => $data->details->count(),
            'details'        => $data->details->map(fn(ManualOutboundDetail $detail) => [
                'id'      => $detail->id,
                'barcode' => $detail->barcode,
                'model'   => $detail->model?->name,
                'color'   => $detail->color?->name,
                'size'    => $detail->size?->name,
            ]),
            'summary'        => $data->details
                ->groupBy(fn(ManualOutboundDetail $d) => $d->model_id . '|' . $d->color_id . '|' . $d->size_id)
                ->map(function ($group) {
                    $first = $group->first();
                    return [
                        'model' => $first->model?->name,
                        'color' => $first->color?->name,
                        'size'  => $first->size?->name,
                        'qty'   => $group->count(),
                    ];
                })
                ->values(),
        ];
    }

    /**
     * List semua outbound manual (dengan paginasi).
     */
    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            ManualOutbound::class,
            ['marketplace', 'user', 'details'],
            ['marketplace_id'],
            fn(ManualOutbound $data) => [
                'id'            => $data->id,
                'outbound_date' => $data->outbound_date?->utc()->toISOString(),
                'marketplace'   => $data->marketplace ? [
                    'id'    => $data->marketplace->id,
                    'name'  => $data->marketplace->name,
                    'alias' => $data->marketplace->alias,
                ] : null,
                'user'          => $data->user ? [
                    'name' => $data->user->name,
                ] : null,
                'total_items'   => $data->details->count(),
                'notes'         => $data->notes,
            ],
            fn($query) => $query->orderBy('created_at', 'desc')
        );
    }

    /**
     * Detail satu outbound manual.
     */
    public function show($id)
    {
        return $this->baseShow(
            ManualOutbound::class,
            $id,
            ['marketplace', 'user', 'details.model', 'details.color', 'details.size'],
            $this->structure()
        );
    }

    /**
     * Validasi barcode sebelum discan.
     * Hanya barcode PIECE (|P|) yang diperbolehkan.
     */
    public function validateBarcode(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'barcode' => 'required|string',
            ],
            function ($data) {
                $barcode = $data['barcode'];

                // Harus format PIECE: 7 bagian, index ke-5 = 'P'
                $parts = explode('|', $barcode);
                if (count($parts) !== 7 || $parts[5] !== 'P') {
                    return $this->errorResponse(422, 'Barcode tidak valid. Hanya barcode satuan (piece) yang dapat di-scan untuk outbound manual.');
                }

                // Cek produk ada dan belum di-soft-delete
                $product = Product::where('barcode', $barcode)->first();

                if (!$product) {
                    return $this->errorResponse(404, 'Barcode tidak ditemukan dalam sistem.');
                }

                if ($product->trashed()) {
                    return $this->errorResponse(400, 'Produk dengan barcode ini sudah pernah dikeluarkan (stok habis).');
                }

                return $this->successResponse([
                    'product' => [
                        'id'      => $product->id,
                        'barcode' => $product->barcode,
                        'model'   => $product->model ? [
                            'id'   => $product->model->id,
                            'name' => $product->model->name,
                            'sku'  => $product->model->sku,
                        ] : null,
                        'color'   => $product->color ? [
                            'id'   => $product->color->id,
                            'name' => $product->color->name,
                            'code' => $product->color->code,
                        ] : null,
                        'size'    => $product->size ? [
                            'id'   => $product->size->id,
                            'name' => $product->size->name,
                            'code' => $product->size->code,
                        ] : null,
                        'rack'    => $product->rack ? [
                            'id'   => $product->rack->id,
                            'code' => $product->rack->code,
                            'name' => $product->rack->name,
                        ] : null,
                    ]
                ], 'Barcode valid dan produk tersedia.');
            }
        );
    }

    /**
     * Submit outbound manual: simpan log dan soft-delete produk.
     */
    public function submit(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'marketplace_id' => ['nullable', Rule::exists('mdx_marketplaces', 'id')->whereNull('deleted_at')],
                'barcodes'       => 'required|array|min:1',
                'barcodes.*'     => 'required|string|distinct',
                'notes'          => 'nullable|string|max:1000',
            ],
            function ($data) {
                $barcodes      = $data['barcodes'];
                $marketplaceId = $data['marketplace_id'] ?? null;
                $notes         = $data['notes'] ?? null;

                // Pre-validate semua barcode sebelum memulai transaksi
                $invalidBarcodes  = [];
                $scannedBarcodes  = [];
                $productsToProcess = [];

                foreach ($barcodes as $barcode) {
                    $parts = explode('|', $barcode);
                    if (count($parts) !== 7 || $parts[5] !== 'P') {
                        $invalidBarcodes[] = $barcode;
                        continue;
                    }

                    $product = Product::where('barcode', $barcode)->first();

                    if (!$product) {
                        $invalidBarcodes[] = $barcode;
                        continue;
                    }

                    if ($product->trashed()) {
                        $scannedBarcodes[] = $barcode;
                        continue;
                    }

                    $productsToProcess[$barcode] = $product;
                }

                $totalInvalid  = count($invalidBarcodes);
                $totalScanned  = count($scannedBarcodes);

                if ($totalInvalid > 0 || $totalScanned > 0) {
                    return $this->errorResponse(422, ($totalInvalid + $totalScanned) . ' barcode tidak valid.', [
                        'invalid'  => $invalidBarcodes,
                        'scanned'  => $scannedBarcodes,
                    ]);
                }

                try {
                    DB::transaction(function () use ($data, $marketplaceId, $notes, $productsToProcess) {
                        // Buat header outbound manual
                        $outbound = ManualOutbound::create([
                            'marketplace_id' => $marketplaceId,
                            'user_id'        => Auth::id(),
                            'outbound_date'  => now(),
                            'notes'          => $notes,
                        ]);

                        // Proses setiap produk
                        foreach ($productsToProcess as $barcode => $product) {
                            // Simpan detail log
                            ManualOutboundDetail::create([
                                'manual_outbound_id' => $outbound->id,
                                'product_id'         => $product->id,
                                'barcode'            => $barcode,
                                'model_id'           => $product->model_id,
                                'color_id'           => $product->color_id,
                                'size_id'            => $product->size_id,
                            ]);

                            // Potong stok: soft-delete produk
                            $product->delete();
                        }
                    });

                    $totalProcessed = count($productsToProcess);

                    return $this->successResponse([
                        'total_items' => $totalProcessed,
                    ], "Berhasil memproses {$totalProcessed} barang. Stok telah dipotong.");
                } catch (\Exception $e) {
                    return $this->errorResponse(500, $e->getMessage() . ' Line: ' . $e->getLine());
                }
            }
        );
    }
}
