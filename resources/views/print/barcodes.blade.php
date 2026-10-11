<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: {{ ($style ?? 'hangtag') === 'rack' ? '0' : '4mm' }};
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
            border-spacing: {{ in_array(($style ?? 'hangtag'), ['rack', 'rack-bulk']) ? '0' : '4px' }};
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
            padding: 3mm 2mm;
        }
        /* Hangtag is printed on real A4 (210x297mm, 4mm margins): 5 columns
         * x exactly 3 rows per page. Fixed mm sizing so each card is ~94.7mm
         * tall (51.8 + 30 + 1 + 9 + 2 + border) and 3 rows + spacing fill the
         * 289mm printable height (~0.4mm slack, measured on dompdf) without
         * pushing a row to the next page. If you change any size above,
         * re-render and check there's still no blank strip at the bottom. */
        .cell-piece {
            background-color: #f7fafd;
            border-color: #d6e8fa;
            padding: 51.8mm 2mm 2mm;
        }
        /* Fixed mm sizing (not px) so the backend can compute an exact page
         * height for this row count — no leftover blank space at the end. */
        .cell-piece-compact {
            background-color: #f7fafd;
            border-color: #d6e8fa;
            padding: 3mm 2mm;
        }
        .qr {
            width: 30mm;
            height: 30mm;
        }
        /* Hangtag dozen cards: 5 columns x exactly 6 rows (30/page) on A4. The
         * default card (3mm vertical padding) is ~47.1mm tall; 6 of them just
         * overflow the 289mm printable height, so dompdf pushed the 6th row to
         * the next page and left ~5cm blank under row 5. 2.8mm padding makes the
         * card ~46.5mm so 6 rows + spacing fit (measured on dompdf: 2.9mm still
         * fits, 2.95mm doesn't; 2.8 leaves ~1.5mm slack). Re-render if changed. */
        .cell-dozen-hangtag {
            padding: 2.8mm 2mm;
        }
        .cell-dozen-compact {
            padding: 3mm 2mm;
        }
        .cell-piece-compact .qr,
        .cell-dozen-compact .qr {
            width: 32.5mm;
            height: 32.5mm;
        }
        .label {
            font-size: 7px;
            color: #000;
            white-space: normal;
            word-break: break-word;
            line-height: 1.2;
            margin-top: 1mm;
            height: 9mm;
            overflow: hidden;
        }
        .cell-piece-compact .label,
        .cell-dozen-compact .label {
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
         * read at a glance from across an aisle. Both variants use the same
         * 50 x 40mm card: a single one-off label ("rack", the PDF page IS the
         * label) and an A4 sheet of them ("rack-bulk": 4 columns x 7 rows =
         * 28 labels/page, ~0.5mm between columns, none between rows). */
        /* Single rack label = exactly 50 x 40mm INCLUDING the border. dompdf
         * sizes boxes as content-box, so content = total - border - padding:
         * width 50 - 2*0.3 - 2*2 = 45.4mm, height 40 - 2*0.3 - 2 = 37.4mm
         * (minus a hair of slack so it never spills onto a 2nd page). */
        .cell-rack-single {
            box-sizing: content-box;
            width: 45.4mm;
            height: 37.1mm;
            border: 0.3mm solid #111;
            border-radius: 0;
            padding: 2mm 2mm 0;
            margin: 0;
            overflow: hidden;
        }
        .cell-rack-single .qr {
            width: 17.5mm;
            height: 17.5mm;
        }
        .cell-rack-single .rack-label {
            font-size: 9px;
            font-weight: bold;
            margin-top: 1mm;
            white-space: normal;
            word-break: break-word;
            line-height: 1.15;
            max-height: 6mm;
            overflow: hidden;
        }
        /* Every text block is height-capped (2 lines max, rest is clipped):
         * dompdf won't clip the card itself, so unbounded text would push
         * the label onto a 2nd page. Budget: 2 + 17.5 + 1 + 6 + 2*(0.5+4.4)
         * = 36.3mm of the 37.1mm available. */
        .cell-rack-single .rack-sublabel,
        .cell-rack-single .rack-detail {
            font-size: 6.5px;
            color: #333;
            margin-top: 0.5mm;
            white-space: normal;
            word-break: break-word;
            line-height: 1.15;
            max-height: 4.4mm;
            overflow: hidden;
        }
        .cell-rack-single .rack-detail {
            font-weight: bold;
            color: #000;
        }
    </style>
</head>
<body>
    <table class="grid">
        @foreach ($dozenBarcodes->chunk(5) as $row)
            <tr>
                @foreach ($row as $code)
                    <td>
                        <div class="cell cell-dozen {{ ($style ?? 'hangtag') === 'compact' ? 'cell-dozen-compact' : 'cell-dozen-hangtag' }}">
                            <img class="qr" src="{{ $code['qr'] }}">
                            <div class="label">{{ $code['serial_number'] }}{{ !empty($code['model']) ? ' - ' . $code['model'] : '' }} - {{ $code['cutting'] }}{{ !empty($code['color']) ? ' - ' . $code['color'] : '' }} - {{ $code['sizes'] }}</div>
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
            $cellClass = ($isRackSingle || $isRackBulk) ? 'cell-rack-single' : ($styleName === 'compact' ? 'cell-piece-compact' : 'cell-piece');
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
                                @if (!empty($code['detail']))
                                    <div class="rack-detail">{{ $code['detail'] }}</div>
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
