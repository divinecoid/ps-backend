<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class OnlineOutboundExport implements FromArray, WithHeadings, ShouldAutoSize
{
    public function __construct(private readonly array $report)
    {
    }

    public function array(): array
    {
        $rows = array_map(fn($r) => [
            $r['marketplace'], $r['store'], $r['sku'], $r['model'], $r['color'], $r['size'], $r['qty'], $r['orders'],
        ], $this->report['rows']);
        $rows[] = ['TOTAL', '', '', '', '', '', $this->report['summary']['qty'], $this->report['summary']['orders']];

        return $rows;
    }

    public function headings(): array
    {
        return ['Marketplace', 'Nama Toko', 'SKU', 'Model', 'Warna', 'Ukuran', 'Qty (pcs)', 'Jumlah Pesanan'];
    }
}
