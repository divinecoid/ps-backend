<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ClothTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['FCT-01', '180', '150cm', 'BLK', 100, 'SEQ-001'],
        ];
    }

    public function headings(): array
    {
        return ['factory_code', 'gram', 'roll_size', 'color_code', 'quantity', 'sequence'];
    }
}
