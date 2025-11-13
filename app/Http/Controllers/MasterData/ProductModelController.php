<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\ProductModel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductModelController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'code' => $data->code,
            'name' => $data->name,
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            ProductModel::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            ProductModel::class,
            $id,
            ['product'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            ProductModel::class,
            [
                'code' => 'required|string|unique:mdx_models,code|max:255',
                'name' => 'required|string|max:255',
            ],
            null
        );
    }

    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            ProductModel::class,
            $id,
            [
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_models', 'code')->ignore($id)
                ],
                'name' => 'required|string|max:255',
            ],
            null
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
}
