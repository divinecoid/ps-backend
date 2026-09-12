<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class FactoryTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['FCT-01', 'Pabrik Utama'],
            ['FCT-02', 'Pabrik Cadangan'],
        ];
    }

    public function headings(): array
    {
        return ['code', 'name'];
    }
}
