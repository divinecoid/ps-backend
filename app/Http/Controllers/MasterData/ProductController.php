<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Product;
use App\Models\Transactions\RequestDetail;
use Illuminate\Http\Request;
use DB;
use Illuminate\Validation\Rule;

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
}
