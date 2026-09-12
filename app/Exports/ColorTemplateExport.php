<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ColorTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['BLK', 'Black'],
            ['WHT', 'White'],
        ];
    }

    public function headings(): array
    {
        return ['code', 'name'];
    }
}
