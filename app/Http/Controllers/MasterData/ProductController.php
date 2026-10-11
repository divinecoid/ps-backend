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
            'rack' => (object) [
                'name' => $data->rack->name
            ],
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
            ->limit(1000);

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
        return $this->baseDelete(
            Product::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(Product::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            Product::class,
            $request->all()
        );
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
            if (!ctype_digit((string) $qty) || (int) $qty < 1 || (int) $qty > 100) {
                return $this->errorResponse(422, "Baris {$rowNumber}: qty harus antara 1 dan 100.");
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

                    Product::create([
                        'id' => (string) Str::uuid(),
                        'model_id' => $line['model_id'],
                        'rack_id' => $line['rack_id'],
                        'color_id' => $line['color_id'],
                        'size_id' => $line['size_id'],
                        'series' => $series,
                        'barcode' => $barcode,
                        'stock_batch_id' => $batchId,
                    ]);

                    $count++;
                }
            }

            return $count;
        });

        return $this->successResponse(['created' => $created], "Berhasil membuat {$created} produk stok lama.");
    }
}
