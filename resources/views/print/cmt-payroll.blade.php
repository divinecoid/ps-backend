@php
    $rupiah = fn($v) => 'Rp ' . number_format((float) $v, 0, ',', '.');
    $cmt = $payroll->cmt;
    $statusLabel = ['draft' => 'Draft', 'approved' => 'Disetujui', 'paid' => 'Lunas'][$payroll->status] ?? $payroll->status;
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Slip Gaji CMT {{ $cmt?->code }}</title>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm 16mm; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #111; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #555; }
        .draft { color: #b45309; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        .head { border-bottom: 2px solid #111; padding-bottom: 6px; margin-bottom: 10px; }
        .info td { padding: 2px 0; vertical-align: top; }
        .info .key { width: 26mm; color: #555; }
        .items { margin-top: 10px; }
        .items thead { display: table-header-group; }
        .items tr { page-break-inside: avoid; }
        .items th, .items td { border: 1px solid #999; padding: 4px 5px; vertical-align: top; word-wrap: break-word; }
        .items th { background: #eee; text-align: left; }
        .items .num { text-align: right; white-space: nowrap; }
        .items .center { text-align: center; }
        .total td { font-weight: bold; font-size: 11px; background: #f5f5f5; }
        .sign { margin-top: 14mm; page-break-inside: avoid; }
        .sign td { width: 33.33%; text-align: center; vertical-align: bottom; padding: 0 6mm; }
        .sign .space { height: 22mm; }
        .sign .line { border-top: 1px solid #111; padding-top: 3px; }
        .printed { position: fixed; bottom: -10mm; left: 0; right: 0; font-size: 8px; color: #777; }
        .printed .page:after { content: counter(page); }
    </style>
</head>
<body>
    <div class="printed">Dicetak {{ $printedAt }} oleh {{ $printedBy }} · Halaman <span class="page"></span></div>

    <div class="head">
        <h1>Slip Gaji CMT</h1>
        <div class="muted">
            Periode {{ $payroll->period_start->format('d/m/Y') }} s/d {{ $payroll->period_end->format('d/m/Y') }}
            @if ($payroll->status === 'draft') · <span class="draft">DRAFT — belum disetujui</span> @endif
        </div>
    </div>

    <table class="info">
        <tr>
            <td class="key">CMT</td><td>: <strong>{{ $cmt?->code }} - {{ $cmt?->name ?? '-' }}</strong></td>
            <td class="key">Status</td><td>: {{ $statusLabel }}</td>
        </tr>
        <tr>
            <td class="key">Kontak</td><td>: {{ trim(($cmt?->contact_person ?? '') . ' ' . ($cmt?->phone ?? '')) ?: '-' }}</td>
            <td class="key">Pembayaran</td>
            <td>: {{ $payroll->is_paid ? 'Sudah dibayar' . ($payroll->paid_at ? ' (' . $payroll->paid_at->timezone('Asia/Jakarta')->format('d/m/Y') . ')' : '') : 'Belum dibayar' }}</td>
        </tr>
        <tr>
            <td class="key">Alamat</td><td colspan="3">: {{ $cmt?->address ?: '-' }}</td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%" class="center">No</th>
                <th style="width: 18%">Seri</th>
                <th style="width: 23%">Model</th>
                <th style="width: 9%" class="num">Qty (pcs)</th>
                <th style="width: 13%" class="center">Lusin</th>
                <th style="width: 15%" class="num">Ongkos</th>
                <th style="width: 17%" class="num">Jumlah</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($rows as $i => $row)
                <tr>
                    <td class="center">{{ $i + 1 }}</td>
                    <td>{{ $row['serial_number'] ?? '-' }}</td>
                    <td>{{ $row['model'] ?? '-' }}</td>
                    <td class="num">{{ number_format($row['qty'], 0, ',', '.') }}</td>
                    <td class="center">
                        @if ($row['qty_lusin'] !== null)
                            {{ $row['qty_dozen'] }} lsn{{ $row['qty_piece'] > 0 ? ' ' . $row['qty_piece'] . ' pcs' : '' }}
                        @else
                            -
                        @endif
                    </td>
                    <td class="num">{{ $rupiah($row['unit_fee']) }}<br><span class="muted">/{{ $row['qty_lusin'] !== null ? 'lusin' : 'pcs' }}</span></td>
                    <td class="num">{{ $rupiah($row['amount']) }}</td>
                </tr>
            @endforeach
            <tr class="total">
                <td colspan="3">TOTAL</td>
                <td class="num">{{ number_format((int) $payroll->total_pcs, 0, ',', '.') }}</td>
                <td class="center">{{ $totalDozen }} lsn{{ $totalPiece > 0 ? ' ' . $totalPiece . ' pcs' : '' }}</td>
                <td></td>
                <td class="num">{{ $rupiah($payroll->total_amount) }}</td>
            </tr>
        </tbody>
    </table>

    <table class="sign">
        <tr><td>Dibuat oleh</td><td>Disetujui</td><td>Diterima (CMT)</td></tr>
        <tr><td class="space"></td><td class="space"></td><td class="space"></td></tr>
        <tr><td><div class="line">&nbsp;</div></td><td><div class="line">&nbsp;</div></td><td><div class="line">{{ $cmt?->name }}</div></td></tr>
    </table>
</body>
</html>
