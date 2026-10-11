<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductSeederTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['CMT01', 'MDL-001', 'BLK', 'M', 'RCK-001', 'P', 1, 10],
        ];
    }

    public function headings(): array
    {
        return ['cmt_code', 'model_sku', 'color_code', 'size_code', 'rack_code', 'type', 'number', 'qty'];
    }
}
