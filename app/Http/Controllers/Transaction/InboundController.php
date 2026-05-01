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
                'barcode' => $detail->barcode,
                'model' => $detail->model?->name,
                'color' => $detail->color?->name,
                'size' => $detail->size?->name,
                'serial_number' => $detail->requestDetail?->request?->serial_number,
                'qty' => $detail->qty,
                'rack' => $detail->product?->rack?->code
            ]),
            'rejected_details' => $data->details->filter(fn($detail) => $detail->is_rejected)->map(fn($detail) => [
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
                    ($detail->is_rejected ? '1' : '0');
            })->map(function ($group) {
                $first = $group->first();
                return [
                    'model' => $first->model?->name,
                    'color' => $first->color?->name,
                    'size' => $first->size?->name,
                    'serial_number' => $first->requestDetail?->request?->serial_number,
                    'total_qty' => $group->sum('qty'),
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
                'warehouse_id' => ['required_if:barcodes_dozen,!=,null', Rule::exists('mdx_warehouses', 'id')->whereNull('deleted_at')],
                'barcodes_dozen' => 'required_without_all:barcodes_piece,barcodes_rejected|array|min:1',
                'barcodes_dozen.*' => 'required|string|distinct',
                'barcodes_piece' => 'required_without_all:barcodes_dozen,barcodes_rejected|array|min:1',
                'barcodes_piece.*.barcode' => 'required|string|distinct',
                'barcodes_piece.*.rack_id' => ['required', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
                'barcodes_rejected' => 'required_without_all:barcodes_dozen,barcodes_piece|array|min:1',
                'barcodes_rejected.*' => 'required|string|distinct',
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
                    $totalInvalid = count($invalidDozenBarcodes) + count($invalidPieceBarcodes) + count($scannedDozenBarcodes) + count($scannedPieceBarcodes) + count($invalidRejectedBarcodes) + count($scannedRejectedBarcodes);
                    $totalConflict = count($conflictBarcodes);
                    if ($totalInvalid == 0 && $requestsFound && $totalConflict == 0) {//jika semuanya lolos validasi
                        DB::transaction(function () use ($data, $barcodesDozen, $barcodesPiece, $barcodesRejected) {
                            $groupedReceivedLogs = [];
                            $requestsToComplete = [];

                            foreach ($barcodesDozen as $barcode) {
                                ['prefix' => $prefix] = $this->parseBarcode($barcode);
                                if ($rd = $this->findRequestDetail($prefix)) {
                                    $req = $rd->request;
                                    $cmtId = $req->cmt_id;
                                    $requestsToComplete[$req->id] = $req;

                                    if (!isset($groupedReceivedLogs[$cmtId])) {
                                        $groupedReceivedLogs[$cmtId] = Receivedlog::create([
                                            'cmt_id' => $cmtId,
                                            'warehouse_id' => $data['warehouse_id'] ?? null,
                                            'user_id' => Auth::id(),
                                            'received_date' => now(),
                                            'notes' => $data['notes']
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
                                            'warehouse_id' => $data['warehouse_id'] ?? null,
                                            'user_id' => Auth::id(),
                                            'received_date' => now(),
                                            'notes' => $data['notes']
                                        ]);
                                    }

                                    $this->createReceivedDetail(
                                        $groupedReceivedLogs[$cmtId],
                                        $rd,
                                        $barcode,
                                        1
                                    );
                                    Product::create([
                                        'rack_id' => $items['rack_id'],
                                        'model_id' => $rd->model_id,
                                        'series' => $series,
                                        'barcode' => $barcode
                                    ]);
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
                                            'warehouse_id' => $data['warehouse_id'] ?? null,
                                            'user_id' => Auth::id(),
                                            'received_date' => now(),
                                            'notes' => $data['notes']
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

                            foreach ($requestsToComplete as $reqId => $req) {
                                if ($req->isCompleted()) {
                                    $req->update(['status' => 'CLOSED']);
                                }
                            }
                        });
                        $totalScanned = count($barcodesDozen) + count($barcodesPiece) + count($barcodesRejected);

                        return $this->successResponse([
                            'total_scanned' => $totalScanned,
                        ], "Successfully processed {$totalScanned} items");
                    } else {
                        return $this->errorResponse(422, $totalInvalid . ' barcode tidak valid, ' . $totalConflict . ' barcode konflik', [
                            'invalid' => [
                                'barcodes_dozen' => $invalidDozenBarcodes,
                                'barcodes_piece' => $invalidPieceBarcodes,
                                'barcodes_rejected' => $invalidRejectedBarcodes,
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
                $color = $requestDetail->color;
                $size = $requestDetail->size;
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
                    'is_dozen' => $group === 'D' ? true : false
                ]);
            }
        );
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
        return RequestDetail::with(['request.cmt', 'model', 'color', 'size'])->where('barcode', $prefix)->first();
    }
    private function createReceivedDetail($receivedLog, $requestDetail, string $barcode, int $qty, bool $is_rejected = false)
    {
        ['prefix' => $prefix, 'group' => $group] = $this->parseBarcode($barcode);
        $series = $this->getSeries($prefix);
        ReceivedlogDetail::create([
            'receivedlog_id' => $receivedLog->id,
            'request_detail_id' => $requestDetail->id,
            'model_id' => $requestDetail->model_id,
            'color_id' => $requestDetail->color_id,
            'size_id' => $requestDetail->size_id,
            'qty' => $qty,
            'barcode' => $barcode,
            'is_rejected' => $is_rejected
        ]);
        $requestDetail->increment('rec_qty', $qty);
        if (!$is_rejected && $group == 'D') {
            $inventory = Inventory::firstOrCreate(
                [
                    'model_id' => $requestDetail->model_id,
                    'color_id' => $requestDetail->color_id,
                    'size_id' => $requestDetail->size_id,
                ]
            );
            $detail = $inventory->detail()->firstOrCreate(
                ['series' => $series],
                ['quantity' => 0]
            );
            $detail->increment('quantity', $qty);
        }
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
