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
        .qr {
            width: 150px;
            height: 150px;
        }
        .label {
            font-size: 9px;
            color: #000;
            white-space: nowrap;
            margin-top: 4px;
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
                            <div class="label">{{ $code['serial_number'] }} - {{ $code['cutting'] }} - {{ $code['sizes'] }}</div>
                        </div>
                    </td>
                @endforeach
                @for ($i = $row->count(); $i < 5; $i++)
                    <td></td>
                @endfor
            </tr>
        @endforeach
        @foreach ($barcodes->chunk(5) as $row)
            <tr>
                @foreach ($row as $code)
                    <td>
                        <div class="cell cell-piece">
                            <img class="qr" src="{{ $code['qr'] }}">
                            <div class="label">{{ $code['serial_number'] }} - {{ $code['cutting'] }} - {{ $code['sizes'] }}</div>
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
