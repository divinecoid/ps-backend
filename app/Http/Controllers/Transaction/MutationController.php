<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Product;
use App\Models\Transactions\Receivedlog;
use App\Models\Transactions\ReceivedlogDetail;
use App\Models\Transactions\RequestDetail;
use Illuminate\Support\Facades\Auth;
use DB;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Validation\Rule;
class MutationController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'user' => (object) [
                'name' => $data->user?->name
            ],
            'created_at' => $data->created_at,
            'notes' => str_replace('[MUTATION] ', '', $data->notes),
            'details' => $data->details->map(fn($detail) => [
                'barcode' => $detail->barcode,
                'rack_id' => $detail->product?->rack_id,
                'rack' => (object) [
                    'name' => $detail->product?->rack?->name ?? '-'
                ]
            ])
        ];
    }

    public function index(HttpRequest $request)
    {
        return $this->baseIndex(
            $request,
            Receivedlog::class,
            ['user', 'details.product.rack'],
            ['notes' => 'like'],
            $this->structure(),
            function ($query) {
                $query->where('notes', 'like', '[MUTATION]%');
                $query->orderBy('created_at', 'desc');
            }
        );
    }
    /*
        {
            "items": [
                {
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c5446",
                    "barcodes": [
                        "CMT01|20260116172102|LP|RED|XS|P|1",
                        "CMT01|20260116172102|LP|RED|XS|P|2",
                        "CMT01|20260116172102|LP|RED|XS|P|3"
                    ]
                },
                {
                    "rack_id": "019b85c4-c219-71f9-a7f8-0a5add1c9999",
                    "barcodes": [
                        "CMT01|20260116172102|LP|BLUE|M|P|1",
                        "CMT01|20260116172102|LP|BLUE|M|P|2"
                    ]
                }
            ]
        }
    */

    public function store(HTTPRequest $request)
    {
        return $this->baseValidate(
            $request,
            [
                'items' => 'required|array|min:1',
                'items.*.rack_id' => ['required', 'distinct', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
                'items.*.barcodes' => 'required|array|min:1',
                'items.*.barcodes.*' => 'required|string',
                'notes' => 'nullable|string|max:1000'
            ],
            function ($data) {
                try {


                    $invalidBarcodes = [];
                    $scannedBarcodes = [];

                    $seen = [];

                    foreach ($data['items'] as $item) {
                        foreach ($item['barcodes'] as $barcode) {
                            if (isset($seen[$barcode])) {
                                $invalidBarcodes[] = $barcode;
                                continue;
                            }
                            $seen[$barcode] = true;
                        }
                    }

                    foreach ($data['items'] as $items) {
                        foreach ($items['barcodes'] as $barcode) {
                            ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] = $this->parseBarcode($barcode);
                            if ($group == 'P') {
                                $requestDetail = $this->findRequestDetail($prefix);
                                if (!$requestDetail) {//cek jika barcode ditemukan di database
                                    $invalidBarcodes[] = $barcode;
                                    continue;
                                }

                                $request = $requestDetail->request;
                                $currentRequest ??= $request;//simpan current request pertama
    
                                if ($request->id !== $currentRequest->id) {
                                    $invalidBarcodes[] = $barcode;
                                    continue;
                                }

                                if ($sequence > $requestDetail->req_qty) {//jika (request tidak memiliki sisa piece) atau (request memiliki sisa piece dan sequence di luar dari range request piece)
                                    $invalidBarcodes[] = $barcode;
                                    continue;
                                }
                                if (
                                    Product::where('barcode', $barcode)->exists()//jika sudah pernah discan
                                ) {
                                    $scannedBarcodes[] = $barcode;
                                }
                            } else {
                                //jika bukan piece
                                $invalidBarcodes[] = $barcode;
                                continue;
                            }
                        }
                    }

                    $totalInvalid = count($invalidBarcodes) + count($scannedBarcodes);
                    if ($totalInvalid == 0 && $currentRequest !== null) {//jika semuanya lolos validasi
                        DB::transaction(function () use ($data, $currentRequest) {
                            $log = Receivedlog::create([
                                'request_id' => $currentRequest->id,
                                'user_id' => Auth::id(),
                                'received_date' => now(),
                                'notes' => '[MUTATION] ' . ($data['notes'] ?? '')
                            ]);

                            foreach ($data['items'] as $item) {
                                foreach ($item['barcodes'] as $barcode) {
                                    ['prefix' => $prefix] = $this->parseBarcode($barcode);
                                    if ($rd = $this->findRequestDetail($prefix)) {
                                        ReceivedlogDetail::create([
                                            'receivedlog_id' => $log->id,
                                            'request_detail_id' => $rd->id,
                                            'model_id' => $rd->model_id,
                                            'color_id' => $rd->color_id,
                                            'size_id' => $rd->size_id,
                                            'qty' => 1,
                                            'barcode' => $barcode
                                        ]);

                                        Product::create([
                                            'rack_id' => $item['rack_id'],
                                            'model_id' => $rd->model_id,
                                            'barcode' => $barcode
                                        ]);
                                    }
                                }
                            }

                        });
                        $totalScanned = collect($data['items'])->pluck('barcodes')->flatten()->count();

                        return $this->successResponse([
                            'total_scanned' => $totalScanned,
                        ], "Successfully processed {$totalScanned} items");
                    } else {
                        return $this->errorResponse(422, $totalInvalid . ' barcode tidak valid', [
                            'invalid' => $invalidBarcodes,
                            'scanned' => $scannedBarcodes,
                        ]);
                    }

                } catch (\Exception $e) {
                    return $this->errorResponse(500, $e->getMessage() . " Line: " . $e->getLine());
                }

            }
        );

    }

    private function findRequestDetail(string $prefix)
    {
        return RequestDetail::where('barcode', $prefix)->first();
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
                    return $this->errorResponse(422, 'Barcode bukan merupakan barcode piece');
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
                    ]
                ]);
            }
        );
    }
}