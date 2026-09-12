<?php

namespace App\Imports;

use App\Models\MasterData\Warehouse;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class WarehouseImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    public function model(array $row)
    {
        return tap(Warehouse::updateOrCreate(
            ['code' => $row['code']],
            [
                'name' => $row['name'],
                'priority' => $row['priority'],
                'type' => $row['type'] ?? null,
            ]
        ));
    }

    public function rules(): array
    {
        return [
            'code' => 'required|string|max:255',
            'name' => 'required|string|max:255',
            'priority' => 'required|integer',
            'type' => 'nullable|string|in:BIG,SMALL',
        ];
    }
}
