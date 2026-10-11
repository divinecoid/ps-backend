<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class RollSizeTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['150cm'],
            ['160cm'],
        ];
    }

    public function headings(): array
    {
        return ['size'];
    }
}
