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

}
