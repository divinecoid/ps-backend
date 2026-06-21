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
            'fabric_id' => $data->fabric_id,
            'serial_number' => $data->serial_number,
            'fabric' => $data->clothes,
            'quantity' => $data->quantity,
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
                'clothes',
                'fabric_cutting_request_detail'
            ],
            [
                'clothes.sequence',
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
        $request = \App\Models\Transactions\FabricCutting::with(['fabric_cutting_request_detail'])->findOrFail($id);
        return $this->successResponse(
            [
                'fabric_id' => $request->fabric_id,
                'quantity' => $request->quantity,
                'serial_number' => $request->serial_number,
                'status' => $request->status,
                'request_detail' => $request->fabric_cutting_request_detail
                    ->groupBy(fn($item) => $item->model_id)
                    ->map(function ($group) {
                        $first = $group->first();
                        return [
                            'model_id' => $first->model_id,
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
            ->whereHas('clothes', function ($q) use ($colorId) {
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
                'fabric_id' => 'required|uuid|exists:mdx_clothes,id',
                'serial_number' => 'required|string|max:255',
                'quantity' => 'required|integer|min:1',
                'request_detail' => 'required|array|min:1',
                'request_detail.*.model_id' => 'required|uuid',
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
                            'size_id' => $variant['size_id'],
                            'req_qty' => ($variant['dozen_qty'] * 12) + $variant['piece_qty'],
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
                    // $serial = str_pad(random_int(0, 9999), 4, '0', STR_PAD_LEFT); //TODO: ganti dengan input dari proses potong baju
                    $requestModel = \App\Models\Transactions\FabricCutting::create([
                        'fabric_id' => $data['fabric_id'],
                        'quantity' => $data['quantity'],
                        'serial_number' => $data['serial_number']
                        // 'serial_number' => $serial
                    ]);
                    $cloth = Cloth::findOrFail($data['fabric_id']);

                    $affected = Cloth::whereKey($cloth->id)
                        ->where('quantity', '>=', $data['quantity'])
                        ->decrement('quantity', $data['quantity']);

                    if ($affected === 0) {
                        throw new \Illuminate\Http\Exceptions\HttpResponseException(
                            $this->errorResponse(400, "Quantity kain tinggal {$cloth->quantity}")
                        );
                    }
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
