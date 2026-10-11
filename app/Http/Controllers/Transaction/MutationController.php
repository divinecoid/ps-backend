<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\InventoryDetail;
use App\Models\MasterData\Product;
use App\Models\Transactions\Receivedlog;
use App\Models\Transactions\ReceivedlogDetail;
use App\Models\Transactions\RequestDetail;
use App\Services\LegacyStockService;
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
                    // Old-stock ("stok lama") barcodes have no CMT request: they are
                    // moved out of the big warehouse using their Product row instead.
                    $legacyProducts = [];
                    $currentRequest = null;

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
                                if (!$requestDetail) {
                                    $legacy = $this->findLegacyProduct($barcode);
                                    if (!$legacy) {//cek jika barcode ditemukan di database
                                        $invalidBarcodes[] = $barcode;
                                    } elseif ($legacy->rack_id !== null) {//stok lama yang sudah dipindah / sudah punya rak
                                        $scannedBarcodes[] = $barcode;
                                    } else {
                                        $legacyProducts[$barcode] = $legacy;
                                    }
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
                    if ($totalInvalid == 0 && ($currentRequest !== null || !empty($legacyProducts))) {//jika semuanya lolos validasi
                        DB::transaction(function () use ($data, $currentRequest, $legacyProducts) {
                            $cmtId = $currentRequest?->cmt_id
                                ?? CMT::where('code', explode('|', array_key_first($legacyProducts))[0])->value('id')
                                ?? CMT::value('id');
                            $log = Receivedlog::create([
                                'cmt_id' => $cmtId,
                                'request_id' => $currentRequest?->id,
                                'user_id' => Auth::id(),
                                'received_date' => now(),
                                'notes' => '[MUTATION] ' . ($data['notes'] ?? '')
                            ]);

                            foreach ($data['items'] as $item) {
                                foreach ($item['barcodes'] as $barcode) {
                                    ['prefix' => $prefix] = $this->parseBarcode($barcode);
                                    $series = $this->getSeries($prefix);
                                    if (isset($legacyProducts[$barcode])) {
                                        // Old stock: move the existing Product into the chosen
                                        // small-warehouse rack and take 1 pc out of the big warehouse.
                                        $legacyProducts[$barcode]->update(['rack_id' => $item['rack_id']]);
                                        app(LegacyStockService::class)->deductOnePiece($legacyProducts[$barcode]);
                                        continue;
                                    }
                                    if ($rd = $this->findRequestDetail($prefix)) {
                                        ReceivedlogDetail::create([
                                            'receivedlog_id' => $log->id,
                                            'request_detail_id' => $rd->id,
                                            'model_id' => $rd->model_id,
                                            'color_id' => $rd->cutting->clothes->color_id,
                                            'size_id' => $rd->size_id,
                                            'qty' => 1,
                                            'barcode' => $barcode
                                        ]);

                                        Product::create([
                                            'rack_id' => $item['rack_id'],
                                            'model_id' => $rd->model_id,
                                            'color_id' => $rd->cutting->clothes->color_id,
                                            'size_id' => $rd->size_id,
                                            'series' => $series,
                                            'barcode' => $barcode
                                        ]);
                                        InventoryDetail::where('series', $series)
                                            ->whereHas('inventory', function ($q) use ($rd) {
                                                $q->where([
                                                    'model_id' => $rd->model_id,
                                                    'color_id' => $rd->cutting->clothes->color_id,
                                                    'size_id' => $rd->size_id
                                                ]);
                                            })
                                            ->where('quantity', '>', 0)
                                            ->decrement('quantity');
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
        return RequestDetail::with([
            'request',
            'model',
            'size',
            'cutting.clothes.color',
        ])->where('barcode', $prefix)->first();
    }

    /** Old-stock Product (has a stock_batch_id) still sitting in the big warehouse (no rack yet). */
    private function findLegacyProduct(string $barcode): ?Product
    {
        return Product::with(['model', 'color', 'size'])
            ->where('barcode', $barcode)
            ->whereNotNull('stock_batch_id')
            ->first();
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
                if (!$requestDetail) {
                    // Old-stock barcode (Input Stok Lama): no CMT request, identified by its Product row.
                    $legacy = $this->findLegacyProduct($barcode);
                    if (!$legacy) {//cek jika barcode ditemukan di database
                        return $this->errorResponse(422, 'Barcode tidak valid');
                    }
                    if ($group !== 'P') {
                        return $this->errorResponse(422, 'Barcode bukan merupakan barcode piece');
                    }
                    if ($legacy->rack_id !== null) {
                        return $this->errorResponse(422, 'Barcode sudah discan');
                    }
                    $cmt = CMT::where('code', explode('|', $barcode)[0])->first();
                    return $this->successResponse([
                        'cmt' => (object) [
                            'code' => $cmt?->code,
                            'name' => $cmt?->name,
                            'contact_person' => $cmt?->contact_person,
                            'phone' => $cmt?->phone,
                            'address' => $cmt?->address,
                        ],
                        'model' => (object) ['sku' => $legacy->model?->sku, 'name' => $legacy->model?->name],
                        'color' => (object) ['code' => $legacy->color?->code, 'name' => $legacy->color?->name],
                        'size' => (object) ['code' => $legacy->size?->code, 'name' => $legacy->size?->name],
                    ]);
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
                $color =  $requestDetail->cutting->clothes->color;
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

    private function getSeries(string $prefix)
    {
        $parts = explode('|', $prefix);
        return $parts[1] ?? null;
    }
}