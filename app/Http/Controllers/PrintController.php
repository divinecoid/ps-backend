<?php

namespace App\Http\Controllers;

use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function barcodes(Request $request)
    {
        $data = $request->validate([
            'barcodes' => 'present|array',
            'barcodes.*.code' => 'required|string',
            'barcodes.*.serial_number' => 'nullable|string',
            'barcodes.*.cutting' => 'nullable|string',
            'barcodes.*.sizes' => 'nullable|string',
            'barcodes.*.color' => 'nullable|string',
            'barcodes.*.model' => 'nullable|string',
            'dozenBarcodes' => 'nullable|array',
            'dozenBarcodes.*.code' => 'required_with:dozenBarcodes|string',
            'dozenBarcodes.*.serial_number' => 'nullable|string',
            'dozenBarcodes.*.cutting' => 'nullable|string',
            'dozenBarcodes.*.sizes' => 'nullable|string',
            'dozenBarcodes.*.color' => 'nullable|string',
            'dozenBarcodes.*.model' => 'nullable|string',
            'paper.width' => 'required|numeric|min:10',
            'paper.height' => 'required|numeric|min:10',
            'style' => 'nullable|in:hangtag,compact',
        ]);

        if (empty($data['barcodes']) && empty($data['dozenBarcodes'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada barcode untuk dicetak',
                'data' => null,
            ], 422);
        }

        $writer = new PngWriter();
        $renderQr = function (array $barcode) use ($writer) {
            $qrCode = new QrCode(
                data: $barcode['code'],
                size: 240,
                margin: 0,
            );
            $result = $writer->write($qrCode);
            $barcode['qr'] = $result->getDataUri();
            return $barcode;
        };

        $barcodes = collect(array_map($renderQr, $data['barcodes'] ?? []));
        $dozenBarcodes = collect(array_map($renderQr, $data['dozenBarcodes'] ?? []));
        $style = $data['style'] ?? 'hangtag';

        $paperWidthMm = $data['paper']['width'];
        $paperHeightMm = $data['paper']['height'];

        if ($style === 'compact') {
            // Real A4 paper — printed on an actual printer, not a giant
            // continuous roll. Cards use fixed mm sizing (see
            // barcodes.blade.php) so dompdf's page-break math lines up
            // cleanly with A4's real height instead of guessing from px,
            // which is what caused rows to break early and leave a blank
            // gap before the next page.
            $paperWidthMm = 210;
            $paperHeightMm = 297;
        }

        $pdf = Pdf::loadView('print.barcodes', [
            'barcodes' => $barcodes,
            'dozenBarcodes' => $dozenBarcodes,
            'paper' => $data['paper'],
            'style' => $style,
        ])->setPaper([0, 0, $paperWidthMm * 2.8346457, $paperHeightMm * 2.8346457]);

        return $pdf->stream('barcodes.pdf');
    }
}
