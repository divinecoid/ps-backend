<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\Color;
use App\Models\MasterData\Size;
use DB;
use Illuminate\Http\Request;

class RequestController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'cmt_id' => $data->cmt_id,
            'cmt' => $data->cmt,
            'created_date' => $data->created_at,
            'status' => $data->status,
            'request_detail' => $data->request_detail->map(fn($detail) => [
                'req_dozen_qty' => $detail->req_dozen_qty,
                'req_piece_qty' => $detail->req_piece_qty,
                'rec_dozen_qty' => $detail->rec_dozen_qty,
                'rec_piece_qty' => $detail->rec_piece_qty,
                'rec_bs_qty' => $detail->rec_bs_qty,
                'model_id' => $detail->model_id,
                'models' => [
                    'name' => $detail->model?->name,
                ],
                'color_id' => $detail->color_id,
                'colors' => [
                    'name' => $detail->color?->name,
                ],
            ]),
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
        $request = \App\Models\Transactions\Request::with(['request_detail'])->findOrFail($id);
        return $this->successResponse(
            [
                'cmt_id' => $request->cmt_id,
                'request_detail' => $request->request_detail
                    ->groupBy(fn($item) => $item->model_id . '|' . $item->color_id)
                    ->map(function ($group) {
                        $first = $group->first();
                        return [
                            'model_id' => $first->model_id,
                            'color_id' => $first->color_id,
                            'variant_detail' => $group->map(function ($item) {
                                return [
                                    'size_id' => $item->size_id,
                                    'dozen_qty' => $item->req_dozen_qty,
                                    'piece_qty' => $item->req_piece_qty,
                                ];
                            })->values(),
                        ];
                    })
                    ->values(),
            ]
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
            function ($data) {
                return DB::transaction(function () use ($data) {
                    $requestModel = \App\Models\Transactions\Request::create([
                        'cmt_id' => $data['cmt_id']
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
                    $cmt = CMT::find($data['cmt_id']);
                    $models = \App\Models\MasterData\ProductModel::with([
                        'colors:id',
                        'sizes:id'
                    ])
                        ->whereIn('id', $modelIds)
                        ->get()
                        ->keyBy('id');

                    foreach ($items as $item) {
                        $model = $models[$item['model_id']] ?? null;
                        $color = Color::find($item['color_id']) ?? null;
                        $size = Size::find($item['size_id']) ?? null;
                        if (!$model) {
                            return $this->errorResponse(422, "Model {$item['model_id']} not found");
                        }
                        if (!$model->colors->contains('id', $item['color_id'])) {
                            return $this->errorResponse(422, "Color {$color->name} not valid for model {$model->name}");
                        }
                        if (!$model->sizes->contains('id', $item['size_id'])) {
                            return $this->errorResponse(422, "Size {$size->name} not valid for model {$model->name}");
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
                            'barcode' => $cmt->code . '|' . now() . '|' . $model->code . '|' . $color->code . '|' . $size->code, //TODO: generate barcode
                        ];
                    }
                    \App\Models\Transactions\RequestDetail::insert($details);
                    return $requestModel;
                });
            }
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            \App\Models\Transactions\Request::class,
            $id
        );
    }
}