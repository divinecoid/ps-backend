<?php

namespace App\Http\Controllers\Transaction;

use App\Http\Controllers\Controller;
use App\Http\Traits\CrudTrait;
use App\Models\MasterData\Cloth;
use App\Models\MasterData\Color;
use App\Models\MasterData\Factory;
use App\Models\MasterData\RollSize;
use App\Models\MasterData\Sequence;
use App\Models\Transactions\FabricPurchaseRequest;
use App\Models\Transactions\FabricPurchaseRequestDetail;
use App\Models\Transactions\FabricSeries;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class FabricPurchaseController extends Controller
{
    use CrudTrait;

    private function structure()
    {
        return fn($data) => [
            'id' => $data->id,
            'factory_id' => $data->factory_id,
            'factory' => $data->factory,
            'gram' => $data->gram,
            'ukuran' => $data->ukuran,
            'roll_size_id' => $data->roll_size_id,
            'roll_size' => $data->roll_size,
            'status' => $data->status,
            'created_at' => $data->created_at,
            'details' => $data->details?->map(fn($detail) => [
                'id' => $detail->id,
                'fabric_purchase_request_id' => $detail->fabric_purchase_request_id,
                'color_id' => $detail->color_id,
                'color' => $detail->color,
                'quantity' => $detail->quantity,
                'sequence' => $detail->sequence,
            ])->values(),
        ];
    }

    public function index(Request $request)
    {
        return $this->baseIndex(
            $request,
            FabricPurchaseRequest::class,
            ['factory', 'details.color'],
            ['factory.name', 'gram', 'ukuran', 'status'],
            $this->structure()
        );
    }

    public function show($id)
    {
        return $this->baseShow(
            FabricPurchaseRequest::class,
            $id,
            ['factory', 'details.color'],
            $this->structure()
        );
    }

    public function series(Request $request)
    {
        $configurationId = $request->input('configuration_id');
        $colorId = $request->input('color_id');

        return $this->baseIndex(
            $request,
            FabricSeries::class,
            ['configuration', 'color'],
            ['configuration.config_key', 'color.name', 'series_code', 'sequence'],
            fn($data) => [
                'id' => $data->id,
                'configuration_id' => $data->configuration_id,
                'configuration' => $data->configuration,
                'color_id' => $data->color_id,
                'color' => $data->color,
                'series_code' => $data->series_code,
                'sequence' => $data->sequence,
                'roll_available' => $data->roll_available,
            ],
            function ($query) use ($configurationId, $colorId) {
                if ($configurationId) {
                    $query->where('configuration_id', $configurationId);
                }
                if ($colorId) {
                    $query->where('color_id', $colorId);
                }
            }
        );
    }

    public function store(Request $request)
    {
        return $this->baseValidate(
            $request,
            [
                'factory_id' => 'required|uuid|exists:mdx_factories,id',
                'gram' => 'required|string|max:255',
                // accept either roll_size_id or ukuran; we'll derive ukuran from roll_size if provided
                'roll_size_id' => 'nullable|uuid|exists:mdx_roll_sizes,id',
                // 'ukuran' => 'nullable|integer|min:1',
                'details' => 'required|array|min:1',
                'details.*.color_id' => 'required|uuid|exists:mdx_colors,id',
                'details.*.quantity' => 'required|integer|min:1',
            ],
            function ($data) {
                $factory = Factory::findOrFail($data['factory_id']);

                try {
                    return DB::transaction(function () use ($data, $factory) {
                        // determine ukuran value: prefer roll_size_id if provided
                        $ukuranValue = null;
                        if (!empty($data['roll_size_id'])) {
                            $roll = RollSize::find($data['roll_size_id']);
                            if ($roll) $ukuranValue = (int) $roll->size;
                        }
                        if ($ukuranValue === null && isset($data['ukuran'])) {
                            $ukuranValue = (int) $data['ukuran'];
                        }

                        $requestModel = FabricPurchaseRequest::create([
                            'factory_id' => $data['factory_id'],
                            'gram' => $data['gram'],
                            'ukuran' => $ukuranValue,
                            'roll_size_id' => $data['roll_size_id'] ?? null,
                            'status' => 'OPEN',
                        ]);

                        $rows = [];
                        foreach ($data['details'] as $detail) {
                            $sequenceRow = Sequence::withTrashed()
                                ->with('color')
                                ->where('color_id', $detail['color_id'])
                                ->lockForUpdate()
                                ->first();

                            if (!$sequenceRow) {
                                $color = Color::find($detail['color_id']);
                                $sequenceKey = $color && $color->code
                                    ? sprintf('sequence.%s', strtolower($color->code))
                                    : sprintf('sequence.%s', $detail['color_id']);

                                $formatValue = $color && $color->code
                                    ? strtoupper($color->code)
                                    : strtoupper($detail['color_id']);

                                $sequenceRow = Sequence::create([
                                    'color_id' => $detail['color_id'],
                                    'key' => $sequenceKey,
                                    'format' => '{colorcode}.{sequence}',
                                    'current' => 0,
                                    'limit' => 999,
                                ]);
                                $sequenceRow->load('color');
                            } elseif ($sequenceRow->trashed()) {
                                $sequenceRow->restore();
                                $sequenceRow->load('color');
                            }

                            if ($sequenceRow->current >= $sequenceRow->limit) {
                                $sequenceRow->current = 0;
                                $sequenceRow->save();
                            }

                            $sequenceRow->increment('current');
                            $sequenceValue = $this->buildSequenceValue(
                                $sequenceRow->format,
                                $sequenceRow->color?->code,
                                $factory->code,
                                $sequenceRow->current,
                                $sequenceRow->limit,
                            );

                            $rows[] = [
                                'id' => Str::uuid(),
                                'fabric_purchase_request_id' => $requestModel->id,
                                'color_id' => $detail['color_id'],
                                'quantity' => (int) $detail['quantity'],
                                'sequence' => $sequenceValue,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];
                        }

                        FabricPurchaseRequestDetail::insert($rows);

                        $fresh = FabricPurchaseRequest::with(['factory', 'details.color'])->find($requestModel->id);
                        return $this->successResponse(($this->structure())($fresh));
                    });
                } catch (\Throwable $exception) {
                    return $this->errorResponse(422, $exception->getMessage());
                }
            }
        );
    }

    private function buildSequenceValue(?string $format, ?string $colorCode, ?string $factoryCode, int $sequenceNumber, int $limit): string
    {
        $digits = max(3, (int) floor(log10(max(1, $limit))) + 1);
        $sequenceNumberFormatted = str_pad((string) $sequenceNumber, $digits, '0', STR_PAD_LEFT);

        $replacements = [
            '{colorcode}' => strtoupper($colorCode ?? ''),
            '{factorycode}' => strtoupper($factoryCode ?? ''),
            '{sequence}' => $sequenceNumberFormatted,
        ];

        $format = $format ?? '{colorcode}.{sequence}';
        $value = strtr($format, $replacements);

        if (!str_contains($format, '{sequence}')) {
            $value = sprintf('%s.%s', $value, $sequenceNumberFormatted);
        }

        if (!str_contains($format, '{factorycode}') && $factoryCode) {
            $value = sprintf('%s.%s', strtoupper($factoryCode), $value);
        }

        return $value;
    }

    public function complete($id)
    {
        try {
            return DB::transaction(function () use ($id) {
                $purchase = FabricPurchaseRequest::with(['factory', 'roll_size', 'details.color'])->find($id);
                if (!$purchase) {
                    return $this->errorResponse(404, 'Pembelian kain tidak ditemukan.');
                }

                if ($purchase->status === 'CLOSED') {
                    return $this->errorResponse(422, 'Pembelian kain sudah selesai.');
                }

                if (!$purchase->roll_size_id) {
                    return $this->errorResponse(422, 'Ukuran roll tidak tersedia untuk pembelian ini.');
                }

                foreach ($purchase->details as $detail) {
                    $existingCloth = Cloth::withTrashed()->where('sequence', $detail->sequence)->first();

                    if ($existingCloth) {
                        if ($existingCloth->trashed()) {
                            $existingCloth->restore();
                        }
                        $existingCloth->quantity = $existingCloth->quantity + $detail->quantity;
                        $existingCloth->save();
                    } else {
                        Cloth::create([
                            'factory_id' => $purchase->factory_id,
                            'gram' => $purchase->gram,
                            'roll_size_id' => $purchase->roll_size_id,
                            'color_id' => $detail->color_id,
                            'quantity' => $detail->quantity,
                            'sequence' => $detail->sequence,
                        ]);
                    }
                }

                $purchase->status = 'CLOSED';
                $purchase->save();

                $fresh = FabricPurchaseRequest::with(['factory', 'details.color'])->find($purchase->id);
                return $this->successResponse(($this->structure())($fresh));
            });
        } catch (\Throwable $exception) {
            return $this->errorResponse(422, $exception->getMessage());
        }
    }

    public function destroy($id)
    {
        return $this->baseDelete(FabricPurchaseRequest::class, $id);
    }
}
