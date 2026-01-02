<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use DB;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class RequestController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'cmt_id' => $data->cmt_id,
            'status' => $data->status,
            'request_detail' => (object) [
                'product_id' => $data->request_detail->product_id,
                'products' => (object) [
                    'name' => $data->request_detail->product->name
                ],
                'req_dozen_qty' => $data->request_detail->req_dozen_qty,
                'req_piece_qty' => $data->request_detail->req_piece_qty,
                'rec_dozen_qty' => $data->request_detail->rec_dozen_qty,
                'rec_piece_qty' => $data->request_detail->rec_piece_qty,
                'rec_bs_qty' => $data->request_detail->rec_bs_qty,
                'barcode' => $data->request_detail->barcode,
                'model_id' => $data->request_detail->model_id,
                'models' => (object) [
                    'name' => $data->request_detail->model->name
                ],
                'color_id' => $data->request_detail->color_id,
                'colors' => (object) [
                    'name' => $data->request_detail->color->name
                ],

            ]
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            \App\Models\Transactions\Request::class,
            [],
            ['code', 'name'],
            $this->structure()
        );
    }


    public function show($id)
    {
        return $this->baseShow(
            \App\Models\Transactions\Request::class,
            $id,
            ['product', 'color', 'model', 'size'],
            $this->structure()
        );
    }

    public function store(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'cmt_id' => 'required|uuid|exists:mdx_cmts,id',
                'request_detail' => 'required|array|min:1',
                'request_detail.*.model_id' => 'required|uuid',
                'request_detail.*.color_id' => 'required|uuid',
                'request_detail.*.variant_detail' => 'required|array|min:1',
                'request_detail.*.variant_detail.*.size_id' => 'required|uuid',
                'request_detail.*.variant_detail.*.dozen_qty' => 'required|integer|min:0',
                'request_detail.*.variant_detail.*.piece_qty' => 'required|integer|min:0',
            ],
            function ($data, Request $request) {
                return DB::transaction(function () use ($data) {
                    $requestModel = \App\Models\Transactions\Request::create([
                        'cmt_id' => $data['cmt_id'],
                        'request_date' => now(),
                        'status' => 'open'
                    ]);
                    $items = [];
                    foreach ($data['request_detail'] as $detail) {
                        foreach ($detail['variant_detail'] as $variant) {
                            $items[] = [
                                'model_id' => $detail['model_id'],
                                'color_id' => $detail['color_id'],
                                'size_id' => $variant['size_id'],
                                'req_dozen_qty' => $variant['dozen_qty'],
                                'req_piece_qty' => $variant['piece_qty'],
                            ];
                        }
                    }

                    $modelIds = collect($items)->pluck('model_id')->unique();

                    $models = \App\Models\MasterData\ProductModel::with([
                        'colors:id',
                        'sizes:id'
                    ])
                        ->whereIn('id', $modelIds)
                        ->get()
                        ->keyBy('id');

                    foreach ($items as $item) {
                        $model = $models[$item['model_id']] ?? null;

                        if (!$model) {
                            throw new \Exception(
                                "Model not found: {$item['model_id']}"
                            );
                        }

                        if (!$model->colors->contains('id', $item['color_id'])) {
                            throw new \Exception(
                                "Color {$item['color_id']} not valid for model {$item['model_id']}"
                            );
                        }

                        if (!$model->sizes->contains('id', $item['size_id'])) {
                            throw new \Exception(
                                "Size {$item['size_id']} not valid for model {$item['model_id']}"
                            );
                        }
                    }

                    $details = [];

                    foreach ($items as $item) {
                        $details[] = [
                            'id' => \Str::uuid(),
                            'request_id' => $requestModel->id,
                            'model_id' => $item['model_id'],
                            'color_id' => $item['color_id'],
                            'size_id' => $item['size_id'],
                            'req_dozen_qty' => $item['req_dozen_qty'],
                            'req_piece_qty' => $item['req_piece_qty'],
                            'rec_dozen_qty' => 0,
                            'rec_piece_qty' => 0,
                            'rec_bs_qty' => 0,
                            'created_at' => now(),
                            'updated_at' => now(),
                        ];
                    }

                    \App\Models\Transactions\RequestDetail::insert($details);
                    return $requestModel;
                });
            }
        );
    }


    public function update(Request $request, $id)
    {
        return $this->baseUpdate(
            $request,
            Request::class,
            $id,
            [
                'code' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('mdx_cmts', 'code')->ignore($id)
                ],
                'name' => 'required|string|max:255',
                'contact_person' => 'required|string|max:255',
                'phone' => 'required|string|max:255',
                'address' => 'required|string|max:255'
            ],
            null
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            Request::class,
            $id
        );
    }
}