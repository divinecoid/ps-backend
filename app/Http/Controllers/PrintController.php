<?php

namespace App\Http\Controllers;

use App\Services\BarcodePdfService;
use Illuminate\Http\Request;

class PrintController extends Controller
{
    public function barcodes(Request $request, BarcodePdfService $service)
    {
        $data = $request->validate([
            'barcodes' => 'present|array',
            'barcodes.*.code' => 'required|string',
            'barcodes.*.serial_number' => 'nullable|string',
            'barcodes.*.cutting' => 'nullable|string',
            'barcodes.*.sizes' => 'nullable|string',
            'barcodes.*.color' => 'nullable|string',
            'barcodes.*.model' => 'nullable|string',
            'barcodes.*.label' => 'nullable|string',
            'barcodes.*.sublabel' => 'nullable|string',
            'barcodes.*.detail' => 'nullable|string',
            'dozenBarcodes' => 'nullable|array',
            'dozenBarcodes.*.code' => 'required_with:dozenBarcodes|string',
            'dozenBarcodes.*.serial_number' => 'nullable|string',
            'dozenBarcodes.*.cutting' => 'nullable|string',
            'dozenBarcodes.*.sizes' => 'nullable|string',
            'dozenBarcodes.*.color' => 'nullable|string',
            'dozenBarcodes.*.model' => 'nullable|string',
            'paper.width' => 'required|numeric|min:10',
            'paper.height' => 'required|numeric|min:10',
            'style' => 'nullable|in:hangtag,compact,rack,rack-bulk',
        ]);

        if (empty($data['barcodes']) && empty($data['dozenBarcodes'])) {
            return response()->json([
                'success' => false,
                'message' => 'Tidak ada barcode untuk dicetak',
                'data' => null,
            ], 422);
        }

        return response(
            $service->render(
                $data['barcodes'] ?? [],
                $data['dozenBarcodes'] ?? [],
                $data['style'] ?? 'hangtag',
                $data['paper'],
            ),
            200,
            ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="barcodes.pdf"']
        );
    }

    /** Old-stock batch PDF: served from the pre-generated file, rendered now if missing/expired. */
    public function batchPdf(BarcodePdfService $service, string $batchId, string $kind)
    {
        if ($batchId === 'none') {
            return $this->noLabels();
        }

        return $this->cachedPdf($service, BarcodePdfService::TYPE_BATCH, $batchId, $kind);
    }

    /** CMT request PDF: served from the pre-generated file, rendered now if missing/expired. */
    public function requestPdf(BarcodePdfService $service, string $id, string $kind)
    {
        return $this->cachedPdf($service, BarcodePdfService::TYPE_REQUEST, $id, $kind);
    }

    private function cachedPdf(BarcodePdfService $service, string $type, string $id, string $kind)
    {
        if (!in_array($kind, BarcodePdfService::KINDS, true) || !preg_match('/^[A-Za-z0-9-]+$/', $id)) {
            return $this->noLabels();
        }

        $path = $service->ensure($type, $id, $kind);

        return $path ? $service->response($path, "barcode-{$kind}.pdf") : $this->noLabels();
    }

    private function noLabels()
    {
        return response()->json([
            'success' => false,
            'message' => 'Tidak ada barcode untuk dicetak',
            'data' => null,
        ], 422);
    }
}
