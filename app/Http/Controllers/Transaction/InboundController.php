<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\Color;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
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

    // {
    //     "barcodes_dozen": [
    //         "CMT01|20260116172102|LP|RED|XS|1|1",
    //         "CMT01|20260116172102|LP|RED|XS|1|1"
    //     ],
    //     "warehouse_id": "019b85c4-c21c-7215-b374-47b05605d1b3",
    //     "barcodes_piece": [
    //         {
    //             "barcode": "CMT01|20260116172102|LP|RED|XS||1",
    //             "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
    //         },
    //         {
    //             "barcode": "CMT01|20260116172102|LP|RED|XS||2",
    //             "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
    //         },
    //         {
    //             "barcode": "CMT01|20260116172102|LP|RED|XS||3",
    //             "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446"
    //         }
    //     ],
    //     "notes": "Received in good condition"
    // }
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
                    'code' => $data->request->cmt->code,
                    'name' => $data->request->cmt->name,
                    'contact_person' => $data->request->cmt->contact_person,
                    'phone' => $data->request->cmt->phone,
                    'address' => $data->request->cmt->address,

                ]
            ],
            'details' => $data->details->map(fn($detail) => [
                'barcode' => $detail->barcode,
                'rack' => $detail->racks
            ])
        ];
    }
    public function store(HTTPRequest $request)
    {
        return $this->baseValidate(
            $request,
            [
                'warehouse_id' => ['required_if:barcodes_dozen,!=,null', Rule::exists('mdx_warehouses', 'id')->whereNull('deleted_at')],
                'barcodes_dozen' => 'required_without:barcodes_piece|array|min:1',
                'barcodes_dozen.*' => 'required|string|distinct',
                'barcodes_piece' => 'required_without:barcodes_dozen|array|min:1',
                'barcodes_piece.*.barcode' => 'required|string|distinct',
                'barcodes_piece.*.rack_id' => ['required', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
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
                    //     "CMT01|20260116173826|LP|RED|XS|1|1",
                    //     "CMT01|20260116173826|LP|RED|XS|2|7",
                    //     "CMT01|20260116173826|LP|RED|XS|1|2",
                    //     "CMT01|20260116173826|LP|RED|XS|1|13"
                    // ],
                    $currentRequest = null;
                    if ($barcodesDozen) {
                        foreach ($barcodesDozen as $barcode) {//harus dalam bentuk dozen semua
                            ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] = $this->parseBarcode($barcode);
                            $requestDetail = $this->findRequestDetail($prefix);
                            if (!$requestDetail) {//cek jika barcode ditemukan di database
                                $invalidDozenBarcodes[] = $barcode;
                                continue;
                            }
                            $request = $requestDetail->request;
                            $currentRequest ??= $request;//simpan current request pertama
                            if ($request->id !== $currentRequest->id) {
                                $invalidDozenBarcodes[] = $barcode;
                                continue;
                            }
                            //jika bukan group
                            if ($group === '' || !is_numeric($group) || !is_numeric($sequence) || $sequence < 1 || $sequence > 12) {
                                $invalidDozenBarcodes[] = $barcode;
                                continue;
                            }
                            //jika group, data masuk cuma dari sini
                            if ($group * 12 > $requestDetail->req_qty || $sequence < 1 || $sequence > 12) {//cek jika barcode group diluar jangkauan, jika group sekarang dikali 12 -> menjadi total piece, lebih besar dari kuantitas yang diminta atau sequence per group lebih dari 12
                                $invalidDozenBarcodes[] = $barcode;
                                continue;
                            }
                            if (
                                ReceivedlogDetail::where('barcode', "{$prefix}|{$group}")->exists()//jika sudah pernah discan
                            ) {
                                $scannedDozenBarcodes[] = $barcode;
                            }
                        }
                    }
                    // "barcodes_piece": [
                    //     {
                    //         "barcode": "CMT01|20260116173826|LP|RED|XS||1",
                    //         "rack_id": "019b85c4-c21c-7215-b374-47b05605d1b3"
                    //     },
                    //     {
                    //         "barcode": "CMT01|20260116173826|LP|RED|XS||2",
                    //         "rack_id": "019b85c4-c21c-7215-b374-47b05605d1b3"
                    //     },
                    //     {
                    //         "barcode": "CMT01|20260116173826|LP|RED|XS||3",
                    //         "rack_id": "019b85c4-c21c-7215-b374-47b05605d1b3"
                    //     }
                    // ],
    
                    if ($barcodesPiece) {
                        foreach ($barcodesPiece as $items) {
                            $barcode = $items['barcode'];
                            ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] =
                                $this->parseBarcode($barcode);

                            $requestDetail = $this->findRequestDetail($prefix);
                            if (!$requestDetail) {//cek jika barcode ditemukan di database
                                $invalidPieceBarcodes[] = $barcode;
                                continue;
                            }

                            $request = $requestDetail->request;
                            $currentRequest ??= $request;//simpan current request pertama
    
                            if ($request->id !== $currentRequest->id) {
                                $invalidPieceBarcodes[] = $barcode;
                                continue;
                            }
                            //jika group (tidak boleh ada group) atau sequence bukan numeric
                            if ($group !== '' || !is_numeric($sequence)) {
                                $invalidPieceBarcodes[] = $barcode;
                                continue;
                            }
                            //jika bukan group (disini harus piece yang masuk), data cuma masuk dari sini
                            $remain = $requestDetail->req_qty % 12;
                            if ($remain === 0 || $sequence > $remain) {//jika (request tidak memiliki sisa piece) atau (request memiliki sisa piece dan sequence di luar dari range request piece)
                                $invalidPieceBarcodes[] = $barcode;
                                continue;
                            }
                            if (
                                ReceivedlogDetail::where('barcode', $barcode)->exists()//jika sudah pernah discan
                            ) {
                                $scannedPieceBarcodes[] = $barcode;
                            }
                        }
                    }
                    $totalInvalid = count($invalidDozenBarcodes) + count($invalidPieceBarcodes) + count($scannedDozenBarcodes) + count($scannedPieceBarcodes);
                    if ($totalInvalid == 0 && $currentRequest !== null) {//jika semuanya lolos validasi
                        DB::transaction(function () use ($data, $currentRequest, $barcodesDozen, $barcodesPiece) {

                            $receivedLog = Receivedlog::create([
                                'request_id' => $currentRequest->id,
                                'warehouse_id' => $data['warehouse_id'] ?? null,
                                'user_id' => Auth::id(),
                                'received_date' => now(),
                                'notes' => $data['notes']
                            ]);

                            foreach ($barcodesDozen as $barcode) {
                                ['prefix' => $prefix, 'group' => $group] = $this->parseBarcode($barcode);
                                if ($rd = $this->findRequestDetail($prefix)) {
                                    $this->createReceivedDetail(
                                        $receivedLog,
                                        $rd,
                                        "{$prefix}|{$group}",//sequence memang ga disimpan disini, biar bisa mewakili 1 lusin
                                        12
                                    );
                                }
                            }

                            foreach ($barcodesPiece as $items) {
                                ['prefix' => $prefix, 'sequence' => $sequence] =
                                    $this->parseBarcode($items['barcode']);

                                if ($rd = $this->findRequestDetail($prefix)) {
                                    $this->createReceivedDetail(
                                        $receivedLog,
                                        $rd,
                                        "{$prefix}||{$sequence}",//group sudah pasti kosong disini, jadi hasilnya pasti {$prefix}||{$sequence}
                                        1
                                    );
                                    Product::create([
                                        'rack_id' => $items['rack_id'],
                                        'model_id' => $rd->model_id,
                                        'barcode' => $items['barcode']
                                    ]);
                                }
                            }

                        });
                        $totalScanned = count($barcodesDozen) + count($barcodesPiece);

                        if ($currentRequest->isCompleted()) {
                            $currentRequest->update(['status' => 'CLOSED']);
                        }

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
                $parts = explode('|', $barcode);
                $cmtCode = $parts[0] ?? null;
                $modelSku = $parts[2] ?? null;
                $colorCode = $parts[3] ?? null;
                $sizeCode = $parts[4] ?? null;
                $prefix = implode('|', array_slice($parts, 0, -2));
                $group = $parts[count($parts) - 2] ?? null;
                $sequence = $parts[count($parts) - 1] ?? null;

                $requestDetail = $this->findRequestDetail($prefix);
                if (!$requestDetail) {//cek jika barcode ditemukan di database
                    return $this->errorResponse(422, 'Barcode tidak valid');
                }
                //jika group tapi group bukan angka atau sequence bukan angka
                if (($group !== '' && !is_numeric($group)) || !is_numeric($sequence)) {
                    return $this->errorResponse(422, 'Barcode tidak valid');
                }
                //jika group dan total item dalam group lebih besar daripada yang diterima atau sequence lebih besar daripada 12
                //cek jika barcode group diluar jangkauan, jika group sekarang dikali 12 -> menjadi total piece, lebih besar dari kuantitas yang diminta atau sequence per group lebih dari 12
                $remain = $requestDetail->req_qty % 12;
                if ($group === '') {
                    if ($remain === 0 || $sequence < 1 || $sequence > $remain) {
                        return $this->errorResponse(422, 'Nomor urut barcode piece diluar jangkauan');
                    }
                } else {
                    if (!is_numeric($group) || $group < 1 || $group * 12 > $requestDetail->req_qty || $sequence < 1 || $sequence > 12) {
                        return $this->errorResponse(422, 'Nomor urut barcode dozen diluar jangkauan');
                    }
                }
                $finalBarcode = $group === ''
                    ? "{$prefix}||{$sequence}"   // piece
                    : "{$prefix}|{$group}";      // dozen
    
                if (
                    ReceivedlogDetail::where('barcode', $finalBarcode)->exists()//jika sudah pernah discan
                ) {
                    return $this->errorResponse(422, 'Barcode sudah discan');
                }

                $cmt = CMT::where('code', $cmtCode)->first();
                $model = ProductModel::where('sku', $modelSku)->first();
                $color = Color::where('code', $colorCode)->first();
                $size = Size::where('code', $sizeCode)->first();
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
                    'is_dozen' => $group ? true : false
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
        return RequestDetail::where('barcode', $prefix)->first();
    }
    private function createReceivedDetail($receivedLog, $requestDetail, string $barcode, int $qty)
    {
        ReceivedlogDetail::create([
            'receivedlog_id' => $receivedLog->id,
            'request_detail_id' => $requestDetail->id,
            'model_id' => $requestDetail->model_id,
            'color_id' => $requestDetail->color_id,
            'size_id' => $requestDetail->size_id,
            'barcode' => $barcode,
        ]);

        $requestDetail->increment('rec_qty', $qty);
    }

    public function index(HttpRequest $request)
    {
        return $this->baseIndex(
            $request,
            Receivedlog::class,
            [],
            [],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            Receivedlog::class,
            $id,
            [],
            $this->structure()
        );
    }
}
