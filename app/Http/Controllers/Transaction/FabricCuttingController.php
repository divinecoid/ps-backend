<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\FabricCuttingFabric;
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
                'status' => $data->status,
                'created_at' => $data->created_at,

                'fabric_count' => $data->fabric_detail->count(),

                'fabric_detail' => $data->fabric_detail->map(fn($fabric) => [
                    'fabric_id' => $fabric->fabric_id,
                    'quantity' => $fabric->quantity,
                    'sequence' => $fabric->cloth?->sequence,
                ])->values(),
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            FabricCutting::class,
            [
                'fabric_detail.cloth',
                'fabric_cutting_request_detail.model',
                'fabric_cutting_request_detail.size',
            ],
            [
                'serial_number',
                'fabric_detail.cloth.sequence',
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
        $request = FabricCutting::with([
            'fabric_detail.cloth',
            'fabric_cutting_request_detail.model',
            'fabric_cutting_request_detail.size',
        ])->findOrFail($id);
        return $this->successResponse([
            'serial_number' => $request->serial_number,
            'fabric_detail' => $request->fabric_detail
                ->map(function ($item) {
                    return [
                        'fabric_id' => $item->fabric_id,
                        'quantity' => $item->quantity,
                        'sequence' => $item->cloth->sequence
                    ];
                })
                ->values(),
            'request_detail' => $request->fabric_cutting_request_detail
                ->groupBy('model_id')
                ->map(function ($group) {
                    return [
                        'model_id' => $group->first()->model_id,
                        'variant_detail' => $group
                            ->map(function ($item) {
                                return [
                                    'size_id' => $item->size_id,
                                    'qty' => $item->req_qty,
                                ];
                            })
                            ->values(),
                    ];
                })
                ->values(),

            'receive_detail' => $request->fabric_cutting_request_detail
                ->groupBy('model_id')
                ->map(function ($group) {

                    return [
                        'model_id' => $group->first()->model_id,
                        'cloth_id' => '',
                        'cloth_detail' => [],

                        'variant_detail' => $group
                            ->map(function ($item) {

                                return [
                                    'size_id' => $item->size_id,
                                    'dozen_qty' => floor(
                                        $item->avl_qty / 12
                                    ),
                                    'piece_qty' => $item->avl_qty % 12,
                                ];
                            })
                            ->values(),
                    ];
                })
                ->values(),

            'status' => $request->status,
        ]);

    }


    public function update(Request $request, $id)
    {
        $data = $request->validate([
            'receive_detail' => 'required|array|min:1',
            'receive_detail.*.model_id' => 'required|uuid',
            'receive_detail.*.cloth_id' => 'required|uuid',
            'receive_detail.*.variant_detail' => 'required|array|min:1',
            'receive_detail.*.variant_detail.*.size_id' => 'required|uuid',
            'receive_detail.*.variant_detail.*.dozen_qty' => 'required|integer|min:0',
            'receive_detail.*.variant_detail.*.piece_qty' => 'required|integer|min:0|max:11',
        ]);

        return DB::transaction(function () use ($data, $id) {
            $fabricCutting = FabricCutting::findOrFail($id);

            foreach ($data['receive_detail'] as $detail) {
                foreach ($detail['variant_detail'] as $variant) {
                    $qty =
                        ($variant['dozen_qty'] * 12)
                        + $variant['piece_qty'];

                    FabricCuttingDetail::where([
                        'fabric_cutting_id' => $fabricCutting->id,
                        'model_id' => $detail['model_id'],
                        'size_id' => $variant['size_id'],
                    ])->update([
                                'avl_qty' => $qty,
                            ]);
                }
            }

            $allReceived = !FabricCuttingDetail::where(
                'fabric_cutting_id',
                $fabricCutting->id
            )
                ->whereColumn('avl_qty', '<', 'req_qty')
                ->exists();

            if ($allReceived) {
                $fabricCutting->update([
                    'status' => 'CLOSED',
                ]);
            }

            return $this->successResponse($data);
        });
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

        $cuttings = FabricCutting::query()
            ->whereHas('fabric_cutting_request_detail', function ($q) use ($modelId, $sizeId) {
                $q->where('model_id', $modelId)
                    ->where('size_id', $sizeId);
            })
            ->whereHas('clothes', function ($q) use ($colorId) {
                $q->where('color_id', $colorId);
            })
            ->with([
                'fabric_cutting_request_detail' => function ($q) use ($modelId, $sizeId) {
                    $q->where('model_id', $modelId)
                        ->where('size_id', $sizeId);
                }
            ])
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
                    ->whereIn(
                        'id',
                        collect($items)
                            ->pluck('model_id')
                            ->unique()
                    )
                    ->get()
                    ->keyBy('id');

                foreach ($items as &$item) {

                    $model = $models[$item['model_id']] ?? null;

                    if (!$model) {
                        return $this->errorResponse(
                            422,
                            "Model {$item['model_id']} not found"
                        );
                    }

                    $size = $model->sizes
                        ->firstWhere('id', $item['size_id']);

                    if (!$size) {
                        return $this->errorResponse(
                            422,
                            "Invalid size for model {$model->name}"
                        );
                    }
                }

                unset($item);

                return DB::transaction(function () use ($data, $items) {

                    $requestModel = FabricCutting::create([
                        'serial_number' => $data['serial_number'],
                        'status' => 'OPEN',
                    ]);

                    $fabricDetails = [];

                    foreach ($data['fabric_detail'] as $fabric) {

                        $cloth = Cloth::findOrFail(
                            $fabric['fabric_id']
                        );

                        $affected = Cloth::whereKey($cloth->id)
                            ->where(
                                'quantity',
                                '>=',
                                $fabric['quantity']
                            )
                            ->decrement(
                                'quantity',
                                $fabric['quantity']
                            );

                        if ($affected === 0) {

                            $currentQty = Cloth::find(
                                $cloth->id
                            )?->quantity ?? 0;

                            throw new \Illuminate\Http\Exceptions\HttpResponseException(
                                $this->errorResponse(
                                    400,
                                    "Quantity kain tinggal {$currentQty}"
                                )
                            );
                        }

                        $fabricDetails[] = [
                            'id' => (string) Str::uuid(),
                            'fabric_cutting_id' => $requestModel->id,
                            'fabric_id' => $fabric['fabric_id'],
                            'quantity' => $fabric['quantity'],
                        ];
                    }

                    FabricCuttingFabric::insert(
                        $fabricDetails
                    );

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

                    FabricCuttingDetail::insert(
                        $details
                    );

                    return $this->successResponse(
                        $requestModel
                    );
                });
            }
        );
    }

    public function destroy($id)
    {
        return $this->baseDelete(
            FabricCutting::class,
            $id
        );
    }

    public function multiDestroy(Request $request)
    {
        return $this->baseDelete(
            FabricCutting::class,
            $request->all()
        );
    }

    public function setReceived($id)
    {
        DB::transaction(function () use ($id) {
            FabricCuttingDetail::where('fabric_cutting_id', $id)
                ->update([
                    'avl_qty' => DB::raw('req_qty'),
                ]);

            FabricCutting::where('id', $id)
                ->update([
                    'status' => 'CLOSED',
                ]);
        });
    }

}
