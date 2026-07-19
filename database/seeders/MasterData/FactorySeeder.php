<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Factory;
use Illuminate\Database\Seeder;

class FactorySeeder extends Seeder
{
    public function run(): void
    {
        $factories = [
            [
                'code' => 'FCT1',
                'name' => 'Pabrik 1',
            ],
            [
                'code' => 'FCT2',
                'name' => 'Pabrik 2',
            ],
        ];

        foreach ($factories as $factory) {
            Factory::firstOrCreate(['code' => $factory['code']], $factory);
        }
    }
}