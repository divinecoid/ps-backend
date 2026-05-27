<?php

namespace Database\Seeders;

use Database\Seeders\MasterData\CMTSeeder;
use Database\Seeders\MasterData\ColorSeeder;
use Database\Seeders\MasterData\RoleSeeder;
use Database\Seeders\MasterData\SizeSeeder;
use Database\Seeders\MasterData\UserSeeder;
use Database\Seeders\MasterData\WarehouseRackSeeder;
use Database\Seeders\MasterData\ConfigurationSeeder;
use Database\Seeders\MasterData\ProductModelSeeder;
use Database\Seeders\MasterData\FactorySeeder;
use Database\Seeders\MasterData\ProductSeeder;
use Database\Seeders\ShopeeSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
      * Seed the application's database.
      */
    public function run(): void
    {
        $this->call([
            CMTSeeder::class,
            ColorSeeder::class,
            RoleSeeder::class,
            SizeSeeder::class,
            UserSeeder::class,
            ProductModelSeeder::class,
            WarehouseRackSeeder::class,
            ShopeeSeeder::class,
            ConfigurationSeeder::class,
            FactorySeeder::class,
            ProductSeeder::class,
        ]);

        // Seed Request and Request Detail specifically for user's barcode request: series 1234 - GRAY - XS
        $cmt = \App\Models\MasterData\CMT::where('code', 'CMT01')->first();
        $model = \App\Models\MasterData\ProductModel::where('sku', 'MDL01')->first();
        $color = \App\Models\MasterData\Color::where('code', 'GRAY')->first();
        $size = \App\Models\MasterData\Size::where('code', 'XS')->first();

        if ($cmt && $model && $color && $size) {
            $request = \App\Models\Transactions\Request::create([
                'cmt_id' => $cmt->id,
                'status' => 'OPEN',
                'serial_number' => 'REQ-1234'
            ]);

            \App\Models\Transactions\RequestDetail::create([
                'request_id' => $request->id,
                'model_id' => $model->id,
                'color_id' => $color->id,
                'size_id' => $size->id,
                'req_qty' => 120, // 10 dozen
                'rec_qty' => 0,
                'rec_bs_qty' => 0,
                'barcode' => 'CMT01|1234|MDL01|GRAY|XS'
            ]);
        }
    }
}
