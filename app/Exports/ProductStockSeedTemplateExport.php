<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;

class ProductStockSeedTemplateExport implements FromArray, WithHeadings
{
    public function array(): array
    {
        return [
            ['CMT01', 'MDL-001', 'BLK', 'S', 'A1', 'P', 1, 12],
        ];
    }

    public function headings(): array
    {
        return ['kode_cmt', 'sku_model', 'kode_warna', 'kode_ukuran', 'kode_rak', 'tipe', 'nomor_urut', 'qty'];
    }
}
