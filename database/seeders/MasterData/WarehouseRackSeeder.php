<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Warehouse;
use Illuminate\Database\Seeder;

class WarehouseRackSeeder extends Seeder
{
    public function run(): void
    {
        $warehouses = [
            [
                'code' => 'WH1',
                'name' => 'Gudang Lantai 1',
                'priority' => 1,
                'racks' => [
                    [
                        'code' => 'RR1',
                        'name' => 'Rak Merah Lantai 1',
                    ],
                    [
                        'code' => 'RB1',
                        'name' => 'Rak Biru Lantai 1',
                    ],
                    [
                        'code' => 'RG1',
                        'name' => 'Rak Hijau Lantai 1',
                    ]
                ],
            ],
            [
                'code' => 'WH2',
                'name' => 'Gudang Lantai 2',
                'priority' => 2,
                'racks' => [
                    [
                        'code' => 'RR2',
                        'name' => 'Rak Merah Lantai 2',
                    ],
                    [
                        'code' => 'RB2',
                        'name' => 'Rak Biru Lantai 2',
                    ],
                    [
                        'code' => 'RG2',
                        'name' => 'Rak Hijau Lantai 2',
                    ]
                ],
            ],
            [
                'code' => 'WH3',
                'name' => 'Gudang Lantai 3',
                'priority' => 3,
                'racks' => [
                    [
                        'code' => 'RR3',
                        'name' => 'Rak Merah Lantai 3',
                    ],
                    [
                        'code' => 'RB3',
                        'name' => 'Rak Biru Lantai 3',
                    ],
                    [
                        'code' => 'RG3',
                        'name' => 'Rak Hijau Lantai 3',
                    ]
                ],
            ],
        ];

        foreach ($warehouses as $data) {
            $warehouse = Warehouse::create(
                collect($data)->except('racks')->toArray()
            );
            $warehouse->rack()->createMany($data['racks']);
        }
    }
}