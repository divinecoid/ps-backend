<?php

namespace App\Imports;

use App\Models\MasterData\RollSize;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Concerns\ToModel;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Maatwebsite\Excel\Concerns\WithValidation;
use Maatwebsite\Excel\Concerns\SkipsOnFailure;
use Maatwebsite\Excel\Concerns\SkipsFailures;

class RollSizeImport implements ToModel, WithHeadingRow, WithValidation, SkipsOnFailure
{
    use SkipsFailures;

    public function model(array $row): Model|array|null
    {
        return RollSize::firstOrCreate(['size' => $row['size']]);
    }

    public function rules(): array
    {
        return [
            'size' => 'required|string|max:255',
        ];
    }
}
