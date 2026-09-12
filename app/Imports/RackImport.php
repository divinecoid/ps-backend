<?php

namespace App\Imports;

use App\Models\MasterData\Color;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Rack;
use App\Models\MasterData\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class RackImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    public function model(array $row): Model|array|null
    {
        $warehouseId = Warehouse::where('code', $row['warehouse_code'])->value('id');

        $modelId = !empty($row['model_sku'])
            ? ProductModel::where('sku', $row['model_sku'])->value('id')
            : null;

        $colorId = !empty($row['color_code'])
            ? Color::where('code', $row['color_code'])->value('id')
            : null;

        return Rack::updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'warehouse_id' => $warehouseId,
                'model_id' => $modelId,
                'color_id' => $colorId,
            ]
        );
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'warehouse_code' => 'required|string|exists:mdx_warehouses,code',
            'model_sku' => 'nullable|string|exists:mdx_models,sku',
            'color_code' => 'nullable|string|exists:mdx_colors,code',
        ];
    }
}
