<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class CMTTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['CMT-01', 'CV Konveksi Jaya', 'Budi', '081234567890', 'Jl. Industri No. 1, Bandung'],
        ];
    }

    public function headings(): array
    {
        return ['code', 'name', 'contact_person', 'phone', 'address'];
    }
}
