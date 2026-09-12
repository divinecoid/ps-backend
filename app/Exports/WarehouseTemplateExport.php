<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class WarehouseTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['GDG-01', 'Gudang Utama', 1, 'BIG'],
            ['GDG-02', 'Gudang Kecil', 2, 'SMALL'],
        ];
    }

    public function headings(): array
    {
        return ['code', 'name', 'priority', 'type'];
    }
}
