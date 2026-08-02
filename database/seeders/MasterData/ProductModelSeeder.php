<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\ProductModel;
use Illuminate\Database\Seeder;

class ProductModelSeeder extends Seeder
{
    public function run(): void
    {
        $models = [
            [
                'sku' => 'MDL01',
                'name' => 'Model T-Shirt Basic',
            ],
            [
                'sku' => 'MDL02',
                'name' => 'Model Polo Premium',
            ],
            [
                'sku' => 'MDL03',
                'name' => 'Model Hoodie Classic',
            ],
        ];

        $colors = \App\Models\MasterData\Color::all();
        $sizes = \App\Models\MasterData\Size::all();

        foreach ($models as $modelData) {
            $model = ProductModel::firstOrCreate(
                ['sku' => $modelData['sku']],
                $modelData
            );
            // Only attach colors/sizes if they haven't been attached yet
            if ($model->colors()->doesntExist()) {
                $model->colors()->attach($colors->pluck('id'));
            }
            if ($model->sizes()->doesntExist()) {
                $model->sizes()->attach($sizes->pluck('id'));
            }
        }
    }
}
