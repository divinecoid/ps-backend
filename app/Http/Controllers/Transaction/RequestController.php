<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\ProductModel;
use App\Models\Transactions\RequestDetail;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RequestController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'cmt_id' => $data->cmt_id,
            'serial_number' => $data->serial_number,
            'cmt' => $data->cmt,
            'created_at' => $data->created_at,
            'status' => $data->status,
            'request_detail' => $data->request_detail->map(fn($detail) => [
                'req_dozen_qty' => floor($detail->req_qty / 12),
                'req_piece_qty' => $detail->req_qty % 12,
                'rec_dozen_qty' => floor($detail->rec_qty / 12),
                'rec_piece_qty' => $detail->rec_qty % 12,
                'rec_bs_qty' => $detail->rec_bs_qty,
                'model_id' => $detail->model_id,
                'models' => $detail->model,
                'cutting_id' => $detail->cloth_id,
                'cutting' => $detail->cutting,
                'barcode' => $detail->barcode
            ]),
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            \App\Models\Transactions\Request::class,
            [
                'cmt',
                'request_detail',
                'request_detail.cutting',
            ],
            [
                'cmt.code',
                'cmt.name',
                'serial_number',
                'request_detail.model.sku',
                'request_detail.model.name',
                'request_detail.cutting.cloth.color.code',
                'request_detail.cutting.cloth.color.name',
                'request_detail.size.code',
                'request_detail.size.name',
                'request_detail.barcode'
            ],
            $this->structure()
        );
    }


    public function show($id)
    {
        $request = \App\Models\Transactions\Request::with(['request_detail', 'request_detail.receivedlog_detail.receivedlog.warehouse', 'request_detail.receivedlog_detail.receivedlog'])->findOrFail($id);
        return $this->successResponse(
            [
                'cmt_id' => $request->cmt_id,
                'serial_number' => $request->serial_number,
                'status' => $request->status,
                'request_detail' => $request->request_detail
                    ->groupBy(fn($item) => $item->model_id . '|' . $item->cloth_id)
                    ->map(function ($group) {
                        $first = $group->first();
                        return [
                            'model_id' => $first->model_id,
                            'cloth_id' => $first->cloth_id,
                            'variant_detail' => $group->map(function ($item) {
                                return [
                                    'size_id' => $item->size_id,
                                    'dozen_qty' => floor($item->req_qty / 12),
                                    'piece_qty' => $item->req_qty % 12,
                                ];
                            })->values(),
                        ];
                    })
                    ->values(),
                'receive_log' => $request->request_detail->flatMap(function ($detail) {
                    return $detail->receivedlog_detail
                        ->map(fn($rd) => $rd->receivedlog);
                })->filter()->unique('id')->values()->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'request_id' => $log->request_id,
                        'warehouse_id' => $log->warehouse_id,
                        'warehouse' => (object) [
                            'name' => $log->warehouse?->name
                        ],
                        'user_id' => $log->user_id,
                        'user' => (object) [
                            'name' => $log->user?->name
                        ],
                        'received_date' => $log->received_date,
                        'notes' => $log->notes,
                        'created_at' => $log->created_at,
                        'updated_at' => $log->updated_at,
                        'details' => $log->details->map(function ($d) {
                            return [
                                'model_id' => $d->model_id,
                                'model' => (object) [
                                    'name' => $d->model?->name
                                ],
                                'color_id' => $d->color_id,
                                'color' => (object) [
                                    'name' => $d->color?->name
                                ],
                                'size_id' => $d->size_id,
                                'size' => (object) [
                                    'name' => $d->size?->name
                                ],
                                'qty' => $d->qty,
                                'barcode' => $d->barcode
                            ];
                        })->values()
                    ];
                })

            ]
        );
    }

    public function barcode($id)
    {
        return $this->baseShow(
            \App\Models\Transactions\Request::class,
            $id,
            ['request_detail'],
            fn($data) => [
                'request_detail' => $data->request_detail->map(fn($detail) => [
                    'req_dozen_qty' => floor($detail->req_qty / 12),
                    'req_piece_qty' => $detail->req_qty % 12,
                    'serial_number' => $data->serial_number,
                    'cutting' => $detail->cutting->clothes->color->code ?? null,
                    'sizes' => $detail->size->code,
                    'barcode' => $detail->barcode,
                ]),
            ]
        );
    }

    public function searchCmt(Request $request)
    {
        $request->validate([
            'model_id' => 'required|uuid',
            'color_id' => 'required|uuid',
            'size_id' => 'required|uuid',
        ]);

        $modelId = $request->model_id;
        $colorId = $request->color_id;
        $sizeId = $request->size_id;

        $requests = \App\Models\Transactions\Request::with(['cmt'])
            ->whereHas('request_detail', function ($q) use ($modelId, $sizeId, $colorId) {
                $q->where('model_id', $modelId)
                  ->where('size_id', $sizeId)
                  ->whereRaw('(req_qty - rec_qty - rec_bs_qty) > 0')
                  ->whereHas('cutting.clothes', function ($q2) use ($colorId) {
                      $q2->where('color_id', $colorId);
                  });
            })
            ->with(['request_detail' => function ($q) use ($modelId, $sizeId, $colorId) {
                $q->where('model_id', $modelId)
                  ->where('size_id', $sizeId)
                  ->whereRaw('(req_qty - rec_qty - rec_bs_qty) > 0')
                  ->whereHas('cutting.clothes', function ($q2) use ($colorId) {
                      $q2->where('color_id', $colorId);
                  });
            }])
            ->get();

        $result = $requests->map(function ($req) {
            $totalSisaQty = $req->request_detail->sum(function ($detail) {
                return $detail->req_qty - $detail->rec_qty - $detail->rec_bs_qty;
            });
            $totalReqQty = $req->request_detail->sum('req_qty');
            
            return [
                'request_id' => $req->id,
                'serial_number' => $req->serial_number,
                'cmt_name' => $req->cmt->name ?? null,
                'sisa_qty' => $totalSisaQty,
                'req_qty' => $totalReqQty,
            ];
        });

        return $this->successResponse($result);
    }

    public function store(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'cmt_id' => 'required|uuid|exists:mdx_cmts,id',
                'serial_number' => 'required|string|max:255',
                'request_detail' => 'required|array|min:1',
                'request_detail.*.model_id' => 'required|uuid',
                'request_detail.*.cloth_id' => 'required|uuid',
                'request_detail.*.variant_detail' => 'required|array|min:1',
                'request_detail.*.variant_detail.*.size_id' => 'required|uuid',
                'request_detail.*.variant_detail.*.dozen_qty' => 'required|integer|min:0',
                'request_detail.*.variant_detail.*.piece_qty' => 'required|integer|min:0',
            ],
            function ($data) {
                $items = [];
                foreach ($data['request_detail'] as $detail) {
                    foreach ($detail['variant_detail'] as $variant) {
                        $items[] = [
                            'model_id' => $detail['model_id'],
                            'cloth_id' => $detail['cloth_id'],
                            'size_id' => $variant['size_id'],
                            'req_qty' => ($variant['dozen_qty'] * 12) + $variant['piece_qty'],
                        ];
                    }
                }
                $cmt = CMT::find($data['cmt_id']);
                $models = ProductModel::with(['colors', 'sizes'])
                    ->whereIn('id', collect($items)->pluck('model_id')->unique())
                    ->get()
                    ->keyBy('id');
                //$clothes = \App\Models\MasterData\Cloth::with('color')->whereIn('cloth_id', collect($items)->pluck('cloth_id')->unique())->get()->keyBy('id');
                $fabricCuttings = \App\Models\Transactions\FabricCutting::with([
                    'clothes.color',
                    'fabric_cutting_request_detail',
                ])
                    ->whereIn('id', collect($items)->pluck('cloth_id')->unique())
                    ->get()
                    ->keyBy('id');
                foreach ($items as &$item) {
                    $model = $models[$item['model_id']] ?? null;
                    if (!$model) {
                        return $this->errorResponse(422, "Model {$item['model_id']} not found");
                    }
                    $fabricCutting = $fabricCuttings[$item['cloth_id']] ?? null;

                    if (!$fabricCutting) {
                        return $this->errorResponse(422, "Fabric cutting not found");
                    }
                    $cloth = $fabricCutting->clothes;

                    if (!$cloth) {
                        return $this->errorResponse(422, "Cloth not found");
                    }
                    $color = $model->colors->firstWhere('id', $cloth->color_id);
                    if (!$color) {
                        return $this->errorResponse(422, "Invalid color (from cloth) for model {$model->name}");
                    }
                    $size = $model->sizes->firstWhere('id', $item['size_id']);
                    if (!$size) {
                        return $this->errorResponse(422, "Invalid size for model {$model->name}");
                    }
                    $item['model'] = $model;
                    $item['cloth'] = $cloth;
                    $item['size'] = $size;
                }
                unset($item);
                return DB::transaction(function () use ($data, $items, $cmt, $fabricCuttings) {
                    // $serial = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT); //TODO: ganti dengan input dari proses potong baju
                    $requestModel = \App\Models\Transactions\Request::create([
                        'cmt_id' => $data['cmt_id'],
                        'serial_number' => $data['serial_number']
                        // 'serial_number' => $serial
                    ]);
                    $details = [];
                    foreach ($items as $item) {
                        $details[] = [
                            'id' => (string) Str::uuid(),
                            'request_id' => $requestModel->id,
                            'model_id' => $item['model_id'],
                            'cloth_id' => $item['cloth_id'],
                            'size_id' => $item['size_id'],
                            'req_qty' => $item['req_qty'],
                            'rec_qty' => 0,
                            'barcode' => implode('|', [
                                $cmt->code,
                                // now()->format('YmdHis'),
                                // $serial,
                                $data['serial_number'],
                                $item['model']->sku,
                                $item['cloth']->color->code,
                                $item['size']->code,
                            ]),
                        ];
                    }
                    foreach ($items as $item) {
                        $fabricCutting = $fabricCuttings[$item['cloth_id']];
                        $cuttingDetail = $fabricCutting
                            ->fabric_cutting_request_detail
                            ->firstWhere('size_id', $item['size_id']);
                        if (!$cuttingDetail) {
                            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                $this->errorResponse(422, "Ukuran tidak ditemukan pada fabric cutting.")
                            );
                        }
                        if ($cuttingDetail->avl_qty < $item['req_qty']) {
                            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                $this->errorResponse(422, "Stok ukuran {$cuttingDetail->size_id} tidak mencukupi.")
                            );
                        }
                        $cuttingDetail->avl_qty -= $item['req_qty'];
                        $cuttingDetail->save();
                    }
                    RequestDetail::insert($details);
                    return $this->successResponse($requestModel);
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

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            \App\Models\Transactions\Request::class,
            $request->all()
        );
    }
}
