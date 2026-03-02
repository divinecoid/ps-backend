<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Inventory;
use App\Models\MasterData\Product;
use App\Models\Transactions\Receivedlog;
use App\Models\Transactions\ReceivedlogDetail;
use App\Models\Transactions\RejectedlogDetail;
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
            "barcode_dozen": [
                "CMT01|20260116172102|LP|RED|XS|D|1",
                "CMT01|20260116172102|LP|RED|XS|D|2"
            ],
            "warehouse_id": "019b85c4-c21c-7215-b374-47b05605d1b3",
            "barcodes_piece": [
                {
                    "barcode": "CMT01|20260116172102|LP|RED|XS|P|1",
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
                },
                {
                    "barcode": "CMT01|20260116172102|LP|RED|XS|P|2",
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
                },
                {
                    "barcode": "CMT01|20260116172102|LP|RED|XS|P|3",
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
                }
            ],
            "notes": "Received in good condition"
        }
     */
    private function structure()
    {
        return fn($data) => [
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
            'details' => $data->details->map(fn($detail) => [
                'barcode' => $detail->barcode,
                'model' => $detail->model?->name,
                'color' => $detail->color?->name,
                'size' => $detail->size?->name,
                'serial_number' => $detail->requestDetail?->request?->serial_number,
                'qty' => $detail->qty,
                'rack' => $detail->product?->rack?->code,
                'is_reject' => false
            ]),
            'rejected_details' => $data->rejectedDetails->map(fn($detail) => [
                'barcode' => $detail->barcode,
                'model' => $detail->model?->name,
                'color' => $detail->color?->name,
                'size' => $detail->size?->name,
                'serial_number' => $detail->requestDetail?->request?->serial_number,
                'qty' => $detail->qty,
                'is_reject' => true
            ]),
            'summary' => $data->details->groupBy(function ($detail) {
                return $detail->model?->name . '|' .
                    $detail->color?->name . '|' .
                    $detail->size?->name . '|' .
                    $detail->requestDetail?->request?->serial_number;
            })->map(function ($group) {
                $first = $group->first();
                return [
                    'model' => $first->model?->name,
                    'color' => $first->color?->name,
                    'size' => $first->size?->name,
                    'serial_number' => $first->requestDetail?->request?->serial_number,
                    'total_qty' => $group->sum('qty'),
                    'is_reject' => false
                ];
            })->values()->concat(
                    $data->rejectedDetails->groupBy(function ($detail) {
                        return $detail->model?->name . '|' .
                            $detail->color?->name . '|' .
                            $detail->size?->name . '|' .
                            $detail->requestDetail?->request?->serial_number;
                    })->map(function ($group) {
                        $first = $group->first();
                        return [
                            'model' => $first->model?->name,
                            'color' => $first->color?->name,
                            'size' => $first->size?->name,
                            'serial_number' => $first->requestDetail?->request?->serial_number,
                            'total_qty' => $group->sum('qty'),
                            'is_reject' => true
                        ];
                    })->values()
                )
        ];
    }
    public function store(HTTPRequest $request)
    {
        return $this->baseValidate(
            $request,
            [
                'warehouse_id' => ['required_if:barcodes_dozen,!=,null', Rule::exists('mdx_warehouses', 'id')->whereNull('deleted_at')],
                'barcodes_dozen' => 'required_without:barcodes_piece|array|min:1',
                'barcodes_dozen.*.barcode' => 'required|string|distinct',
                'barcodes_dozen.*.is_reject' => 'nullable|boolean',
                'barcodes_piece' => 'required_without:barcodes_dozen|array|min:1',
                'barcodes_piece.*.barcode' => 'required|string|distinct',
                'barcodes_piece.*.rack_id' => 'required_unless:barcodes_piece.*.is_reject,true',
                'barcodes_piece.*.is_reject' => 'nullable|boolean',
                'notes' => 'nullable|string|max:1000',
            ],
            function ($data) {
                $invalidDozenBarcodes = [];
                $invalidPieceBarcodes = [];

                $scannedDozenBarcodes = [];
                $scannedPieceBarcodes = [];
                try {

                    $barcodesDozen = $data['barcodes_dozen'] ?? [];
                    $barcodesPiece = $data['barcodes_piece'] ?? [];

                    // "barcodes_dozen": [
                    //     "CMT01|20260116173826|LP|RED|XS|D|1",
                    //     "CMT01|20260116173826|LP|RED|XS|D|7",
                    //     "CMT01|20260116173826|LP|RED|XS|D|2",
                    //     "CMT01|20260116173826|LP|RED|XS|D|13"
                    // ],
                    $requestsFound = false;
                    if ($barcodesDozen) {
                        foreach ($barcodesDozen as $item) {//harus dalam bentuk dozen semua
                            $barcode = $item['barcode'];
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
                                    ReceivedlogDetail::where('barcode', $barcode)->exists() ||
                                    RejectedlogDetail::where('barcode', $barcode)->exists()//jika sudah pernah discan
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
                                    ReceivedlogDetail::where('barcode', $barcode)->exists() ||
                                    RejectedlogDetail::where('barcode', $barcode)->exists()//jika sudah pernah discan
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
                    $totalInvalid = count($invalidDozenBarcodes) + count($invalidPieceBarcodes) + count($scannedDozenBarcodes) + count($scannedPieceBarcodes);
                    if ($totalInvalid == 0 && $requestsFound) {//jika semuanya lolos validasi
                        DB::transaction(function () use ($data, $barcodesDozen, $barcodesPiece) {
                            $groupedReceivedLogs = [];
                            $requestsToComplete = [];

                            foreach ($barcodesDozen as $item) {
                                $barcode = $item['barcode'];
                                $isReject = $item['is_reject'] ?? false;
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

                                    if ($isReject) {
                                        $this->createRejectedDetail(
                                            $groupedReceivedLogs[$cmtId],
                                            $rd,
                                            $barcode,
                                            12
                                        );
                                    } else {
                                        $this->createReceivedDetail(
                                            $groupedReceivedLogs[$cmtId],
                                            $rd,
                                            $barcode,
                                            12
                                        );
                                    }
                                }
                            }

                            foreach ($barcodesPiece as $items) {
                                $barcode = $items['barcode'];
                                $isReject = $items['is_reject'] ?? false;
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

                                    if ($isReject) {
                                        $this->createRejectedDetail(
                                            $groupedReceivedLogs[$cmtId],
                                            $rd,
                                            $barcode,
                                            1
                                        );
                                    } else {
                                        $this->createReceivedDetail(
                                            $groupedReceivedLogs[$cmtId],
                                            $rd,
                                            $barcode,
                                            1
                                        );
                                        Product::create([
                                            'rack_id' => $items['rack_id'],
                                            'model_id' => $rd->model_id,
                                            'barcode' => $barcode
                                        ]);
                                    }
                                }
                            }

                            foreach ($requestsToComplete as $reqId => $req) {
                                if ($req->isCompleted()) {
                                    $req->update(['status' => 'CLOSED']);
                                }
                            }
                        });
                        $totalScanned = count($barcodesDozen) + count($barcodesPiece);

                        return $this->successResponse([
                            'total_scanned' => $totalScanned,
                        ], "Successfully processed {$totalScanned} items");
                    } else {
                        return $this->errorResponse(422, $totalInvalid . ' barcode tidak valid', [
                            'invalid' => [
                                'barcodes_dozen' => $invalidDozenBarcodes,
                                'barcodes_piece' => $invalidPieceBarcodes,
                            ],
                            'scanned' => [
                                'barcodes_dozen' => $scannedDozenBarcodes,
                                'barcode_piece' => $scannedPieceBarcodes,
                            ],
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
                    Product::where('barcode', $barcode)->exists()//jika sudah pernah discan
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
    private function createReceivedDetail($receivedLog, $requestDetail, string $barcode, int $qty)
    {
        ['prefix' => $prefix] = $this->parseBarcode($barcode);
        $series = $this->getSeries($prefix);
        ReceivedlogDetail::create([
            'receivedlog_id' => $receivedLog->id,
            'request_detail_id' => $requestDetail->id,
            'model_id' => $requestDetail->model_id,
            'color_id' => $requestDetail->color_id,
            'size_id' => $requestDetail->size_id,
            'qty' => $qty,
            'barcode' => $barcode,
        ]);
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
        $requestDetail->increment('rec_qty', $qty);
    }

    private function createRejectedDetail($receivedLog, $requestDetail, string $barcode, int $qty)
    {
        RejectedlogDetail::create([
            'receivedlog_id' => $receivedLog->id,
            'request_detail_id' => $requestDetail->id,
            'model_id' => $requestDetail->model_id,
            'color_id' => $requestDetail->color_id,
            'size_id' => $requestDetail->size_id,
            'qty' => $qty,
            'barcode' => $barcode,
        ]);
    }
    public function index(HttpRequest $request)
    {
        return $this->baseIndex(
            $request,
            Receivedlog::class,
            ['cmt', 'warehouse', 'user', 'details.model', 'details.color', 'details.size', 'details.requestDetail.request', 'details.product.rack', 'rejectedDetails.model', 'rejectedDetails.color', 'rejectedDetails.size'],
            [],
            $this->structure(),
            fn($query) => $query->orderBy('created_at', 'desc')
        );
    }
    public function show($id)
    {
        return $this->baseShow(
            Receivedlog::class,
            $id,
            ['cmt', 'warehouse', 'user', 'details.model', 'details.color', 'details.size', 'details.requestDetail.request', 'details.product.rack', 'rejectedDetails.model', 'rejectedDetails.color', 'rejectedDetails.size'],
            $this->structure()
        );
    }

    private function getSeries(string $prefix)
    {
        $parts = explode('|', $prefix);
        return $parts[1] ?? null;
    }
}
