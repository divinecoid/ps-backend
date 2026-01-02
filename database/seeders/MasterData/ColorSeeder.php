<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Color;
use Illuminate\Database\Seeder;

class ColorSeeder extends Seeder
{
    public function run(): void
    {
        $colors = [
            [
                'code' => 'RED',
                'name' => 'Merah'
            ],
            [
                'code' => 'GREEN',
                'name' => 'Hijau',
            ],
            [
                'code' => 'BLUE',
                'name' => 'Biru',
            ],
            [
                'code' => 'YELLOW',
                'name' => 'Kuning',
            ],
            [
                'code' => 'BLACK',
                'name' => 'Hitam',
            ],
            [
                'code' => 'GRAY',
                'name' => 'Abu abu',
            ],
            [
                'code' => 'WHITE',
                'name' => 'Putih',
            ],
        ];

        foreach ($colors as $color) {
            Color::create($color);
        }
    }
}