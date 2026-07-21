<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Warehouse;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Color;
use Illuminate\Database\Seeder;

class WarehouseRackSeeder extends Seeder
{
    public function run(): void
    {
        $model1 = ProductModel::where('sku', 'MDL01')->first();
        $model2 = ProductModel::where('sku', 'MDL02')->first();
        $model3 = ProductModel::where('sku', 'MDL03')->first();

        $colorRed = Color::where('code', 'RED')->first();
        $colorBlue = Color::where('code', 'BLUE')->first();
        $colorGreen = Color::where('code', 'GREEN')->first();
        $colorGray = Color::where('code', 'GRAY')->first();

        $warehouses = [
            [
                'code' => 'WH1',
                'name' => 'Gudang Besar Utama',
                'priority' => 1,
                'type' => 'BIG',
                'racks' => [
                    [
                        'code' => 'RR1',
                        'name' => 'Rak Merah Utama (T-Shirt Basic)',
                        'model_id' => $model1?->id,
                        'color_id' => $colorRed?->id,
                    ],
                    [
                        'code' => 'RB1',
                        'name' => 'Rak Biru Utama (T-Shirt Basic)',
                        'model_id' => $model1?->id,
                        'color_id' => $colorBlue?->id,
                    ],
                    [
                        'code' => 'RGRAY1',
                        'name' => 'Rak Abu-Abu Utama (T-Shirt Basic)',
                        'model_id' => $model1?->id,
                        'color_id' => $colorGray?->id,
                    ],
                    [
                        'code' => 'RG1',
                        'name' => 'Rak Hijau Utama (Polo Premium)',
                        'model_id' => $model2?->id,
                        'color_id' => null,
                    ],
                    [
                        'code' => 'RGEN1',
                        'name' => 'Rak Umum Utama',
                        'model_id' => null,
                        'color_id' => null,
                    ]
                ],
            ],
            [
                'code' => 'WH2',
                'name' => 'Gudang Lantai 2 (Sedang)',
                'priority' => 2,
                'type' => 'BIG',
                'racks' => [
                    [
                        'code' => 'RR2',
                        'name' => 'Rak Merah Lantai 2',
                        'model_id' => $model3?->id,
                        'color_id' => $colorRed?->id,
                    ],
                    [
                        'code' => 'RB2',
                        'name' => 'Rak Biru Lantai 2',
                        'model_id' => null,
                        'color_id' => null,
                    ],
                    [
                        'code' => 'RG2',
                        'name' => 'Rak Hijau Lantai 2',
                        'model_id' => null,
                        'color_id' => null,
                    ]
                ],
            ],
            [
                'code' => 'WH3',
                'name' => 'Gudang Kecil Lantai 3',
                'priority' => 3,
                'type' => 'SMALL',
                'racks' => [
                    [
                        'code' => 'RR3',
                        'name' => 'Rak Merah Lantai 3',
                        'model_id' => null,
                        'color_id' => null,
                    ],
                    [
                        'code' => 'RB3',
                        'name' => 'Rak Biru Lantai 3',
                        'model_id' => null,
                        'color_id' => null,
                    ],
                    [
                        'code' => 'RGRAY3',
                        'name' => 'Rak Abu-Abu Kecil (T-Shirt Basic)',
                        'model_id' => $model1?->id,
                        'color_id' => $colorGray?->id,
                    ],
                    [
                        'code' => 'RG3',
                        'name' => 'Rak Hijau Lantai 3',
                        'model_id' => null,
                        'color_id' => null,
                    ]
                ],
            ],
        ];

        foreach ($warehouses as $data) {
            $warehouse = Warehouse::firstOrCreate(
                ['code' => $data['code']],
                collect($data)->except('racks')->toArray()
            );
            foreach ($data['racks'] as $rack) {
                $warehouse->rack()->firstOrCreate(
                    ['code' => $rack['code']],
                    $rack
                );
            }
        }
    }
}