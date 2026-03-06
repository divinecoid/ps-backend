<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Configuration;
use Illuminate\Database\Seeder;

class ConfigurationSeeder extends Seeder
{
    public function run(): void
    {
        $configurations = [
            [
                'config_key' => 'is_need_checker',
                'config_value' => 'true',
                'data_type' => 'boolean',
                'description' => 'Apakah membutuhkan checker dalam proses operasional',
            ],
            [
                'config_key' => 'min_qty_gudang_kecil',
                'config_value' => '10',
                'data_type' => 'integer',
                'description' => 'Minimum quantity yang harus tersedia di gudang kecil',
            ],
            [
                'config_key' => 'min_qty_gudang_besar',
                'config_value' => '50',
                'data_type' => 'integer',
                'description' => 'Minimum quantity untuk mengambil langsung dari gudang besar',
            ],
        ];

        foreach ($configurations as $config) {
            Configuration::updateOrCreate(
                ['config_key' => $config['config_key']],
                $config
            );
        }
    }
}
