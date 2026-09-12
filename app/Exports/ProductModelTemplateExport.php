<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductModelTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['MDL-001', 'Kemeja Polos', 'BLK,WHT', 'S,M'],
        ];
    }

    public function headings(): array
    {
        return ['sku', 'name', 'color_codes', 'size_codes'];
    }
}
