<?php

namespace App\Services;

use App\Models\MasterData\Product;
use App\Models\Transactions\Request as CmtRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Storage;

/**
 * Renders barcode label PDFs and keeps pre-generated copies on disk.
 *
 * Cached files are content-addressed: the file name carries a hash of the
 * exact labels that go into the PDF, so any edit to a batch/request (or to a
 * model/color/size name used on a label) produces a different name and can
 * never serve a stale PDF. Files older than the retention period are treated
 * as missing and deleted by `barcode:prune`.
 */
class BarcodePdfService
{
    public const TYPE_BATCH = 'batch';
    public const TYPE_REQUEST = 'request';
    public const KINDS = ['dozen', 'piece'];

    private const DIR = 'barcode-pdfs';
    private const MM_TO_PT = 2.8346457;

    private function disk()
    {
        return Storage::disk('local');
    }

    /** Raw PDF bytes for the given labels (shared with the on-demand /print/barcodes endpoint). */
    public function render(array $barcodes, array $dozenBarcodes, string $style = 'hangtag', array $paper = []): string
    {
        // PDF generation is CPU/memory heavy for thousands of labels.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        // Vector (SVG) QR instead of PNG: dompdf re-decodes and re-encodes
        // every raster image through GD, which dominated render time (~5x
        // slower for hundreds of labels). SVG is drawn as plain PDF paths.
        $writer = new SvgWriter();
        $renderQr = function (array $barcode) use ($writer) {
            $qrCode = new QrCode(data: $barcode['code'], size: 240, margin: 0);
            $barcode['qr'] = $writer->write($qrCode)->getDataUri();
            return $barcode;
        };

        if ($style === 'rack') {
            // Single rack label: the PDF page IS the label, 5cm wide x 4cm
            // tall including its border (client spec for the shelf stickers).
            $paperWidthMm = 50;
            $paperHeightMm = 40;
        } else {
            // Real A4 paper. Cards use fixed mm sizing (see
            // barcodes.blade.php) so dompdf's page-break math lines up with
            // A4's real height.
            $paperWidthMm = 210;
            $paperHeightMm = 297;
        }

        return Pdf::loadView('print.barcodes', [
            'barcodes' => collect(array_map($renderQr, $barcodes)),
            'dozenBarcodes' => collect(array_map($renderQr, $dozenBarcodes)),
            'paper' => $paper,
            'style' => $style,
        ])->setPaper([0, 0, $paperWidthMm * self::MM_TO_PT, $paperHeightMm * self::MM_TO_PT])->output();
    }

    /**
     * The labels to print for a source, exactly as the frontend used to build
     * them: ['barcodes' => piece labels, 'dozenBarcodes' => dozen labels, 'style' => ...].
     */
    public function payload(string $type, string $id, string $kind): array
    {
        [$pieces, $dozens, $style] = $type === self::TYPE_BATCH
            ? $this->batchLabels($id)
            : $this->requestLabels($id);

        return [
            'barcodes' => $kind === 'piece' ? $pieces : [],
            'dozenBarcodes' => $kind === 'dozen' ? $dozens : [],
            'style' => $style,
        ];
    }

    /** Relative storage path of the cached PDF for a payload, or null when there is nothing to print. */
    public function ensure(string $type, string $id, string $kind): ?string
    {
        $payload = $this->payload($type, $id, $kind);
        $dir = self::DIR . "/{$type}/{$id}";

        if (!$payload['barcodes'] && !$payload['dozenBarcodes']) {
            // Nothing left to print (e.g. batch deleted): drop old files.
            $this->disk()->deleteDirectory($dir);
            return null;
        }

        $path = "{$dir}/{$kind}-" . sha1(json_encode($payload)) . '.pdf';
        if ($this->isFresh($path)) {
            return $path;
        }

        $pdf = $this->render($payload['barcodes'], $payload['dozenBarcodes'], $payload['style']);
        $this->disk()->put($path, $pdf);

        // Older versions of this kind (before an edit, or expired) are obsolete.
        foreach ($this->disk()->files($dir) as $file) {
            if ($file !== $path && str_starts_with(basename($file), "{$kind}-")) {
                $this->disk()->delete($file);
            }
        }

        return $path;
    }

