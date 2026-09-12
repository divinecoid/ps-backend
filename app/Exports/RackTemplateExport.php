<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RackTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['RAK-01', 'Rak A1', 'GDG-01', '', ''],
        ];
    }

    public function headings(): array
    {
        return ['code', 'name', 'warehouse_code', 'model_sku', 'color_code'];
    }
}
