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
        .grid {
            width: 100%;
        }
        .cell {
            display: inline-block;
            width: 19%;
            margin: 0.5%;
            padding: 10px 6px;
            text-align: center;
            border-radius: 12px;
            border-width: 2px;
            border-style: solid;
            vertical-align: top;
        }
        .cell-dozen {
            background-color: #f0fdf4;
            border-color: #bbf7d0;
        }
        .cell-piece {
            background-color: #eff6ff;
            border-color: #bfdbfe;
        }
        .qr {
            width: 90px;
            height: 90px;
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
    <div class="grid">
        @foreach ($dozenBarcodes as $code)
            <div class="cell cell-dozen">
                <img class="qr" src="{{ $code['qr'] }}">
                <div class="label">{{ $code['serial_number'] }} - {{ $code['cutting'] }} - {{ $code['sizes'] }}</div>
            </div>
        @endforeach
        @foreach ($barcodes as $code)
            <div class="cell cell-piece">
                <img class="qr" src="{{ $code['qr'] }}">
                <div class="label">{{ $code['serial_number'] }} - {{ $code['cutting'] }} - {{ $code['sizes'] }}</div>
            </div>
        @endforeach
    </div>
</body>
</html>
