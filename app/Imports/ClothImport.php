<?php

namespace App\Imports;

use App\Models\MasterData\Cloth;
use App\Models\MasterData\Color;
use App\Models\MasterData\Factory;
use App\Models\MasterData\RollSize;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class ClothImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    public function model(array $row): Model|array|null
    {
        $factoryId = Factory::where('code', $row['factory_code'])->value('id');
        $colorId = Color::where('code', $row['color_code'])->value('id');
        $rollSizeId = RollSize::where('size', $row['roll_size'])->value('id');

        return Cloth::updateOrCreate(
            ['sequence' => $row['sequence']],
            [
                'factory_id' => $factoryId,
                'gram' => (string) $row['gram'],
                'roll_size_id' => $rollSizeId,
                'color_id' => $colorId,
                'quantity' => $row['quantity'],
            ]
        );
    }

    public function rules(): array
    {
        return [
            'factory_code' => 'required|string|exists:mdx_factories,code',
            'gram' => 'required|max:255',
            'roll_size' => 'required|exists:mdx_roll_sizes,size',
            'color_code' => 'required|string|exists:mdx_colors,code',
            'quantity' => 'required|integer',
            'sequence' => 'required|string|max:255',
        ];
    }
}
