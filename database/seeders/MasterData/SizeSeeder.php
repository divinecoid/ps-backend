<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Color;
use App\Models\MasterData\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    public function run(): void
    {
        $sizes = [
            [
                'code' => 'XS',
                'name' => 'Ekstra Kecil'
            ],
            [
                'code' => 'S',
                'name' => 'Kecil',
            ],
            [
                'code' => 'M',
                'name' => 'Sedang',
            ],
            [
                'code' => 'L',
                'name' => 'Besar',
            ],
            [
                'code' => 'XL',
                'name' => 'Ekstra Besar',
            ],
            [
                'code' => '2XL',
                'name' => '2 Ekstra Besar',
            ],
            [
                'code' => '3XL',
                'name' => '3 Ekstra Besar',
            ],
        ];

        foreach ($sizes as $size) {
            Size::firstOrCreate(['code' => $size['code']], $size);
        }
    }
}