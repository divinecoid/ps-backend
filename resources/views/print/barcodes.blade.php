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
        @php $pieceClass = ($style ?? 'hangtag') === 'compact' ? 'cell-piece-compact' : 'cell-piece'; @endphp
        @foreach ($barcodes->chunk(5) as $row)
            <tr>
                @foreach ($row as $code)
                    <td>
                        <div class="cell {{ $pieceClass }}">
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
    </table>
</body>
</html>
