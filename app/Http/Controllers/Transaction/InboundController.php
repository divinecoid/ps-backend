<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Inventory;
use App\Models\MasterData\Product;
use App\Models\Transactions\Receivedlog;
use App\Models\Transactions\ReceivedlogDetail;
use App\Models\Transactions\RequestDetail;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class InboundController extends Controller
{
    use CrudTrait;
    /*
        {
            "barcodes_dozen": [
                "CMT01|2200|LP|RED|XS|D|1",
                "CMT01|2200|LP|RED|XS|D|2"
            ],
            "warehouse_id": "019b85c4-c21c-7215-b374-47b05605d1b3",
            "barcodes_piece": [
                {
                    "barcode": "CMT01|2200|LP|RED|XS|P|1",
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
                },
                {
                    "barcode": "CMT01|2200|LP|RED|XS|P|2",
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
                },
                {
                    "barcode": "CMT01|2200|LP|RED|XS|P|3",
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
                }
            ],
            "barcodes_rejected": [
                "CMT01|2200|LP|RED|XS|D|1",
                "CMT01|2200|LP|RED|XS|D|2"
            ],
            "notes": "Received in good condition"
        }
     */
    private function structure()
    {
        return fn(Receivedlog $data) => [
            'id' => $data->id,
            'warehouse_id' => $data->warehouse_id,
            'warehouse' => (object) [
                'name' => $data->warehouse?->name
            ],
            'user' => (object) [
                'name' => $data->user?->name
            ],
            'received_date' => $data->received_date,
            'notes' => $data->notes,
            'request' => (object) [
                'cmt' => (object) [
                    'code' => $data->cmt?->code,
                    'name' => $data->cmt?->name,
                    'contact_person' => $data->cmt?->contact_person,
                    'phone' => $data->cmt?->phone,
                    'address' => $data->cmt?->address,
                ]
            ],
            'details' => $data->details->filter(fn($detail) => !$detail->is_rejected)->map(fn($detail) => [
                'category' => $detail->category ?? 'sewn',
                'barcode' => $detail->barcode,
                'model' => $detail->model?->name,
                'color' => $detail->color?->name,
                'size' => $detail->size?->name,
                'serial_number' => $detail->requestDetail?->request?->serial_number,
                'qty' => $detail->qty,
                'rack' => $detail->product?->rack?->code
            ]),
            'rejected_details' => $data->details->filter(fn($detail) => $detail->is_rejected)->map(fn($detail) => [
                'category' => $detail->category ?? 'sewn',
                'barcode' => $detail->barcode,
                'model' => $detail->model?->name,
                'color' => $detail->color?->name,
                'size' => $detail->size?->name,
                'serial_number' => $detail->requestDetail?->request?->serial_number,
                'qty' => $detail->qty,
                'rack' => $detail->product?->rack?->code
            ]),
            'summary' => $data->details->groupBy(function (ReceivedlogDetail $detail) {
                return $detail->model?->name . '|' .
                    $detail->color?->name . '|' .
                    $detail->size?->name . '|' .
                    $detail->requestDetail?->request?->serial_number . '|' .
                    ($detail->category ?? 'sewn') . '|' .
                    ($detail->is_rejected ? '1' : '0');
            })->map(function ($group) {
                $first = $group->first();
                return [
                    'model' => $first->model?->name,
                    'color' => $first->color?->name,
                    'size' => $first->size?->name,
                    'serial_number' => $first->requestDetail?->request?->serial_number,
                    'total_qty' => $group->sum('qty'),
                    'category' => $first->category ?? 'sewn',
                    'is_reject' => !!$first->is_rejected
                ];
            })->values()
        ];
    }
    public function store(HTTPRequest $request)
    {
        return $this->baseValidate(
            $request,
            [
                'warehouse_id' => ['nullable', Rule::exists('mdx_warehouses', 'id')->whereNull('deleted_at')],
                'barcodes_dozen' => 'required_without_all:barcodes_piece,barcodes_rejected,manual_items|array|min:1',
                'barcodes_dozen.*' => 'required|string|distinct',
                'barcodes_piece' => 'required_without_all:barcodes_dozen,barcodes_rejected,manual_items|array|min:1',
                'barcodes_piece.*.barcode' => 'required|string|distinct',
                'barcodes_piece.*.rack_id' => ['required', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
                'barcodes_rejected' => 'required_without_all:barcodes_dozen,barcodes_piece,manual_items|array|min:1',
                'barcodes_rejected.*' => 'required|string|distinct',
                // Items without a QR code, entered by quantity against a request line.
                'manual_items' => 'required_without_all:barcodes_dozen,barcodes_piece,barcodes_rejected|array|min:1',
                'manual_items.*.request_detail_id' => ['required', 'uuid', Rule::exists('trx_request_details', 'id')],
                'manual_items.*.category' => 'required|in:returned_good,returned_defect,extra',
                'manual_items.*.qty' => 'required|integer|min:1|max:100000',
                'notes' => 'nullable|string|max:1000',
            ],
            function ($data) {
                $invalidDozenBarcodes = [];
                $invalidPieceBarcodes = [];
                $invalidRejectedBarcodes = [];

                $scannedDozenBarcodes = [];
                $scannedPieceBarcodes = [];
                $scannedRejectedBarcodes = [];

                $conflictBarcodes = [];
                $invalidManualItems = [];
                try {

                    $barcodesDozen = $data['barcodes_dozen'] ?? [];
                    $barcodesPiece = $data['barcodes_piece'] ?? [];

                    $barcodesRejected = $data['barcodes_rejected'] ?? [];
                    $conflict = array_intersect($barcodesRejected, collect($barcodesPiece)->pluck('barcode')->toArray());
                    $conflictBarcodes = array_values($conflict);

                    // "barcodes_dozen": [
                    //     "CMT01|20260116173826|LP|RED|XS|D|1",
                    //     "CMT01|20260116173826|LP|RED|XS|D|7",
                    //     "CMT01|20260116173826|LP|RED|XS|D|2",
                    //     "CMT01|20260116173826|LP|RED|XS|D|13"
                    // ],
                    $requestsFound = false;
                    if ($barcodesDozen) {
                        foreach ($barcodesDozen as $barcode) {//harus dalam bentuk dozen semua
                            ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] = $this->parseBarcode($barcode);
                            if ($group == 'D') {
                                $requestDetail = $this->findRequestDetail($prefix);
                                if (!$requestDetail) {//cek jika barcode ditemukan di database
                                    $invalidDozenBarcodes[] = $barcode;
                                    continue;
                                }
                                $requestsFound = true;

                                if ($sequence * 12 > $requestDetail->req_qty) {//cek jika barcode group diluar jangkauan, jika group sekarang dikali 12 -> menjadi total piece, lebih besar dari kuantitas yang diminta atau sequence per group lebih dari 12
                                    $invalidDozenBarcodes[] = $barcode;
                                    continue;
                                }
                                if (
                                    ReceivedlogDetail::where('barcode', $barcode)->exists() //jika sudah pernah discan
                                ) {
                                    $scannedDozenBarcodes[] = $barcode;
                                }

                            } else {
                                //jika bukan group
                                $invalidDozenBarcodes[] = $barcode;
                                continue;
                            }
                        }
                    }
                    // "barcodes_piece": [
                    //     {
                    //         "barcode": "CMT01|20260116173826|LP|RED|XS|P|1",
                    //         "rack_id": "019b85c4-c21c-7215-b374-47b05605d1b3"
                    //     },
                    //     {
                    //         "barcode": "CMT01|20260116173826|LP|RED|XS|P|2",
                    //         "rack_id": "019b85c4-c21c-7215-b374-47b05605d1b3"
                    //     },
                    //     {
                    //         "barcode": "CMT01|20260116173826|LP|RED|XS|P|3",
                    //         "rack_id": "019b85c4-c21c-7215-b374-47b05605d1b3"
                    //     }
                    // ],
    
                    if ($barcodesPiece) {
                        foreach ($barcodesPiece as $items) {
                            $barcode = $items['barcode'];
                            ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] = $this->parseBarcode($barcode);
                            if ($group == 'P') {
                                $requestDetail = $this->findRequestDetail($prefix);
                                if (!$requestDetail) {//cek jika barcode ditemukan di database
                                    $invalidPieceBarcodes[] = $barcode;
                                    continue;
                                }

                                $requestsFound = true;

                                if ($this->rackSizeMismatch($items['rack_id'], $requestDetail->size_id)) {
                                    $invalidPieceBarcodes[] = $barcode;
                                    continue;
                                }

                                if ($sequence > $requestDetail->req_qty) {//jika (request tidak memiliki sisa piece) atau (request memiliki sisa piece dan sequence di luar dari range request piece)
                                    $invalidPieceBarcodes[] = $barcode;
                                    continue;
                                }
                                if (
                                    ReceivedlogDetail::where('barcode', $barcode)->exists() //jika sudah pernah discan
                                ) {
                                    $scannedPieceBarcodes[] = $barcode;
                                }
                            } else {
                                //jika bukan piece
                                $invalidPieceBarcodes[] = $barcode;
                                continue;
                            }
                        }
                    }

                    if ($barcodesRejected) {
                        foreach ($barcodesRejected as $barcode) {//harus dalam bentuk piece semua
                            ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] = $this->parseBarcode($barcode);
                            if ($group == 'P') {
                                $requestDetail = $this->findRequestDetail($prefix);
                                if (!$requestDetail) {//cek jika barcode ditemukan di database
                                    $invalidRejectedBarcodes[] = $barcode;
                                    continue;
                                }
                                $requestsFound = true;

                                if ($sequence > $requestDetail->req_qty) {//cek jika barcode piece diluar jangkauan, jika sequence, lebih besar dari kuantitas yang diminta
                                    $invalidRejectedBarcodes[] = $barcode;
                                    continue;
                                }
                                if (
                                    ReceivedlogDetail::where('barcode', $barcode)->exists()//jika sudah pernah discan
                                ) {
                                    $scannedRejectedBarcodes[] = $barcode;
                                }

                            } else {
                                //jika bukan piece
                                $invalidRejectedBarcodes[] = $barcode;
                                continue;
                            }
                        }
                    }
                    // Manual items (no QR): the request line must exist, and returned
                    // goods can't exceed what is still outstanding on that line.
                    $manualItems = $data['manual_items'] ?? [];
                    $returnedPerLine = [];
                    foreach ($manualItems as $i => $item) {
                        $line = RequestDetail::find($item['request_detail_id']);
                        if (!$line) {
                            $invalidManualItems[] = 'Baris ' . ($i + 1) . ': baris permintaan tidak ditemukan';
                            continue;
                        }
                        $requestsFound = true;
                        if ($item['category'] !== 'extra') {
                            $returnedPerLine[$line->id] = ($returnedPerLine[$line->id] ?? 0) + (int) $item['qty'];
                            $outstanding = $line->req_qty - $line->rec_qty - $line->rec_bs_qty;
                            if ($returnedPerLine[$line->id] > $outstanding) {
                                $invalidManualItems[] = 'Baris ' . ($i + 1) . ": jumlah kembali ({$returnedPerLine[$line->id]}) melebihi sisa permintaan ({$outstanding})";
                            }
                        }
                    }

                    $totalInvalid = count($invalidDozenBarcodes) + count($invalidPieceBarcodes) + count($scannedDozenBarcodes) + count($scannedPieceBarcodes) + count($invalidRejectedBarcodes) + count($scannedRejectedBarcodes) + count($invalidManualItems);
                    $totalConflict = count($conflictBarcodes);
                    if ($totalInvalid == 0 && $requestsFound && $totalConflict == 0) {//jika semuanya lolos validasi
                        DB::transaction(function () use ($data, $barcodesDozen, $barcodesPiece, $barcodesRejected, $manualItems) {
                            $groupedReceivedLogs = [];
                            $requestsToComplete = [];

                            $warehouseId = $data['warehouse_id'] ?? null;
                            if (!$warehouseId) {
                                if (count($barcodesDozen) > 0) {
                                    ['prefix' => $prefix] = $this->parseBarcode($barcodesDozen[0]);
                                    if ($rd = $this->findRequestDetail($prefix)) {
                                        $recRack = \App\Models\MasterData\Rack::whereHas('warehouse', function ($q) {
                                            $q->where('type', 'BESAR');
                                        })
                                            ->where('model_id', $rd->model_id)
                                            ->where('color_id', $rd->color_id)
                                            ->first();
                                        if (!$recRack) {
                                            $recRack = \App\Models\MasterData\Rack::whereHas('warehouse', function ($q) {
                                                $q->where('type', 'BESAR');
                                            })
                                                ->where('model_id', $rd->model_id)
                                                ->whereNull('color_id')
                                                ->first();
                                        }
                                        if (!$recRack) {
                                            $recRack = \App\Models\MasterData\Rack::whereHas('warehouse', function ($q) {
                                                $q->where('type', 'BESAR');
                                            })
                                                ->whereNull('model_id')
                                                ->whereNull('color_id')
                                                ->first();
                                        }
                                        if ($recRack) {
                                            $warehouseId = $recRack->warehouse_id;
                                        }
                                    }
                                }
                                if (!$warehouseId && count($barcodesPiece) > 0) {
                                    $warehouseId = \App\Models\MasterData\Rack::where('id', $barcodesPiece[0]['rack_id'])->value('warehouse_id');
                                }
                            }

                            foreach ($barcodesDozen as $barcode) {
                                ['prefix' => $prefix] = $this->parseBarcode($barcode);
                                if ($rd = $this->findRequestDetail($prefix)) {
                                    $req = $rd->request;
                                    $cmtId = $req->cmt_id;
                                    $requestsToComplete[$req->id] = $req;

                                    if (!isset($groupedReceivedLogs[$cmtId])) {
                                        $groupedReceivedLogs[$cmtId] = Receivedlog::create([
                                            'cmt_id' => $cmtId,
                                            'warehouse_id' => $warehouseId,
                                            'user_id' => Auth::id(),
                                            'received_date' => now(),
                                            'notes' => $data['notes'] ?? null
                                        ]);
                                    }

                                    $this->createReceivedDetail(
                                        $groupedReceivedLogs[$cmtId],
                                        $rd,
                                        $barcode,
                                        12
                                    );
                                }
                            }

                            foreach ($barcodesPiece as $items) {
                                $barcode = $items['barcode'];
                                ['prefix' => $prefix] = $this->parseBarcode($barcode);
                                $series = $this->getSeries($prefix);
                                if ($rd = $this->findRequestDetail($prefix)) {
                                    $req = $rd->request;
                                    $cmtId = $req->cmt_id;
                                    $requestsToComplete[$req->id] = $req;

                                    if (!isset($groupedReceivedLogs[$cmtId])) {
                                        $groupedReceivedLogs[$cmtId] = Receivedlog::create([
                                            'cmt_id' => $cmtId,
                                            'warehouse_id' => $warehouseId,
                                            'user_id' => Auth::id(),
                                            'received_date' => now(),
                                            'notes' => $data['notes'] ?? null
                                        ]);
                                    }

                                    $this->createReceivedDetail(
                                        $groupedReceivedLogs[$cmtId],
                                        $rd,
                                        $barcode,
                                        1
                                    );
                                    $createdProduct = Product::create([
                                        'rack_id' => $items['rack_id'],
                                        'model_id' => $rd->model_id,
                                        'color_id' => $rd->cutting->clothes->color_id,
                                        'size_id' => $rd->size_id,
                                        'series' => $series,
                                        'barcode' => $barcode
                                    ]);

                                    (new \App\Services\ProductCostingService())
                                        ->calculateForProduct($createdProduct, $rd);
                                }
                            }

                            foreach ($barcodesRejected as $barcode) {
                                ['prefix' => $prefix] = $this->parseBarcode($barcode);
                                if ($rd = $this->findRequestDetail($prefix)) {
                                    $req = $rd->request;
                                    $cmtId = $req->cmt_id;
                                    $requestsToComplete[$req->id] = $req;

                                    if (!isset($groupedReceivedLogs[$cmtId])) {
                                        $groupedReceivedLogs[$cmtId] = Receivedlog::create([
                                            'cmt_id' => $cmtId,
                                            'warehouse_id' => $warehouseId,
                                            'user_id' => Auth::id(),
                                            'received_date' => now(),
                                            'notes' => $data['notes'] ?? null
                                        ]);
                                    }

                                    $this->createReceivedDetail(
                                        $groupedReceivedLogs[$cmtId],
                                        $rd,
                                        $barcode,
                                        1,
                                        true
                                    );
                                }
                            }

                            foreach ($manualItems as $item) {
                                $rd = RequestDetail::with(['request.cmt', 'model', 'cutting.clothes.color', 'size'])->find($item['request_detail_id']);
                                $req = $rd->request;
                                $cmtId = $req->cmt_id;
                                $requestsToComplete[$req->id] = $req;

                                if (!isset($groupedReceivedLogs[$cmtId])) {
                                    $groupedReceivedLogs[$cmtId] = Receivedlog::create([
                                        'cmt_id' => $cmtId,
                                        'warehouse_id' => $warehouseId,
                                        'user_id' => Auth::id(),
                                        'received_date' => now(),
                                        'notes' => $data['notes'] ?? null
                                    ]);
                                }

                                $this->createManualReceivedDetail(
                                    $groupedReceivedLogs[$cmtId],
                                    $rd,
                                    $item['category'],
                                    (int) $item['qty']
                                );
                            }

                            foreach ($requestsToComplete as $reqId => $req) {
                                if ($req->isCompleted()) {
                                    $req->update(['status' => 'CLOSED']);
                                }
                            }
                        });
                        $totalScanned = count($barcodesDozen) + count($barcodesPiece) + count($barcodesRejected) + count($manualItems);

                        return $this->successResponse([
                            'total_scanned' => $totalScanned,
                        ], "Successfully processed {$totalScanned} items");
                    } else {
                        return $this->errorResponse(422, $totalInvalid . ' barcode/item tidak valid, ' . $totalConflict . ' barcode konflik' . ($invalidManualItems ? ' — ' . implode('; ', $invalidManualItems) : ''), [
                            'invalid' => [
                                'barcodes_dozen' => $invalidDozenBarcodes,
                                'barcodes_piece' => $invalidPieceBarcodes,
                                'barcodes_rejected' => $invalidRejectedBarcodes,
                                'manual_items' => $invalidManualItems,
                            ],
                            'scanned' => [
                                'barcodes_dozen' => $scannedDozenBarcodes,
                                'barcode_piece' => $scannedPieceBarcodes,
                                'barcode_rejected' => $scannedRejectedBarcodes,
                            ],
                            'conflict' => $conflictBarcodes
                        ]);
                    }

                } catch (\Exception $e) {
                    return $this->errorResponse(500, $e->getMessage() . " Line: " . $e->getLine());
                }
            }
        );
    }
    public function validate(HttpRequest $request)
    {
        return $this->baseValidate(
            $request,
            [
                'barcode' => 'required|string',
            ],
            function ($data) {
                $barcode = $data['barcode'];
                ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] = $this->parseBarcode($barcode);

                $requestDetail = $this->findRequestDetail($prefix);
                if (!$requestDetail) {//cek jika barcode ditemukan di database
                    return $this->errorResponse(422, 'Barcode tidak valid');
                }
                //jika group dan total item dalam group lebih besar daripada yang diterima atau sequence lebih besar daripada 12
                //cek jika barcode group diluar jangkauan, jika group sekarang dikali 12 -> menjadi total piece, lebih besar dari kuantitas yang diminta atau sequence per group lebih dari 12
                if ($group == 'D') {
                    if ($sequence * 12 > $requestDetail->req_qty) {
                        return $this->errorResponse(422, 'Nomor urut barcode lusin diluar jangkauan');
                    }
                } else if ($group == 'P') {
                    if ($sequence > $requestDetail->req_qty) {
                        return $this->errorResponse(422, 'Nomor urut barcode piece diluar jangkauan');
                    }
                } else {
                    return $this->errorResponse(422, 'Barcode tidak valid');
                }
                if (
                    ReceivedlogDetail::where('barcode', $barcode)->exists()//jika sudah pernah discan
                ) {
                    return $this->errorResponse(422, 'Barcode sudah discan');
                }

                $cmt = $requestDetail->request->cmt;
                $model = $requestDetail->model;
                $color = $requestDetail->cutting->clothes->color;
                $size = $requestDetail->size;

                $recommendedRack = null;
                if ($group === 'P') {
                    $recommendedRack = $this->findSmallRack(
                        $requestDetail->model_id,
                        $requestDetail->cutting->clothes->color_id,
                        $requestDetail->size_id
                    );
                } else if ($group === 'D') {
                    $modelId = $requestDetail->model_id;
                    $colorId = $requestDetail->cutting->clothes->color_id;

                    $recommendedRack = \App\Models\MasterData\Rack::with('warehouse')
                        ->whereHas('warehouse', function ($q) {
                            $q->where('type', 'BESAR');
                        })
                        ->where('model_id', $modelId)
                        ->where('color_id', $colorId)
                        ->first();

                    if (!$recommendedRack) {
                        $recommendedRack = \App\Models\MasterData\Rack::with('warehouse')
                            ->whereHas('warehouse', function ($q) {
                                $q->where('type', 'BESAR');
                            })
                            ->where('model_id', $modelId)
                            ->whereNull('color_id')
                            ->first();
                    }

                    if (!$recommendedRack) {
                        $recommendedRack = \App\Models\MasterData\Rack::with('warehouse')
                            ->whereHas('warehouse', function ($q) {
                                $q->where('type', 'BESAR');
                            })
                            ->whereNull('model_id')
                            ->whereNull('color_id')
                            ->first();
                    }
                }

                return $this->successResponse([
                    'cmt' => (object) [
                        'code' => $cmt->code,
                        'name' => $cmt->name,
                        'contact_person' => $cmt->contact_person,
                        'phone' => $cmt->phone,
                        'address' => $cmt->address,
                    ],
                    'model' => (object) [
                        'sku' => $model->sku,
                        'name' => $model->name,
                    ],
                    'color' => (object) [
                        'code' => $color->code,
                        'name' => $color->name,
                    ],
                    'size' => (object) [
                        'code' => $size->code,
                        'name' => $size->name,
                    ],
                    'is_dozen' => $group === 'D' ? true : false,
                    'rack' => $recommendedRack ? (object) [
                        'id' => $recommendedRack->id,
                        'code' => $recommendedRack->code,
                        'name' => $recommendedRack->name,
                        'warehouse' => (object) [
                            'id' => $recommendedRack->warehouse->id,
                            'name' => $recommendedRack->warehouse->name
                        ]
                    ] : null,
                ]);
            }
        );
    }
    /**
     * Recommended rack in the small (KECIL) warehouse. Racks there are per size:
     * a piece may only go to a rack of its own size, or to a rack that is not
     * tied to any size. Most specific model/color match wins.
     */
    private function findSmallRack($modelId, $colorId, $sizeId)
    {
        $base = fn() => \App\Models\MasterData\Rack::with('warehouse')
            ->whereHas('warehouse', fn($q) => $q->where('type', 'KECIL'))
            ->where(fn($q) => $q->where('size_id', $sizeId)->orWhereNull('size_id'))
            // exact size first, size-less racks only as fallback
            ->orderByRaw('size_id IS NULL');

        return $base()->where('model_id', $modelId)->where('color_id', $colorId)->first()
            ?? $base()->where('model_id', $modelId)->whereNull('color_id')->first()
            ?? $base()->whereNull('model_id')->whereNull('color_id')->first();
    }

    /** A piece can't be stored in a small-warehouse rack of a different size. */
    private function rackSizeMismatch($rackId, $sizeId): bool
    {
        $rack = \App\Models\MasterData\Rack::with('warehouse')->find($rackId);
        return $rack
            && $rack->warehouse?->type === 'KECIL'
            && $rack->size_id !== null
            && $rack->size_id !== $sizeId;
    }

    private function parseBarcode(string $barcode)
    {
        $parts = explode('|', $barcode);
        return [
            'prefix' => implode('|', array_slice($parts, 0, -2)),
            'group' => $parts[count($parts) - 2] ?? null,
            'sequence' => $parts[count($parts) - 1] ?? null,
        ];
    }
    private function findRequestDetail(string $prefix)
    {
        return RequestDetail::with(['request.cmt', 'model', 'cutting.clothes.color', 'size'])->where('barcode', $prefix)->first();
    }
    private function createReceivedDetail($receivedLog, $requestDetail, string $barcode, int $qty, bool $is_rejected = false)
    {
        ['prefix' => $prefix, 'group' => $group] = $this->parseBarcode($barcode);
        $series = $this->getSeries($prefix);
        ReceivedlogDetail::create([
            'receivedlog_id' => $receivedLog->id,
            'request_detail_id' => $requestDetail->id,
            'model_id' => $requestDetail->model_id,
            'color_id' => $requestDetail->cutting->clothes->color_id,
            'size_id' => $requestDetail->size_id,
            'qty' => $qty,
            'barcode' => $barcode,
            'is_rejected' => $is_rejected
        ]);
        $requestDetail->increment('rec_qty', $qty);
        if (!$is_rejected && $group == 'D') {
            $this->addToBigInventory($requestDetail, $series, $qty);
        }
    }

    /**
     * Adds sewn pieces to the big-warehouse inventory (model/color/size, per
     * series), assigning the default BESAR rack on first use.
     */
    private function addToBigInventory($requestDetail, ?string $series, int $qty): void
    {
        $inventory = Inventory::firstOrCreate([
            'model_id' => $requestDetail->model_id,
            'color_id' => $requestDetail->cutting->clothes->color_id,
            'size_id' => $requestDetail->size_id,
        ]);
        if ($inventory->rack_id === null) {
            $recRack = \App\Models\MasterData\Rack::whereHas('warehouse', function ($q) {
                $q->where('type', 'BESAR');
            })
                ->where('model_id', $requestDetail->model_id)
                ->where('color_id', $requestDetail->cutting->clothes->color_id)
                ->first();
            if (!$recRack) {
                $recRack = \App\Models\MasterData\Rack::whereHas('warehouse', function ($q) {
                    $q->where('type', 'BESAR');
                })
                    ->where('model_id', $requestDetail->model_id)
                    ->whereNull('color_id')
                    ->first();
            }
            if (!$recRack) {
                $recRack = \App\Models\MasterData\Rack::whereHas('warehouse', function ($q) {
                    $q->where('type', 'BESAR');
                })
                    ->whereNull('model_id')
                    ->whereNull('color_id')
                    ->first();
            }
            if ($recRack) {
                $inventory->update(['rack_id' => $recRack->id]);
            }
        }
        $detail = $inventory->detail()->firstOrCreate(
            ['series' => $series],
            ['quantity' => 0]
        );
        $detail->increment('quantity', $qty);
    }

    /**
     * Records an item that has no QR code, by quantity.
     *  - returned_good / returned_defect: goods returned UNSEWN. They close out
     *    the request line (rec_bs_qty) but are not finished goods: no stock and
     *    no payroll. Defective ones are flagged is_rejected.
     *  - extra ("barang lebih"): sewn pieces beyond / without QR. They count as
     *    received (rec_qty), go into big-warehouse stock and are paid in payroll.
     */
    private function createManualReceivedDetail($receivedLog, $requestDetail, string $category, int $qty): void
    {
        ReceivedlogDetail::create([
            'receivedlog_id' => $receivedLog->id,
            'request_detail_id' => $requestDetail->id,
            'model_id' => $requestDetail->model_id,
            'color_id' => $requestDetail->cutting->clothes->color_id,
            'size_id' => $requestDetail->size_id,
            'qty' => $qty,
            'barcode' => null,
            'is_rejected' => $category === 'returned_defect',
            'category' => $category,
        ]);

        if ($category === 'extra') {
            $requestDetail->increment('rec_qty', $qty);
            $this->addToBigInventory($requestDetail, $this->getSeries((string) $requestDetail->barcode), $qty);
        } else {
            $requestDetail->increment('rec_bs_qty', $qty);
        }
    }

    /** Open request lines to pick from when receiving goods without a QR code. */
    public function requestDetails(HttpRequest $request)
    {
        $perPage = (int) $request->input('per_page', 50);
        $search = trim((string) $request->input('search', ''));

        $query = RequestDetail::with(['request.cmt', 'model', 'size', 'cutting.clothes.color'])
            ->whereHas('request', fn($r) => $r->where(fn($s) => $s->whereNull('status')->orWhere('status', '!=', 'CLOSED')))
            ->orderByDesc('created_at');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->whereHas('request', fn($r) => $r->where('serial_number', 'like', "%{$search}%"))
                    ->orWhereHas('request.cmt', fn($r) => $r->where('name', 'like', "%{$search}%")->orWhere('code', 'like', "%{$search}%"))
                    ->orWhereHas('model', fn($r) => $r->where('name', 'like', "%{$search}%")->orWhere('sku', 'like', "%{$search}%"));
            });
        }

        $data = $query->paginate($perPage);
        $items = collect($data->items())->map(function ($d) {
            $serial = $d->request?->serial_number;
            $color = $d->cutting?->clothes?->color?->name;

            return [
                'id' => $d->id,
                'label' => collect([$serial, $d->request?->cmt?->name, $d->model?->name, $color, $d->size?->name])->filter()->implode(' · '),
                'serial_number' => $serial,
                'cmt' => $d->request?->cmt?->name,
                'model' => $d->model?->name,
                'color' => $color,
                'size' => $d->size?->name,
                'req_qty' => (int) $d->req_qty,
                'rec_qty' => (int) $d->rec_qty,
                'rec_bs_qty' => (int) $d->rec_bs_qty,
                'outstanding_qty' => (int) ($d->req_qty - $d->rec_qty - $d->rec_bs_qty),
            ];
        });

        return response()->json($this->paginateResponse($data, $items));
    }
    public function index(HttpRequest $request)
    {
        return $this->baseIndex(
            $request,
            Receivedlog::class,
            ['cmt', 'warehouse', 'user', 'details.model', 'details.color', 'details.size', 'details.requestDetail.request', 'details.product.rack'],
            ['details.barcode'],
            fn(ReceivedLog $data) => [
                'id' => $data->id,
                'cmt' => (object) [
                    'name' => $data->cmt->name,
                    'code' => $data->cmt->code,
                ],
                'received_date' => $data->received_date,
                'items' => $data->details->sum('qty'),
                'name' => $data->user?->name
            ],
            fn($query) => $query->orderBy('created_at', 'desc')
        );
    }
    public function show($id)
    {
        return $this->baseShow(
            Receivedlog::class,
            $id,
            ['cmt', 'warehouse', 'user', 'details.model', 'details.color', 'details.size', 'details.requestDetail.request', 'details.product.rack'],
            $this->structure()
        );
    }

    public function generateNext(HttpRequest $request)
    {
        return $this->baseValidate(
            $request,
            [
                'barcode' => 'required|string',
            ],
            function ($data) {
                $barcode = $data['barcode'];
                ['prefix' => $prefix] = $this->parseBarcode($barcode);

                $requestDetail = $this->findRequestDetail($prefix);
                if (!$requestDetail) {
                    return $this->errorResponse(422, 'Barcode tidak valid');
                }

                DB::transaction(function () use ($requestDetail) {
                    $requestDetail->increment('req_qty', 1);
                });

                $newBarcode = $prefix . '|P|' . $requestDetail->req_qty;

                return $this->successResponse([
                    'barcode' => $newBarcode,
                    'req_qty' => $requestDetail->req_qty
                ], "Barcode baru berhasil dibuat");
            }
        );
    }

    private function getSeries(string $prefix)
    {
        $parts = explode('|', $prefix);
        return $parts[1] ?? null;
    }
}
