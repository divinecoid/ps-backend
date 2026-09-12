<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SizeTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['S', 'Small'],
            ['M', 'Medium'],
        ];
    }

    public function headings(): array
    {
        return ['code', 'name'];
    }
}
