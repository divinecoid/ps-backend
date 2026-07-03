<?php

namespace Database\Seeders;

use Database\Seeders\MasterData\CMTSeeder;
use Database\Seeders\MasterData\ColorSeeder;
use Database\Seeders\MasterData\RoleSeeder;
use Database\Seeders\MasterData\SizeSeeder;
use Database\Seeders\MasterData\UserSeeder;
use Database\Seeders\MasterData\WarehouseRackSeeder;
use Database\Seeders\MasterData\ConfigurationSeeder;
use Database\Seeders\MasterData\ShippingLogisticSeeder;
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
            CMTSeeder::class,
            ColorSeeder::class,
            SizeSeeder::class,
            ProductModelSeeder::class,
            WarehouseRackSeeder::class,
            ShopeeSeeder::class,
            WarehouseRackSeeder::class,
            ConfigurationSeeder::class,
            ShippingLogisticSeeder::class,
            FactorySeeder::class,
            ProductSeeder::class,
        ]);

        // Seed Request and Request Detail specifically for user's barcode request: series 1234
        $cmt = \App\Models\MasterData\CMT::where('code', 'CMT01')->first();
        $model = \App\Models\MasterData\ProductModel::where('sku', 'MDL01')->first();
        $size = \App\Models\MasterData\Size::where('code', 'XS')->first();
        $factory = \App\Models\MasterData\Factory::first();

        if ($cmt && $model && $size && $factory) {
            $rollSize = \App\Models\MasterData\RollSize::create([
                'size' => '25'
            ]);

            $cloth = \App\Models\MasterData\Cloth::create([
                'factory_id' => $factory->id,
                'gram' => '200',
                'roll_size_id' => $rollSize->id,
                'color_id' => $color->id,
                'quantity' => 100,
                'sequence' => 'K1'
            ]);

            $fabricCutting = \App\Models\Transactions\FabricCutting::create([
                'serial_number' => 'FC-1234',
                'status' => 'OPEN'
            ]);

            \App\Models\FabricCuttingFabric::create([
                'fabric_cutting_id' => $fabricCutting->id,
                'fabric_id' => $cloth->id,
                'quantity' => 10
            ]);

            \App\Models\Transactions\FabricCuttingDetail::create([
                'fabric_cutting_id' => $fabricCutting->id,
                'model_id' => $model->id,
                'size_id' => $size->id,
                'req_qty' => 120,
                'avl_qty' => 0
            ]);

            $request = \App\Models\Transactions\Request::create([
                'cmt_id' => $cmt->id,
                'status' => 'OPEN',
                'serial_number' => 'REQ-1234'
            ]);

            \App\Models\Transactions\RequestDetail::create([
                'request_id' => $request->id,
                'model_id' => $model->id,
                'cloth_id' => $fabricCutting->id,
                'size_id' => $size->id,
                'req_qty' => 120,
                'rec_qty' => 0,
                'rec_bs_qty' => 0,
                'barcode' => 'CMT01|1234|MDL01|XS'
            ]);
        }
    }
}