    /** Generate (or refresh) every kind for a source — used by the background job. */
    public function generateAll(string $type, string $id): void
    {
        foreach (self::KINDS as $kind) {
            $this->ensure($type, $id, $kind);
        }
    }

    public function response(string $path, string $filename)
    {
        return $this->disk()->response($path, $filename, ['Content-Type' => 'application/pdf'], 'inline');
    }

    /** Delete cached PDFs past the retention period. Returns how many were removed. */
    public function prune(): int
    {
        $cutoff = now()->subDays(config('barcode.retention_days', 30))->timestamp;
        $removed = 0;

        foreach ($this->disk()->allFiles(self::DIR) as $file) {
            if ($this->disk()->lastModified($file) < $cutoff) {
                $this->disk()->delete($file);
                $removed++;
            }
        }
        foreach ($this->disk()->allDirectories(self::DIR) as $dir) {
            if (!$this->disk()->allFiles($dir) && !$this->disk()->directories($dir)) {
                $this->disk()->deleteDirectory($dir);
            }
        }

        return $removed;
    }

    private function isFresh(string $path): bool
    {
        return $this->disk()->exists($path)
            && $this->disk()->lastModified($path) >= now()->subDays(config('barcode.retention_days', 30))->timestamp;
    }

    /** Old-stock batch (compact stickers): [pieces, dozens, style]. */
    private function batchLabels(string $batchId): array
    {
        $products = Product::with(['model', 'color', 'size'])
            ->where('stock_batch_id', $batchId)
            ->orderByDesc('created_at')
            ->limit(50000)
            ->get();

        $label = fn(Product $p, ?string $code = null) => [
            'code' => $code ?? $p->barcode,
            'serial_number' => $p->series ?: '',
            'cutting' => $p->model?->name ?: '',
            'sizes' => $p->size?->name ?: '',
            'color' => $p->color?->name ?: '',
        ];
        $groupOf = function (Product $p) {
            $parts = explode('|', (string) $p->barcode);
            return $parts[count($parts) - 2] ?? null;
        };

        $isDozenBatch = $products->contains(fn($p) => $groupOf($p) === 'D');
        if ($isDozenBatch) {
            // A dozen batch already holds its D and P barcodes as separate rows.
            return [
                $products->filter(fn($p) => $groupOf($p) === 'P')->map(fn($p) => $label($p))->values()->all(),
                $products->filter(fn($p) => $groupOf($p) === 'D')->map(fn($p) => $label($p))->values()->all(),
                'compact',
            ];
        }

        // Every product is one piece tag; dozen tags are derived: one per full
        // 12 pieces of the same model/color/size.
        $dozens = [];
        $products
            ->groupBy(fn($p) => "{$p->model?->name}|{$p->color?->name}|{$p->size?->name}")
            ->each(function ($items) use (&$dozens, $label) {
                $first = $items->first();
                $parts = explode('|', (string) $first->barcode);
                $base = count($parts) >= 5 ? implode('|', array_slice($parts, 0, 5)) : $first->barcode;
                for ($i = 1; $i <= intdiv($items->count(), 12); $i++) {
                    $dozens[] = $label($first, "{$base}|D|{$i}");
                }
            });

        return [$products->map(fn($p) => $label($p))->values()->all(), $dozens, 'compact'];
    }

    /** CMT request (hangtag): [pieces, dozens, style]. */
    private function requestLabels(string $requestId): array
    {
        $request = CmtRequest::with(['request_detail.model', 'request_detail.size', 'request_detail.cutting.clothes.color'])
            ->find($requestId);
        if (!$request) {
            return [[], [], 'hangtag'];
        }

        $pieces = [];
        $dozens = [];
        foreach ($request->request_detail as $detail) {
            $row = [
                'serial_number' => $request->serial_number,
                'model' => $detail->model->name ?? null,
                'cutting' => $detail->cutting->clothes->color->code ?? null,
                'sizes' => $detail->size->code ?? null,
            ];
            $count = (int) $detail->req_qty;
            for ($i = 1; $i <= intdiv($count, 12); $i++) {
                $dozens[] = $row + ['code' => "{$detail->barcode}|D|{$i}"];
            }
            for ($i = 1; $i <= $count; $i++) {
                $pieces[] = $row + ['code' => "{$detail->barcode}|P|{$i}"];
            }
        }

        return [$pieces, $dozens, 'hangtag'];
    }
}
