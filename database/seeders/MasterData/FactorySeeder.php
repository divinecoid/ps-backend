<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\CMT;
use Illuminate\Database\Seeder;

class FactorySeeder extends Seeder
{
    public function run(): void
    {
        $cmts = [
            [
                'code' => 'FCT1',
                'name' => 'Pabrik 1',
            ],
            [
                'code' => 'FCT2',
                'name' => 'Pabrik 2',
            ],
        ];

        foreach ($cmts as $cmt) {
            CMT::create($cmt);
        }
    }
}