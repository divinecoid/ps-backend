<?php

namespace App\Http\Controllers\MasterData;

use App\Exports\ProductModelTemplateExport;
use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Imports\ProductModelImport;
use App\Models\MasterData\ProductModel;
use App\Models\Transactions\FabricCutting;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Maatwebsite\Excel\Facades\Excel;

class ProductModelController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'sku' => $data->sku,
            'name' => $data->name,
            'color_id' => $data->colors->pluck('id'),
            'size_id' => $data->sizes->pluck('id'),
            'colors' => $data->colors->map(fn($c) => [
                'id' => $c->id,
                'name' => $c->name
            ]),
            'sizes' => $data->sizes->map(fn($s) => [
                'id' => $s->id,
                'name' => $s->name
            ])
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            ProductModel::class,
            ['colors', 'sizes'],
            ['sku', 'name'],
            $this->structure()
        );
    }
    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            ProductModel::class,
            ['colors', 'sizes'],
            ['sku', 'name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            ProductModel::class,
            $id,
            ['colors', 'sizes'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            ProductModel::class,
            [
                'sku' => 'required|string|unique:mdx_models,sku|max:255',
                'name' => 'required|string|max:255',
                'size_id' => [
                    'required',
                    Rule::exists('mdx_sizes', 'id')->whereNull('deleted_at'),
                ],
                'color_id' => [
                    'required',
                    Rule::exists('mdx_colors', 'id')->whereNull('deleted_at'),
                ]
            ],
            function (ProductModel $model, Request $req) {
                $model->sizes()->attach($req->size_id);
                $model->colors()->attach($req->color_id);
            }
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            ProductModel::class,
            $id,
            [
                'sku' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_models', 'sku')->ignore($id)
                ],
                'name' => 'required|string|max:255',
                'size_id' => [
                    'sometimes',
                    'required',
                    Rule::exists('mdx_sizes', 'id')->whereNull('deleted_at'),
                ],
                'color_id' => [
                    'sometimes',
                    'required',
                    Rule::exists('mdx_colors', 'id')->whereNull('deleted_at'),
                ]
            ],
            function (ProductModel $model, Request $req) {
                $model->sizes()->sync($req->size_id);
                $model->colors()->sync($req->color_id);
            }
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            ProductModel::class,
            $id
        );
    }

    public function restore($id)
    {
        return $this->baseRestore(ProductModel::class, $id);
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            ProductModel::class,
            $request->all()
        );
    }

    public function forceDestroy($id)
    {
        return $this->baseForceDelete(
            ProductModel::class,
            $id
        );
    }

    public function multiForceDestroy(Request $request)
    {
        return $this->baseForceDelete(
            ProductModel::class,
            $request->all()
        );
    }

    public function downloadTemplate()
    {
        return Excel::download(new ProductModelTemplateExport(), 'template-model-produk.xlsx');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:xlsx,xls,csv',
        ]);

        $import = new ProductModelImport();
        Excel::import($import, $request->file('file'));

        if ($import->failures()->isNotEmpty()) {
            $first = $import->failures()->first();
            return $this->errorResponse(
                422,
                "Baris {$first->row()}: " . implode(', ', $first->errors())
            );
        }

        return $this->successResponse(null);
    }

    public function modelColor(Request $request, $id)
    {
        $model = ProductModel::find($id);
        if (!$model) {
            return $this->errorResponse(404, 'Not found');
        }
        return $this->baseRelationIndex(
            $request,
            $model->colors(),
            ['name'],
            fn($c) => [
                'id' => $c->id,
                'name' => $c->name,
            ]
        );
    }

    public function modelSize($id)
    {
        $model = ProductModel::find($id);
        if (!$model) {
            return $this->errorResponse(404, 'Not found');
        }
        return $this->baseShow(
            ProductModel::class,
            $id,
            ['sizes'],
            fn($data) => $data->sizes->map(fn($c) => ['id' => $c->id, 'name' => $c->name])
        );
    }

public function getFabricColor($id)
{
    // 1. Cari semua FabricCutting CLOSED yang punya receive untuk model ini
    $cuttings = FabricCutting::query()
        ->where('status', '=', 'CLOSED')
        ->whereHas('fabric_cutting_receives', function ($q) use ($id) {
            $q->where('model_id', $id);
        })
        ->with([
            'fabric_cutting_receives' => function ($q) use ($id) {
                $q->where('model_id', $id);
            },
            'clothes.color',
        ])
        ->get();

    // 2. Hitung stok yang sudah terpakai (sum req_qty dari request_detail per cutting_id + model_id + size_id)
    $usedQty = \App\Models\Transactions\RequestDetail::query()
        ->whereIn('cloth_id', $cuttings->pluck('id'))
        ->where('model_id', $id)
        ->selectRaw('cloth_id as cutting_id, size_id, SUM(req_qty) as used_qty')
        ->groupBy('cloth_id', 'size_id')
        ->get()
        ->groupBy('cutting_id')
        ->map(fn($g) => $g->keyBy('size_id'));

    // 3. Build response
    $result = $cuttings->map(function ($cutting) use ($usedQty) {
        $receives = $cutting->fabric_cutting_receives;
        $used = $usedQty[$cutting->id] ?? collect();

        $detail = $receives->map(function ($r) use ($used) {
            $usedForSize = $used[$r->size_id]->used_qty ?? 0;
            return [
                'size_id' => $r->size_id,
                'avl_qty' => max(0, $r->qty - $usedForSize),
            ];
        })->values();

        if ($detail->isEmpty()) return null;

        return [
            'id' => $cutting->id, // fabric_cutting_id
            'name' => ($cutting->clothes->color->name ?? '') . ' - ' . ($cutting->clothes->sequence ?? ''),
            'detail' => $detail,
        ];
    })->filter()->values();

    return $this->successResponse($result);
}
    public function getFabricQty(Request $request)
    {
        $request->validate([
            'model_id' => 'required|uuid',
            'color_name' => 'required|string'
        ]);

        $modelId = $request->model_id;
        $colorName = $request->color_name;

        $cuttings = FabricCutting::whereHas('fabric_cutting_request_detail', function ($q) use ($modelId) {
            $q->where('model_id', $modelId);
        })
            ->where('quantity', '>', 0)
            ->with(['clothes.color', 'fabric_cutting_request_detail.size'])
            ->get();

        $matchedCutting = null;
        foreach ($cuttings as $cutting) {
            if ($cutting->clothes && $cutting->clothes->color) {
                $name = $cutting->clothes->color->name . '-' . $cutting->clothes->sequence;
                if ($name === $colorName) {
                    $matchedCutting = $cutting;
                    break;
                }
            }
        }

        if (!$matchedCutting) {
            return $this->errorResponse(404, 'Data not found or already used');
        }

        $details = $matchedCutting->fabric_cutting_request_detail->where('model_id', $modelId);
        $variantDetail = $details->map(function ($item) {
            return [
                'size_id' => $item->size_id,
                'dozen_qty' => floor($item->req_qty / 12),
                'piece_qty' => $item->req_qty % 12,
                'size_name' => $item->size->name ?? '',
                'req_qty' => $item->req_qty
            ];
        })->values();

        // Mark as used so it won't appear again
        $matchedCutting->update(['quantity' => 0]);

        return $this->successResponse([
            'fabric_cutting_id' => $matchedCutting->id,
            'variant_detail' => $variantDetail
        ]);
    }

}
