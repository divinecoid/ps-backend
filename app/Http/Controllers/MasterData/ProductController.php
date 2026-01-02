<?php

namespace App\Http\Controllers\MasterData;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Product;
use Illuminate\Http\Request;
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
            'barcode' => $data->barcode
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Product::class,
            ['rack', 'model'],
            ['barcode'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Product::class,
            ['rack', 'model'],
            ['barcode'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Product::class,
            $id,
            ['rack', 'model'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseStore(
            $request,
            Product::class,
            [
                'rack_id' => [
                    'required',
                    Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
                ],
                'model_id' => [
                    'required',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
                ],
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
                'rack_id' => [
                    'required',
                    Rule::exists('mdx_racks', 'id')->whereNull('deleted_at'),
                ],
                'model_id' => [
                    'required',
                    Rule::exists('mdx_models', 'id')->whereNull('deleted_at'),
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
}
