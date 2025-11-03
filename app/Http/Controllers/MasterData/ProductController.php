<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Product;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'sku' => $data->sku,
            'color_id' => $data->color_id,
            'model_id' => $data->model_id,
            'size_id' => $data->size_id,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Product::class,
            [],
            ['sku', 'color_id', 'model_id', 'size_id'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Product::class,
            $id,
            ['order_item', 'inventory'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Product::class,
            [
                'sku' => 'required|string|unique:mdx_products,sku|max:255',
                'color_id' => 'required|exists:mdx_colors,id',
                'model_id' => 'required|exists:mdx_models,id',
                'size_id' => 'required|exists:mdx_sizes,id',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Product::class,
            $id,
            [
                'sku' => 'required|string|unique:mdx_products,sku|max:255',
                'color_id' => 'required|exists:mdx_colors,id',
                'model_id' => 'required|exists:mdx_models,id',
                'size_id' => 'required|exists:mdx_sizes,id',
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
}
