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
            'series' => $data->series
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            Product::class,
            ['rack', 'model', 'color', 'size'],
            ['barcode'],
            $this->structure()
        );
    }

    public function master(Request $request)
    {
        return $this->baseMaster(
            $request,
            Product::class,
            ['rack', 'model', 'color', 'size'],
            ['barcode'],
            $this->structure()
        );
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
