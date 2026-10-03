<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 4mm;
        }
        * {
            box-sizing: border-box;
        }
        body {
            font-family: DejaVu Sans, sans-serif;
            margin: 0;
            padding: 0;
        }
        table.grid {
            width: 100%;
            border-collapse: separate;
            border-spacing: 4px;
            table-layout: fixed;
        }
        table.grid td {
            width: 20%;
            vertical-align: top;
            padding: 0;
        }
        .cell {
            padding: 10px 6px;
            text-align: center;
            border-radius: 12px;
            border-width: 2px;
            border-style: solid;
            page-break-inside: avoid;
        }
        .cell-dozen {
            background-color: #f7fdf9;
            border-color: #d3f5df;
        }
        .cell-piece {
            background-color: #f7fafd;
            border-color: #d6e8fa;
            padding-top: 240px;
        }
        /* Fixed mm sizing (not px) so the backend can compute an exact page
         * height for this row count — no leftover blank space at the end. */
        .cell-piece-compact {
            background-color: #f7fafd;
            border-color: #d6e8fa;
            padding: 3mm 2mm;
        }
        .qr {
            width: 150px;
            height: 150px;
        }
        .cell-piece-compact .qr {
            width: 32.5mm;
            height: 32.5mm;
        }
        .label {
            font-size: 9px;
            color: #000;
            white-space: nowrap;
            margin-top: 4px;
        }
        .cell-piece-compact .label {
            font-size: 7px;
            white-space: normal;
            word-break: break-word;
            line-height: 1.2;
            margin-top: 1mm;
            height: 6mm;
            overflow: hidden;
        }
        /* Rack shelf labels — deliberately plain/high-contrast (not the
         * rounded colorful hangtag card): a sharp-cornered tag meant to be
         * read at a glance from across an aisle. Two variants: a single big
         * tag ("rack") for one-off printing, and a dense A4 grid
         * ("rack-bulk", ~20 tags/page) for printing many racks at once. */
        .cell-rack-single {
            border: 1.5px solid #111;
            border-radius: 4px;
            padding: 10mm 8mm;
            max-width: 80mm;
            margin: 0 auto;
        }
        .cell-rack-single .qr {
            width: 45mm;
            height: 45mm;
        }
        .cell-rack-single .rack-label {
            font-size: 16px;
            font-weight: bold;
            letter-spacing: 0.3px;
            margin-top: 3mm;
            white-space: normal;
            word-break: break-word;
            line-height: 1.25;
        }
        .cell-rack-single .rack-sublabel {
            font-size: 10px;
            color: #555;
            margin-top: 1.5mm;
            white-space: normal;
            word-break: break-word;
        }
        .cell-rack-bulk {
            background-color: #fff;
            border: 1px solid #111;
            border-radius: 2mm;
            padding: 3mm 2mm;
        }
        .cell-rack-bulk .qr {
            width: 36mm;
            height: 36mm;
        }
        .cell-rack-bulk .rack-label {
            font-size: 10px;
            font-weight: bold;
            letter-spacing: 0.2px;
            margin-top: 2mm;
            white-space: normal;
            word-break: break-word;
            line-height: 1.2;
            max-height: 8.5mm;
            overflow: hidden;
        }
        .cell-rack-bulk .rack-sublabel {
            font-size: 7px;
            color: #555;
            margin-top: 0.5mm;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    </style>
</head>
<body>
    <table class="grid">
        @foreach ($dozenBarcodes->chunk(5) as $row)
            <tr>
                @foreach ($row as $code)
                    <td>
                        <div class="cell cell-dozen">
                            <img class="qr" src="{{ $code['qr'] }}">
                            <div class="label">{{ $code['serial_number'] }}{{ !empty($code['model']) ? ' - ' . $code['model'] : '' }} - {{ $code['cutting'] }} - {{ $code['sizes'] }}</div>
                        </div>
                    </td>
                @endforeach
                @for ($i = $row->count(); $i < 5; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
        @php
            $styleName = $style ?? 'hangtag';
            $isRackSingle = $styleName === 'rack';
            $isRackBulk = $styleName === 'rack-bulk';
            $cols = $isRackSingle ? 1 : ($isRackBulk ? 4 : 5);
            $cellClass = $isRackSingle ? 'cell-rack-single' : ($isRackBulk ? 'cell-rack-bulk' : ($styleName === 'compact' ? 'cell-piece-compact' : 'cell-piece'));
        @endphp
        @foreach ($barcodes->chunk($cols) as $row)
            <tr>
                @foreach ($row as $code)
                    <td @if ($cols !== 5) style="width: {{ 100 / $cols }}%" @endif>
                        <div class="cell {{ $cellClass }}">
                            <img class="qr" src="{{ $code['qr'] }}">
                            @if ($isRackSingle || $isRackBulk)
                                <div class="rack-label">{{ $code['label'] ?? $code['code'] }}</div>
                                @if (!empty($code['sublabel']))
                                    <div class="rack-sublabel">{{ $code['sublabel'] }}</div>
                                @endif
                            @else
                                <div class="label">{{ $code['serial_number'] }}{{ !empty($code['model']) ? ' - ' . $code['model'] : '' }} - {{ $code['cutting'] }}{{ !empty($code['color']) ? ' - ' . $code['color'] : '' }} - {{ $code['sizes'] }}</div>
                            @endif
                        </div>
                    </td>
                @endforeach
                @for ($i = $row->count(); $i < $cols; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
    </table>
</body>
</html>
