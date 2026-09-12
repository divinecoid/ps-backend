<?php

namespace App\Imports;

use App\Models\MasterData\Color;
use App\Models\MasterData\ProductModel;
use App\Models\MasterData\Size;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class ProductModelImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    private function splitCodes(?string $value): array
    {
        if (empty($value)) {
            return [];
        }
        return array_filter(array_map('trim', explode(',', $value)));
    }

    public function model(array $row): Model|array|null
    {
        $colorCodes = $this->splitCodes($row['color_codes'] ?? null);
        $sizeCodes = $this->splitCodes($row['size_codes'] ?? null);

        $colorIds = Color::whereIn('code', $colorCodes)->pluck('id');
        $sizeIds = Size::whereIn('code', $sizeCodes)->pluck('id');

        $productModel = ProductModel::updateOrCreate(
            ['sku' => $row['sku']],
            ['name' => $row['name']]
        );

        $productModel->colors()->sync($colorIds);
        $productModel->sizes()->sync($sizeIds);

        return $productModel;
    }

    public function rules(): array
    {
        return [
            'sku' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'color_codes' => 'nullable|string',
            'size_codes' => 'nullable|string',
        ];
    }
}
