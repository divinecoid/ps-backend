<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\CMT;
use App\Models\MasterData\Color;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Size;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $cmts = CMT::all();
        $colors = Color::all();
        $sizes = Size::all();
        $models = ProductModel::all();
        $racks = Rack::all();

        $products = [];
        $productCounter = 0;

        foreach ($cmts as $cmt) {
            foreach ($models as $model) {
                foreach ($colors as $color) {
                    foreach ($sizes as $size) {

                        // Generate 2–3 products per specification (pure random)
                        $productsPerSpec = rand(2, 3);

                        for ($i = 0; $i < $productsPerSpec; $i++) {

                            // Random type
                            $types = ['D', 'P'];
                            $type = $types[array_rand($types)];

                            // Random number
                            $pieceNumber = rand(1, 12);

                            // Timestamp variation
                            $timestamp = now()
                                ->addSeconds($productCounter)
                                ->format('YmdHis');

                            // Barcode format:
                            // {CMT_CODE}|{Timestamp}|{MODEL_SKU}|{COLOR_CODE}|{SIZE_CODE}|{TYPE}|{NUMBER}
                            $barcode = implode('|', [
                                $cmt->code,
                                $timestamp,
                                $model->sku,
                                $color->code,
                                $size->code,
                                $type,
                                $pieceNumber
                            ]);

                            // Random rack
                            $rack = $racks->random();

                            $products[] = [
                                'id' => Str::uuid(),
                                'model_id' => $model->id,
                                'rack_id' => $rack->id,
                                'barcode' => $barcode,
                                'created_at' => now(),
                                'updated_at' => now(),
                            ];

                            $productCounter++;

                            // Hard limit 200 products total
                            if ($productCounter >= 200) {
                                break 5;
                            }
                        }
                    }
                }
            }
        }

        // Insert in chunks
        foreach (array_chunk($products, 50) as $chunk) {
            Product::insert($chunk);
        }

        $this->command->info('Created ' . count($products) . ' products successfully!');
    }
}
