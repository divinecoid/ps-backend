<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\CMT;
use App\Models\MasterData\Inventory;
use App\Models\MasterData\InventoryDetail;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Product;
use App\Models\MasterData\Notification;
use App\Models\MasterData\NotificationRecipient;
use App\Models\MasterData\User;
use App\Models\Transactions\MutationHistory;
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
            'source_rack' => $data->sourceRack ? (object) ['id' => $data->sourceRack->id, 'name' => $data->sourceRack->name] : null,
            'details' => $data->histories->isNotEmpty()
                ? $data->histories->map(fn($h) => [
                    'barcode' => $h->barcode,
                    'rack_id' => $h->to_rack_id,
                    'rack' => (object) ['name' => $h->toRack?->name ?? '-'],
                    'from_rack' => $h->fromRack ? (object) ['name' => $h->fromRack->name] : null,
                    'from_warehouse' => $h->fromWarehouse?->name,
                    'to_warehouse' => $h->toWarehouse?->name,
                ])
                : $data->details->map(fn($detail) => [
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
            ['user', 'sourceRack', 'details.product.rack', 'histories.fromRack', 'histories.toRack', 'histories.fromWarehouse', 'histories.toWarehouse'],
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
                'source_rack_id' => ['nullable', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
                'notes' => 'nullable|string|max:1000'
            ],
            function ($data) {
                try {
                    // Opsi 1: barang diambil dari rak asal yang discan (gudang besar / kecil).
                    // Tanpa source_rack_id = Opsi 2 (rak tujuan saja).
                    $sourceRackId = $data['source_rack_id'] ?? null;
                    if ($sourceRackId && collect($data['items'])->pluck('rack_id')->contains($sourceRackId)) {
                        return $this->errorResponse(422, 'Rak tujuan tidak boleh sama dengan rak asal');
                    }

                    $invalidBarcodes = [];
                    $scannedBarcodes = [];
                    // Old-stock ("stok lama") barcodes have no CMT request: they are
                    // moved out of the big warehouse using their Product row instead.
                    $legacyProducts = [];
                    // Barang yang sudah punya rak: dipindah antar rak (tanpa mengubah stok).
                    $moves = [];
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
                            if ($group != 'P') {
                                //jika bukan piece
                                $invalidBarcodes[] = $barcode;
                                continue;
                            }

                            $requestDetail = $this->findRequestDetail($prefix);
                            $existing = Product::where('barcode', $barcode)->first();

                            // Sudah punya rak -> pindah antar rak.
                            if ($existing && $existing->rack_id !== null) {
                                if (
                                    $existing->rack_id === $items['rack_id']
                                    || ($sourceRackId && $existing->rack_id !== $sourceRackId)
                                ) {
                                    $scannedBarcodes[] = $barcode;
                                } else {
                                    $moves[$barcode] = $existing;
                                }
                                continue;
                            }

                            if (!$requestDetail) {
                                $legacy = $this->findLegacyProduct($barcode);
                                if (!$legacy) {//cek jika barcode ditemukan di database
                                    $invalidBarcodes[] = $barcode;
                                } elseif ($sourceRackId && !$this->inSourceRack($sourceRackId, $legacy->model_id, $legacy->color_id, $legacy->size_id)) {
                                    $invalidBarcodes[] = $barcode;
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
                            if ($sourceRackId && !$this->inSourceRack($sourceRackId, $requestDetail->model_id, $requestDetail->cutting->clothes->color_id, $requestDetail->size_id)) {
                                $invalidBarcodes[] = $barcode;
                                continue;
                            }
                        }
                    }

                    $totalInvalid = count($invalidBarcodes) + count($scannedBarcodes);
                    if ($totalInvalid == 0 && ($currentRequest !== null || !empty($legacyProducts) || !empty($moves))) {//jika semuanya lolos validasi
                        $log = null;
                        $movements = [];
                        DB::transaction(function () use ($data, $currentRequest, $legacyProducts, $moves, $sourceRackId, &$log, &$movements) {
                            $firstKey = array_key_first($legacyProducts) ?? array_key_first($moves);
                            $cmtId = $currentRequest?->cmt_id
                                ?? ($firstKey ? CMT::where('code', explode('|', $firstKey)[0])->value('id') : null)
                                ?? CMT::value('id');
                            $log = Receivedlog::create([
                                'cmt_id' => $cmtId,
                                'request_id' => $currentRequest?->id,
                                'user_id' => Auth::id(),
                                'received_date' => now(),
                                'notes' => '[MUTATION] ' . ($data['notes'] ?? ''),
                                'source_rack_id' => $sourceRackId
                            ]);

                            $rackIds = collect($data['items'])->pluck('rack_id')->push($sourceRackId)->filter()->unique()->all();
                            $racks = Rack::with('warehouse')->whereIn('id', $rackIds)->get()->keyBy('id');

                            foreach ($data['items'] as $item) {
                                $toRack = $racks[$item['rack_id']] ?? null;
                                foreach ($item['barcodes'] as $barcode) {
                                    ['prefix' => $prefix] = $this->parseBarcode($barcode);
                                    $series = $this->getSeries($prefix);
                                    $product = null;
                                    $fromRackId = null;

                                    if (isset($moves[$barcode])) {
                                        // Antar rak: hanya ganti rak, stok tidak berubah.
                                        $product = $moves[$barcode];
                                        $fromRackId = $product->rack_id;
                                        $product->update(['rack_id' => $item['rack_id']]);
                                    } elseif (isset($legacyProducts[$barcode])) {
                                        // Old stock: move the existing Product into the chosen
                                        // small-warehouse rack and take 1 pc out of the big warehouse.
                                        $product = $legacyProducts[$barcode];
                                        $fromRackId = $sourceRackId ?? $this->inventoryRackId($product->model_id, $product->color_id, $product->size_id);
                                        $product->update(['rack_id' => $item['rack_id']]);
                                        app(LegacyStockService::class)->deductOnePiece($product);
                                    } elseif ($rd = $this->findRequestDetail($prefix)) {
                                        $colorId = $rd->cutting->clothes->color_id;
                                        $fromRackId = $sourceRackId ?? $this->inventoryRackId($rd->model_id, $colorId, $rd->size_id);
                                        ReceivedlogDetail::create([
                                            'receivedlog_id' => $log->id,
                                            'request_detail_id' => $rd->id,
                                            'model_id' => $rd->model_id,
                                            'color_id' => $colorId,
                                            'size_id' => $rd->size_id,
                                            'qty' => 1,
                                            'barcode' => $barcode
                                        ]);

                                        $product = Product::create([
                                            'rack_id' => $item['rack_id'],
                                            'model_id' => $rd->model_id,
                                            'color_id' => $colorId,
                                            'size_id' => $rd->size_id,
                                            'series' => $series,
                                            'barcode' => $barcode
                                        ]);
                                        InventoryDetail::where('series', $series)
                                            ->whereHas('inventory', function ($q) use ($rd, $colorId) {
                                                $q->where([
                                                    'model_id' => $rd->model_id,
                                                    'color_id' => $colorId,
                                                    'size_id' => $rd->size_id
                                                ]);
                                            })
                                            ->where('quantity', '>', 0)
                                            ->decrement('quantity');
                                    } else {
                                        continue;
                                    }

                                    $fromRack = $fromRackId ? ($racks[$fromRackId] ?? Rack::with('warehouse')->find($fromRackId)) : null;
                                    if ($fromRack) {
                                        $racks[$fromRack->id] = $fromRack;
                                    }
                                    MutationHistory::create([
                                        'receivedlog_id' => $log->id,
                                        'barcode' => $barcode,
                                        'model_id' => $product?->model_id,
                                        'color_id' => $product?->color_id,
                                        'size_id' => $product?->size_id,
                                        'from_rack_id' => $fromRack?->id,
                                        'to_rack_id' => $item['rack_id'],
                                        'from_warehouse_id' => $fromRack?->warehouse_id,
                                        'to_warehouse_id' => $toRack?->warehouse_id,
                                        'user_id' => Auth::id(),
                                    ]);

                                    $key = ($fromRack?->id ?? '-') . '>' . $item['rack_id'];
                                    $movements[$key] ??= [
                                        'from_rack' => $fromRack?->name,
                                        'from_warehouse' => $fromRack?->warehouse?->name,
                                        'to_rack' => $toRack?->name,
                                        'to_warehouse' => $toRack?->warehouse?->name,
                                        'count' => 0,
                                    ];
                                    $movements[$key]['count']++;
                                }
                            }

                        });
                        $totalScanned = collect($data['items'])->pluck('barcodes')->flatten()->count();
                        $this->notifyMutation($log, $totalScanned, array_values($movements));

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

    /** 1 notifikasi per transaksi mutasi: semua admin + user yang melakukan mutasi. */
    private function notifyMutation(Receivedlog $log, int $total, array $movements): void
    {
        try {
            $user = Auth::user();
            $summary = collect($movements)
                ->map(fn($m) => "{$m['count']} item: " . ($m['from_rack'] ?? 'gudang besar') . ' → ' . ($m['to_rack'] ?? '-'))
                ->implode('; ');

            $notification = Notification::create([
                'unique_key' => 'mutation:' . $log->id,
                'title' => "Mutasi barang: {$total} item",
                'message' => ($user?->name ?? 'User') . " memutasi {$summary}.",
                'type' => 'mutasi',
                'data' => [
                    'receivedlog_id' => $log->id,
                    'total' => $total,
                    'user_name' => $user?->name,
                    'movements' => $movements,
                ],
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            $userIds = User::whereHas('roles', fn($q) => $q->whereRaw('LOWER(name) = ?', ['admin']))
                ->pluck('id')
                ->push(Auth::id())
                ->filter()
                ->unique();

            foreach ($userIds as $userId) {
                NotificationRecipient::create([
                    'notification_id' => $notification->id,
                    'user_id' => $userId,
                    'is_read' => false,
                ]);
            }
        } catch (\Throwable $e) {
            report($e); // mutasi sudah tersimpan; kegagalan notifikasi tidak boleh menggagalkannya
        }
    }

    private function inventoryRackId($modelId, $colorId, $sizeId): ?string
    {
        return Inventory::where(['model_id' => $modelId, 'color_id' => $colorId, 'size_id' => $sizeId])->value('rack_id');
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

    /** Stok gudang besar tercatat per model/warna/ukuran di Inventory.rack_id. */
    private function inSourceRack(string $rackId, $modelId, $colorId, $sizeId): bool
    {
        return Inventory::where(['model_id' => $modelId, 'color_id' => $colorId, 'size_id' => $sizeId])
            ->value('rack_id') === $rackId;
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
                'source_rack_id' => ['nullable', Rule::exists('mdx_racks', 'id')->whereNull('deleted_at')],
            ],
            function ($data) {
                $barcode = $data['barcode'];
                $sourceRackId = $data['source_rack_id'] ?? null;

                ['prefix' => $prefix, 'group' => $group, 'sequence' => $sequence] = $this->parseBarcode($barcode);

                // Barang yang sudah punya rak boleh dipindah ke rak lain (mutasi antar rak).
                $existing = Product::with(['model', 'color', 'size', 'rack'])->where('barcode', $barcode)->first();
                if ($existing && $existing->rack_id !== null) {
                    if ($group !== 'P') {
                        return $this->errorResponse(422, 'Barcode bukan merupakan barcode piece');
                    }
                    if ($sourceRackId && $existing->rack_id !== $sourceRackId) {
                        return $this->errorResponse(422, 'Barang tidak berada di rak asal yang dipilih');
                    }
                    $cmt = CMT::where('code', explode('|', $barcode)[0])->first();
                    return $this->successResponse([
                        'cmt' => (object) ['code' => $cmt?->code, 'name' => $cmt?->name, 'contact_person' => $cmt?->contact_person, 'phone' => $cmt?->phone, 'address' => $cmt?->address],
                        'model' => (object) ['sku' => $existing->model?->sku, 'name' => $existing->model?->name],
                        'color' => (object) ['code' => $existing->color?->code, 'name' => $existing->color?->name],
                        'size' => (object) ['code' => $existing->size?->code, 'name' => $existing->size?->name],
                        'current_rack' => (object) ['id' => $existing->rack_id, 'name' => $existing->rack?->name],
                    ]);
                }

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
                    if ($sourceRackId && !$this->inSourceRack($sourceRackId, $legacy->model_id, $legacy->color_id, $legacy->size_id)) {
                        return $this->errorResponse(422, 'Barang tidak berada di rak asal yang dipilih');
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

                if ($sourceRackId && !$this->inSourceRack($sourceRackId, $requestDetail->model_id, $requestDetail->cutting->clothes->color_id, $requestDetail->size_id)) {
                    return $this->errorResponse(422, 'Barang tidak berada di rak asal yang dipilih');
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