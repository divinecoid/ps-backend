<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Rack;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class SmallInventoryController extends Controller
{
    use CrudTrait;

    private function detailStructure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'warehouse_id' => $data->warehouse_id,
            'warehouse' => (object) [
                'name' => $data->warehouse->name
            ],
            'products' => $data->product?->groupBy('series')->map(function ($items, $series) {
                return [
                    'series' => $series,
                    'count' => $items->count(),
                    'items' => $items->map(fn($product) => [
                        'id' => $product->id,
                        'model_id' => $product->model_id,
                        'model' => (object) [
                            'name' => $product->model->name
                        ],
                        'color' => (object) [
                            'name' => $product->color->name
                        ],
                        'size' => (object) [
                            'name' => $product->size->name
                        ],
                        'barcode' => $product->barcode,
                        'series' => $product->series
                    ])->values()
                ];
            })->values()
        ];
    }

    private function overviewStructure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
            'warehouse_id' => $data->warehouse_id,
            'warehouse' => (object) [
                'name' => $data->warehouse->name
            ],
            'total' => (int) ($data->product_count ?? 0)
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Rack::class,
            ['product'],
            ['id', 'code', 'name'],
            $this->overviewStructure(),
            function (Builder $query) {
                $query->withCount('product')->has('product');
            }
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Rack::class,
            ['product'],
            ['id', 'code', 'name'],
            $this->overviewStructure(),
            function (Builder $query) {
                $query->withCount('product')->has('product');
            }
        );
    }
    public function show($id)
    {
        return $this->baseShow(
            Rack::class,
            $id,
            ['product', 'product.model', 'product.color', 'product.size'],
            $this->detailStructure()
        );
    }
}