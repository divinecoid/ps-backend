<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Cloth;
use App\Models\MasterData\ProductModel;
use App\Models\Transactions\FabricCutting;
use App\Models\Transactions\FabricCuttingDetail;
use DB;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class FabricCuttingController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'serial_number' => $data->serial_number,
            'created_at' => $data->created_at,
            'status' => $data->status,
            'request_detail' => $data->fabric_cutting_request_detail->map(fn($detail) => [
                'req_dozen_qty' => floor($detail->req_qty / 12),
                'req_piece_qty' => $detail->req_qty % 12,
                'rec_dozen_qty' => floor($detail->avl_qty / 12),
                'rec_piece_qty' => $detail->avl_qty % 12,
                'model_id' => $detail->model_id,
                'models' => $detail->model
            ]),
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            \App\Models\Transactions\FabricCutting::class,
            [
                'fabric_cutting_clothes.fabric',
                'fabric_cutting_request_detail'
            ],
            [
                'serial_number',
                'fabric_cutting_request_detail.model.sku',
                'fabric_cutting_request_detail.model.name',
                'fabric_cutting_request_detail.size.code',
                'fabric_cutting_request_detail.size.name',
            ],
            $this->structure()
        );
    }

    public function show($id)
    {
        $request = \App\Models\Transactions\FabricCutting::with([
            'fabric_cutting_request_detail',
            'fabric_cutting_clothes',
            'fabric_cutting_receives'
        ])->findOrFail($id);

        return $this->successResponse(
            [
                'id' => $request->id,
                'serial_number' => $request->serial_number,
                'status' => $request->status,
                'fabric_detail' => $request->fabric_cutting_clothes->map(function ($item) {
                    return [
                        'fabric_id' => $item->fabric_id,
                        'quantity' => $item->quantity,
                    ];
                }),
                'request_detail' => $request->fabric_cutting_request_detail
                    ->groupBy(fn($item) => $item->model_id)
                    ->map(function ($group) {
                        $first = $group->first();
                        return [
                            'model_id' => $first->model_id,
                            'variant_detail' => $group->map(function ($item) {
                                return [
                                    'size_id' => $item->size_id,
                                    'qty' => $item->req_qty,
                                ];
                            })->values(),
                        ];
                    })
                    ->values(),
                'receive_detail' => $request->fabric_cutting_receives->isEmpty() ?
                    $request->fabric_cutting_clothes->flatMap(function ($cloth) use ($request) {
                        return $request->fabric_cutting_request_detail
                            ->groupBy('model_id')
                            ->map(function ($details, $model_id) use ($cloth) {
                                return [
                                    'model_id' => $model_id,
                                    'cloth_id' => $cloth->fabric_id,
                                    'cloth_detail' => $details->map(function ($detail) {
                                        return [
                                            'size_id' => $detail->size_id,
                                            'avl_qty' => $detail->req_qty,
                                        ];
                                    })->values(),
                                    'variant_detail' => $details->map(function ($detail) {
                                        return [
                                            'size_id' => $detail->size_id,
                                            'dozen_qty' => 0,
                                            'piece_qty' => 0,
                                        ];
                                    })->values()
                                ];
                            })->values();
                    })->values()
                    :
                    $request->fabric_cutting_receives
                        ->groupBy(fn($item) => $item->model_id . '_' . $item->cloth_id)
                        ->map(function ($group) {
                            $first = $group->first();
                            return [
                                'model_id' => $first->model_id,
                                'cloth_id' => $first->cloth_id,
                                'cloth_detail' => [],
                                'variant_detail' => $group->map(function ($item) {
                                    return [
                                        'size_id' => $item->size_id,
                                        'dozen_qty' => floor($item->qty / 12),
                                        'piece_qty' => $item->qty % 12,
                                    ];
                                })->values(),
                            ];
                        })
                        ->values()
            ]
        );
    }

    public function searchCutting(Request $request)
    {
        $request->validate([
            'model_id' => 'required|uuid',
            'color_id' => 'required|uuid',
            'size_id' => 'required|uuid',
        ]);

        $modelId = $request->model_id;
        $colorId = $request->color_id;
        $sizeId = $request->size_id;

        $cuttings = \App\Models\Transactions\FabricCutting::query()
            ->whereHas('fabric_cutting_request_detail', function ($q) use ($modelId, $sizeId) {
                $q->where('model_id', $modelId)
                  ->where('size_id', $sizeId);
            })
            ->whereHas('fabric_cutting_clothes.fabric', function ($q) use ($colorId) {
                $q->where('color_id', $colorId);
            })
            ->with(['fabric_cutting_request_detail' => function ($q) use ($modelId, $sizeId) {
                $q->where('model_id', $modelId)
                  ->where('size_id', $sizeId);
            }])
            ->get();

        $result = $cuttings->map(function ($cut) {
            $totalAvlQty = $cut->fabric_cutting_request_detail->sum('avl_qty');
            $totalReqQty = $cut->fabric_cutting_request_detail->sum('req_qty');
            
            return [
                'fabric_cutting_id' => $cut->id,
                'serial_number' => $cut->serial_number,
                'status' => $cut->status,
                'avl_qty' => $totalAvlQty,
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
                'serial_number' => 'required|string|max:255',
                'fabric_detail' => 'required|array|min:1',
                'fabric_detail.*.fabric_id' => 'required|uuid|exists:mdx_clothes,id',
                'fabric_detail.*.quantity' => 'required|integer|min:1',
                'request_detail' => 'required|array|min:1',
                'request_detail.*.model_id' => 'required|uuid',
                'request_detail.*.variant_detail' => 'required|array|min:1',
                'request_detail.*.variant_detail.*.size_id' => 'required|uuid',
                'request_detail.*.variant_detail.*.qty' => 'required|integer|min:0',
            ],
            function ($data) {
                $items = [];
                foreach ($data['request_detail'] as $detail) {
                    foreach ($detail['variant_detail'] as $variant) {
                        $items[] = [
                            'model_id' => $detail['model_id'],
                            'size_id' => $variant['size_id'],
                            'req_qty' => $variant['qty'],
                        ];
                    }
                }
                $models = ProductModel::with(['sizes'])
                    ->whereIn('id', collect($items)->pluck('model_id')->unique())
                    ->get()
                    ->keyBy('id');
                foreach ($items as &$item) {
                    $model = $models[$item['model_id']] ?? null;
                    if (!$model) {
                        return $this->errorResponse(422, "Model {$item['model_id']} not found");
                    }

                    $size = $model->sizes->firstWhere('id', $item['size_id']);
                    if (!$size) {
                        return $this->errorResponse(422, "Invalid size for model {$model->name}");
                    }
                    $item['model'] = $model;
                    $item['size'] = $size;
                }
                unset($item);
                return DB::transaction(function () use ($data, $items) {
                    $requestModel = \App\Models\Transactions\FabricCutting::create([
                        'serial_number' => $data['serial_number']
                    ]);
                    
                    $fabricClothes = [];
                    foreach ($data['fabric_detail'] as $fabric) {
                        $cloth = Cloth::findOrFail($fabric['fabric_id']);

                        $affected = Cloth::whereKey($cloth->id)
                            ->where('quantity', '>=', $fabric['quantity'])
                            ->decrement('quantity', $fabric['quantity']);

                        if ($affected === 0) {
                            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                $this->errorResponse(400, "Quantity kain tinggal {$cloth->quantity}")
                            );
                        }

                        $fabricClothes[] = [
                            'id' => (string) Str::uuid(),
                            'fabric_cutting_id' => $requestModel->id,
                            'fabric_id' => $fabric['fabric_id'],
                            'quantity' => $fabric['quantity'],
                        ];
                    }
                    \App\Models\Transactions\FabricCuttingCloth::insert($fabricClothes);

                    $details = [];
                    foreach ($items as $item) {
                        $details[] = [
                            'id' => (string) Str::uuid(),
                            'fabric_cutting_id' => $requestModel->id,
                            'model_id' => $item['model_id'],
                            'size_id' => $item['size_id'],
                            'req_qty' => $item['req_qty'],
                            'avl_qty' => 0,
                        ];
                    }
                    FabricCuttingDetail::insert($details);
                    return $this->successResponse($requestModel);
                });
            }
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            \App\Models\Transactions\FabricCutting::class,
            $id
        );
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            \App\Models\Transactions\FabricCutting::class,
            $request->all()
        );
    }

    public function setReceived(Request $request, $id)
    {
        return $this->baseValidate(
            $request,
            [
                'receive_detail' => 'required|array|min:1',
                'receive_detail.*.model_id' => 'required|uuid',
                'receive_detail.*.cloth_id' => 'required|uuid',
                'receive_detail.*.variant_detail' => 'required|array|min:1',
                'receive_detail.*.variant_detail.*.size_id' => 'required|uuid',
                'receive_detail.*.variant_detail.*.dozen_qty' => 'required|integer|min:0',
                'receive_detail.*.variant_detail.*.piece_qty' => 'required|integer|min:0',
            ],
            function ($data) use ($id) {
                return DB::transaction(function () use ($data, $id) {
                    $receives = [];
                    foreach ($data['receive_detail'] as $detail) {
                        foreach ($detail['variant_detail'] as $variant) {
                            $total_piece = ($variant['dozen_qty'] * 12) + $variant['piece_qty'];
                            if ($total_piece > 0) {
                                $receives[] = [
                                    'id' => (string) Str::uuid(),
                                    'fabric_cutting_id' => $id,
                                    'model_id' => $detail['model_id'],
                                    'cloth_id' => $detail['cloth_id'],
                                    'size_id' => $variant['size_id'],
                                    'qty' => $total_piece,
                                ];
                            }
                        }
                    }
                    
                    \App\Models\Transactions\FabricCuttingReceive::where('fabric_cutting_id', $id)->delete();
                    if (!empty($receives)) {
                        \App\Models\Transactions\FabricCuttingReceive::insert($receives);
                    }

                    FabricCutting::where('id', $id)
                        ->update([
                            'status' => 'CLOSED',
                        ]);

                    return $this->successResponse(FabricCutting::find($id));
                });
            }
        );
    }

    public function getFabrics($id)
    {
        $cutting = \App\Models\Transactions\FabricCutting::with(['fabric_cutting_clothes.fabric.color', 'fabric_cutting_request_detail'])->findOrFail($id);
        
        $clothes = $cutting->fabric_cutting_clothes->map(function ($cloth_item) use ($cutting) {
            return [
                'id' => $cloth_item->fabric_id,
                'name' => ($cloth_item->fabric->color->name ?? '') . ' - ' . ($cloth_item->fabric->sequence ?? ''),
                'detail' => $cutting->fabric_cutting_request_detail->map(function ($detail) {
                    return [
                        'size_id' => $detail->size_id,
                        'avl_qty' => $detail->req_qty, 
                    ];
                })
            ];
        });

        return $this->successResponse($clothes);
    }
}
