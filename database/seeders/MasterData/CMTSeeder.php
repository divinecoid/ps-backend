<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\CMT;
use Illuminate\Database\Seeder;

class CMTSeeder extends Seeder
{
    public function run(): void
    {
        $cmts = [
            [
                'code' => 'CMT01',
                'name' => 'Penjahit 1',
                'contact_person' => 'Nama Penjahit',
                'phone' => '0852823920152',
                'address' => 'Jalan CMT 1 Penjahit 1'
            ],
            [
                'code' => 'CMT02',
                'name' => 'Penjahit 2',
                'contact_person' => 'Nama Orang',
                'phone' => '0895342059219',
                'address' => 'Jalan CMT 2 Penjahit 2'
            ],
        ];

        foreach ($cmts as $cmt) {
            CMT::create($cmt);
        }
    }
}