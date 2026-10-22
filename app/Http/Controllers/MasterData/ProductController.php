<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\ProductStockSeedTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\Color;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Size;
use App\Models\Transactions\RequestDetail;
use App\Services\LegacyStockService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DB;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProductController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'rack_id' => $data->rack_id,
            'model_id' => $data->model_id,
            'model' => (object) [
                'name' => $data->model->name
            ],
            'rack' => $data->rack ? (object) [
                'name' => $data->rack->name
            ] : null,
            'color' => $data->color ? (object) [
                'name' => $data->color->name
            ] : null,
            'size' => $data->size ? (object) [
                'name' => $data->size->name
            ] : null,
            'barcode' => $data->barcode,
            'series' => $data->series,
            'stock_batch_id' => $data->stock_batch_id,
            'created_at' => $data->created_at,
        ];
    }

    /**
     * Applies the `stock_batch_id` filter used by both index/master when the
     * frontend is drilled into a single group. The literal value "none"
     * means "the synthetic ungrouped bucket" (stock_batch_id IS NULL).
     */
    private function applyBatchFilter($query, Request $request)
    {
        if ($request->filled('stock_batch_id')) {
            if ($request->input('stock_batch_id') === 'none') {
                $query->whereNull('stock_batch_id');
            } else {
                $query->where('stock_batch_id', $request->input('stock_batch_id'));
            }
        }
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Product::class,
            ['rack', 'model', 'color', 'size'],
            ['barcode'],
            $this->structure(),
            function ($query) use ($request) {
                $this->applyBatchFilter($query, $request);
                $query->orderByDesc('created_at');
            }
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Product::class,
            ['rack', 'model', 'color', 'size'],
            ['barcode'],
            $this->structure(),
            function ($query) use ($request) {
                $this->applyBatchFilter($query, $request);
                $query->orderByDesc('created_at');
            }
        );
    }

    /**
     * Paginated list of GROUPS instead of individual products: one row per
     * `stock_batch_id` (e.g. one "Input Stok Lama" submission), plus a single
     * synthetic "none" group covering every product with a null batch id
     * (the normal production/inbound flow, which doesn't set a batch).
     */
    public function batches(Request $request)
    {
        $perPage = (int) ($request->input('per_page', 10));

        $query = Product::query()
            ->select([
                'stock_batch_id',
                DB::raw('COUNT(*) as total'),
                DB::raw('MIN(created_at) as created_at'),
                DB::raw('MAX(model_id) as sample_model_id'),
                DB::raw('COUNT(DISTINCT model_id) as distinct_model_count'),
                DB::raw("SUM(CASE WHEN barcode LIKE '%|D|%' THEN 1 ELSE 0 END) as dozen_count"),
                DB::raw("SUM(CASE WHEN barcode LIKE '%|P|%' THEN 1 ELSE 0 END) as piece_count"),
            ])
            ->groupBy('stock_batch_id')
            ->orderByRaw('MIN(created_at) DESC');

        $data = $query->paginate($perPage);

        $modelIds = collect($data->items())->pluck('sample_model_id')->filter()->unique();
        $models = \App\Models\MasterData\ProductModel::whereIn('id', $modelIds)->get()->keyBy('id');

        $items = collect($data->items())->map(function ($row) use ($models) {
            $isUngrouped = $row->stock_batch_id === null;
            $sampleModel = $models[$row->sample_model_id] ?? null;

            return [
                'batch_id' => $isUngrouped ? 'none' : $row->stock_batch_id,
                'is_ungrouped' => $isUngrouped,
                'label' => $isUngrouped ? 'Tanpa Batch (Produksi Normal)' : 'Batch Input Stok Lama',
                'total' => (int) $row->total,
                // Old-stock dozen batches hold D barcodes (one per dozen) plus
                // their 12 P barcodes each.
                'dozen_count' => (int) $row->dozen_count,
                'piece_count' => (int) $row->piece_count,
                'is_dozen' => !$isUngrouped && (int) $row->dozen_count > 0,
                // Number of barcodes the batch was entered with (dozens for a
                // dozen batch, pieces otherwise) — what "Jumlah" edits.
                'qty' => (int) ($row->dozen_count > 0 ? $row->dozen_count : $row->piece_count),
                'created_at' => $row->created_at,
                'model_summary' => $row->distinct_model_count == 1 ? $sampleModel?->name : "{$row->distinct_model_count} model",
            ];
        });

        return response()->json($this->paginateResponse($data, $items));
    }

    /**
     * Unpaginated list of every product in a single batch/group — used to
     * bulk-print a whole group at once. Bounded to keep the response sane.
     */
    public function batchItems(Request $request)
    {
        $batchId = $request->input('stock_batch_id', 'none');

        $query = Product::with(['rack', 'model', 'color', 'size'])
            ->orderByDesc('created_at')
            ->limit(50000);

        if ($batchId === 'none') {
            $query->whereNull('stock_batch_id');
        } else {
            $query->where('stock_batch_id', $batchId);
        }

        $items = $query->get()->map($this->structure());

        return $this->successResponse($items);
    }

    public function show($id)
    {
        return $this->baseShow(
            Product::class,
            $id,
            ['rack', 'model', 'color', 'size'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'rack_id' => [
                    'required',
                    Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
                ],
                'model_id' => [
                    'required',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
                ],
                'barcode' => [
                    'required',
                    'string',
                    'max:255',
                    'unique:mdx_products,barcode',
                ],
            ],
            function ($data) {
                $request = RequestDetail::where('barcode', 'like', substr($data['barcode'], 0, strrpos($data['barcode'], '|')) . '%')->firstOrFail();
                if (!$request) {
                    return $this->errorResponse(422, 'Prefiks barcode tidak valid');
                }
                $barcodeIndex = substr($data['barcode'], strrpos($data['barcode'], '|') + 1);
                if (!ctype_digit($barcodeIndex)) {
                    return $this->errorResponse(422, 'Sequence barcode tidak valid');
                }
                $totalQuantity = $request->req_qty;
                if ((int)$barcodeIndex < 1 || (int)$barcodeIndex > $totalQuantity) {
                    return $this->errorResponse(422, 'Sequence barcode di luar jangkauan');
                }
                return DB::transaction(function () use ($data) {
                    $product = Product::create($data);
                    return $this->successResponse($product);
                });
            }
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Product::class,
            $id,
            [
                'rack_id' => [
                    'required',
                    Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
                ],
                'model_id' => [
                    'required',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
                ],
                'barcode' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_products', 'barcode')->ignore($id),
                ],
            ],
            null
        );
    }

    public function destroy($id)
    {
        $product = Product::find($id);
        if ($product?->stock_batch_id !== null) {
            return $this->deleteLegacyProducts(collect([$product]));
        }

        return $this->baseDelete(
            Product::class,
            $id
        );
    }

    public function restore($id)
    {
        $product = Product::onlyTrashed()->find($id);
        if ($product?->stock_batch_id !== null) {
            return $this->restoreLegacyProducts(collect([$product]));
        }

        return $this->baseRestore(Product::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        $ids = $request->all();
        $products = Product::whereIn('id', $ids)->get();
        $legacy = $products->whereNotNull('stock_batch_id');
        if ($legacy->isEmpty()) {
            return $this->baseDelete(Product::class, $ids);
        }

        $others = $products->whereNull('stock_batch_id')->pluck('id')->all();
        if ($others) {
            $this->baseDelete(Product::class, $others);
        }

        return $this->deleteLegacyProducts($legacy);
    }

    /**
     * Old-stock rows: a dozen (D) barcode takes its piece (P) barcodes with
     * it, big-warehouse stock is taken back out, and barcodes that already
     * moved to a small-warehouse rack (Mutasi) cannot be deleted.
     */
    private function deleteLegacyProducts($products)
    {
        $rows = $this->withDozenPieces($products);
        $moved = $rows->first(fn($p) => $p->rack_id !== null);
        if ($moved) {
            return $this->errorResponse(422, "Barcode {$moved->barcode} sudah berada di rak gudang kecil (sudah dimutasi), tidak bisa dihapus.");
        }

        $stock = app(LegacyStockService::class);
        DB::transaction(function () use ($rows, $stock) {
            foreach ($rows as $row) {
                $stock->release($row);
                $row->delete();
            }
        });
        \App\Support\AuditLogger::log(
            \App\Support\AuditLogger::mapModelToModule(Product::class),
            'DELETE',
            'Menghapus stok lama dengan ID: ' . $rows->pluck('id')->implode(', ')
        );

        return $this->successResponse($rows->values(), 'Success delete data');
    }

    private function restoreLegacyProducts($products)
    {
        $rows = $this->withDozenPieces($products, true);

        $stock = app(LegacyStockService::class);
        DB::transaction(function () use ($rows, $stock) {
            foreach ($rows as $row) {
                $row->restore();
                $stock->reclaim($row);
            }
        });
        \App\Support\AuditLogger::log(
            \App\Support\AuditLogger::mapModelToModule(Product::class),
            'RESTORE',
            'Mengembalikan stok lama dengan ID: ' . $rows->pluck('id')->implode(', ')
        );

        return $this->successResponse($rows->values());
    }

    /**
     * Adds every sibling that shares a dozen's series (same batch) for each
     * dozen (D) barcode in the list; piece barcodes alone stay alone.
     */
    private function withDozenPieces($products, bool $trashed = false)
    {
        $stock = app(LegacyStockService::class);
        $siblings = $products
            ->filter(fn($p) => $stock->group($p) === 'D')
            ->flatMap(fn($d) => Product::query()
                ->when($trashed, fn($q) => $q->onlyTrashed())
                ->where('stock_batch_id', $d->stock_batch_id)
                ->where('series', $d->series)
                ->get());

        return $products->concat($siblings)->unique('id')->values();
    }

    /** Edit every active product of an old-stock batch (model / color / size / rack). */
    public function updateBatch(Request $request, $batchId)
    {
        $products = Product::where('stock_batch_id', $batchId)->get();
        if ($products->isEmpty()) {
            return $this->errorResponse(404, 'Batch tidak ditemukan');
        }

        return $this->updateLegacyProducts($request, $products, true);
    }

    /** Edit one old-stock product; its dozen's other barcodes follow. */
    public function updateBatchItem(Request $request, $id)
    {
        $product = Product::whereNotNull('stock_batch_id')->find($id);
        if (!$product) {
            return $this->errorResponse(404, 'Produk stok lama tidak ditemukan');
        }

        $rows = Product::where('stock_batch_id', $product->stock_batch_id)
            ->where('series', $product->series)
            ->get();

        return $this->updateLegacyProducts($request, $rows);
    }

    private function updateLegacyProducts(Request $request, $products, bool $withQty = false)
    {
        $data = $request->validate([
            'model_id' => ['nullable', Rule::exists('mdx_models', 'id')->whereNull('deleted_at')],
            'color_id' => ['nullable', Rule::exists('mdx_colors', 'id')->whereNull('deleted_at')],
            'size_id' => ['nullable', Rule::exists('mdx_sizes', 'id')->whereNull('deleted_at')],
            'rack_id' => ['nullable', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
            'qty' => $withQty ? ['nullable', 'integer', 'min:1'] : ['prohibited'],
        ]);
        $attributes = array_filter(\Illuminate\Support\Arr::only($data, ['model_id', 'color_id', 'size_id']));
        $rackId = $data['rack_id'] ?? null;
        $qty = $withQty ? ($data['qty'] ?? null) : null;
        if (!$attributes && !$rackId && $qty === null) {
            return $this->errorResponse(422, 'Tidak ada perubahan yang diisi.');
        }

        $rows = $this->withDozenPieces($products);
        if ($attributes && ($moved = $rows->first(fn($p) => $p->rack_id !== null))) {
            return $this->errorResponse(422, "Barcode {$moved->barcode} sudah berada di rak gudang kecil (sudah dimutasi), model/warna/ukuran tidak bisa diubah.");
        }
        if ($rackId && $rows->contains(fn($p) => $p->rack_id === null)) {
            $isBig = \App\Models\MasterData\Rack::whereHas('warehouse', fn($q) => $q->where('type', 'BESAR'))->whereKey($rackId)->exists();
            if (!$isBig) {
                return $this->errorResponse(422, 'Rak untuk stok gudang besar harus berada di gudang besar.');
            }
        }

        $stock = app(LegacyStockService::class);
        try {
            DB::transaction(function () use ($stock, $rows, $attributes, $rackId, $qty, $products) {
                if ($attributes || $rackId) {
                    $stock->updateRows($rows, $attributes, $rackId);
                }
                if ($qty !== null) {
                    $stock->resizeBatch($products->first()->stock_batch_id, $qty);
                }
            });
        } catch (\InvalidArgumentException $e) {
            return $this->errorResponse(422, $e->getMessage());
        }
        \App\Support\AuditLogger::log(
            \App\Support\AuditLogger::mapModelToModule(Product::class),
            'UPDATE',
            'Mengubah stok lama dengan ID: ' . $rows->pluck('id')->implode(', ')
                . ($qty !== null ? " (jumlah menjadi {$qty})" : '')
        );

        return $this->successResponse($rows->count(), 'Berhasil mengubah ' . $rows->count() . ' barcode.');
    }

    /** Delete every active product of an old-stock batch. */
    public function destroyBatch($batchId)
    {
        $products = Product::where('stock_batch_id', $batchId)->get();
        if ($products->isEmpty()) {
            return $this->errorResponse(404, 'Batch tidak ditemukan');
        }

        return $this->deleteLegacyProducts($products);
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            Product::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            Product::class,
            $request->all()
        );
    }

    public function downloadSeedTemplate()
    {
        return Excel::download(new ProductStockSeedTemplateExport(), 'template-input-stok-lama.xlsx');
    }

    /**
     * Bulk "Input Stok Lama" via Excel. All rows are validated up front and
     * the whole file is rejected (no products created) if any row is
     * invalid — matches the manual form's per-submission barcode batch.
     */
    public function importSeedDummy(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $rows = Excel::toArray([], $request->file('file'))[0] ?? [];
        if (count($rows) < 2) {
            return $this->errorResponse(422, 'File kosong atau tidak memiliki data.');
        }

        $header = array_map(fn($h) => strtolower(trim((string) $h)), array_shift($rows));
        $expected = ['kode_cmt', 'sku_model', 'kode_warna', 'kode_ukuran', 'kode_rak', 'tipe', 'nomor_urut', 'qty'];
        if ($header !== $expected) {
            return $this->errorResponse(422, 'Format kolom template tidak sesuai.');
        }

        $cmts = CMT::pluck('id', 'code');
        $models = ProductModel::pluck('id', 'sku');
        $colors = Color::pluck('id', 'code');
        $sizes = Size::pluck('id', 'code');
        $racks = Rack::pluck('id', 'code');

        $parsed = [];
        foreach ($rows as $i => $row) {
            $rowNumber = $i + 2;
            if (empty(array_filter($row, fn($v) => $v !== null && $v !== ''))) {
                continue;
            }

            [$cmtCode, $sku, $colorCode, $sizeCode, $rackCode, $type, $number, $qty] = array_pad($row, 8, null);

            $cmtId = $cmts[trim((string) $cmtCode)] ?? null;
            $modelId = $models[trim((string) $sku)] ?? null;
            $colorId = $colors[trim((string) $colorCode)] ?? null;
            $sizeId = $sizes[trim((string) $sizeCode)] ?? null;
            $rackId = $rackCode ? ($racks[trim((string) $rackCode)] ?? null) : null;
            $type = strtoupper(trim((string) $type));

            if (!$cmtId) {
                return $this->errorResponse(422, "Baris {$rowNumber}: kode CMT '{$cmtCode}' tidak ditemukan.");
            }
            if (!$modelId) {
                return $this->errorResponse(422, "Baris {$rowNumber}: SKU model '{$sku}' tidak ditemukan.");
            }
            if (!$colorId) {
                return $this->errorResponse(422, "Baris {$rowNumber}: kode warna '{$colorCode}' tidak ditemukan.");
            }
            if (!$sizeId) {
                return $this->errorResponse(422, "Baris {$rowNumber}: kode ukuran '{$sizeCode}' tidak ditemukan.");
            }
            if ($rackCode && !$rackId) {
                return $this->errorResponse(422, "Baris {$rowNumber}: kode rak '{$rackCode}' tidak ditemukan.");
            }
            if (!in_array($type, ['D', 'P'])) {
                return $this->errorResponse(422, "Baris {$rowNumber}: tipe harus D atau P.");
            }
            if (!ctype_digit((string) $number) || (int) $number < 1) {
                return $this->errorResponse(422, "Baris {$rowNumber}: nomor urut tidak valid.");
            }
            if (!ctype_digit((string) $qty) || (int) $qty < 1) {
                return $this->errorResponse(422, "Baris {$rowNumber}: qty harus bilangan bulat minimal 1.");
            }

            $parsed[] = [
                'cmt_id' => $cmtId,
                'model_id' => $modelId,
                'color_id' => $colorId,
                'size_id' => $sizeId,
                'rack_id' => $rackId,
                'type' => $type,
                'number' => (int) $number,
                'qty' => (int) $qty,
            ];
        }

        if (empty($parsed)) {
            return $this->errorResponse(422, 'Tidak ada baris data yang valid untuk diproses.');
        }

        $created = DB::transaction(function () use ($parsed) {
            $batchId = (string) Str::uuid();
            $baseTime = now();
            $count = 0;

            foreach ($parsed as $line) {
                $cmt = CMT::find($line['cmt_id']);
                $model = ProductModel::find($line['model_id']);
                $color = Color::find($line['color_id']);
                $size = Size::find($line['size_id']);

                for ($i = 0; $i < $line['qty']; $i++) {
                    $series = $baseTime->copy()->addSeconds($count)->format('YmdHis');
                    $barcode = implode('|', [
                        $cmt->code,
                        $series,
                        $model->sku,
                        $color->code,
                        $size->code,
                        $line['type'],
                        $line['number'] + $i,
                    ]);

                    app(LegacyStockService::class)->register([
                        'model_id' => $line['model_id'],
                        'color_id' => $line['color_id'],
                        'size_id' => $line['size_id'],
                        'rack_id' => $line['rack_id'],
                        'series' => $series,
                        'barcode' => $barcode,
                        'type' => $line['type'],
                        'batch_id' => $batchId,
                    ]);

                    $count++;
                }
            }

            return $count;
        });

        return $this->successResponse(['created' => $created], "Berhasil membuat {$created} produk stok lama.");
    }
}
