<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\CMT;
use App\Models\MasterData\Color;
use App\Models\MasterData\Product;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Size;
use Illuminate\Database\Seeder;
use Str;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch all master data
        $cmts = CMT::all();
        $colors = Color::all();
        $sizes = Size::all();
        $models = ProductModel::all();
        $racks = Rack::all();

        // Generate sample products
        $products = [];

        // Track used dozen+piece combinations per CMT+Model+Color+Size
        $usedCombinations = [];

        $productCounter = 0;

        foreach ($cmts as $cmtIndex => $cmt) {
            foreach ($models as $modelIndex => $model) {
                foreach ($colors as $colorIndex => $color) {
                    foreach ($sizes as $sizeIndex => $size) {
                        // Create a unique key for this product specification
                        $specKey = "{$cmt->code}|{$model->sku}|{$color->code}|{$size->code}";

                        // Initialize tracking array for this specification if not exists
                        if (!isset($usedCombinations[$specKey])) {
                            $usedCombinations[$specKey] = [];
                        }

                        // Generate 2-3 products per specification with unique dozen+piece
                        $productsPerSpec = rand(2, 3);

                        for ($i = 0; $i < $productsPerSpec; $i++) {
                            // Generate unique dozen and piece combination
                            $attempts = 0;
                            do {
                                $dozenNumber = rand(1, 5);
                                $pieceNumber = rand(1, 12);
                                $dozenPieceKey = "{$dozenNumber}|{$pieceNumber}";
                                $attempts++;

                                // Prevent infinite loop
                                if ($attempts > 100) {
                                    break 2; // Skip this product if can't find unique combination
                                }
                            } while (in_array($dozenPieceKey, $usedCombinations[$specKey]));

                            // Mark this combination as used
                            $usedCombinations[$specKey][] = $dozenPieceKey;

                            // Generate timestamp variation for each product
                            $timestamp = now()->addSeconds($productCounter)->format('YmdHis');

                            // Build barcode according to format:
                            // {CMT_CODE}|{Timestamp}|{MODELS_SKU}|{COLORS_CODE}|{SIZE_CODE}|{DOZEN}|{PIECE}
                            $barcode = implode('|', [
                                $cmt->code,
                                $timestamp,
                                $model->sku,
                                $color->code,
                                $size->code,
                                $dozenNumber,
                                $pieceNumber
                            ]);

                            // Assign to a random rack
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

                            // Limit total products to avoid excessive data
                            // Remove this condition if you want all combinations
                            if ($productCounter >= 200) {
                                break 5; // Break out of all loops
                            }
                        }
                    }
                }
            }
        }

        // Insert products in chunks for better performance
        foreach (array_chunk($products, 50) as $chunk) {
            Product::insert($chunk);
        }

        $this->command->info('Created ' . count($products) . ' products successfully!');
    }
}
