<?php

namespace App\Services;

use App\Jobs\RenderBarcodePdfJob;
use App\Models\MasterData\Product;
use App\Models\Transactions\Request as CmtRequest;
use Barryvdh\DomPDF\Facade\Pdf;
use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\SvgWriter;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
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
    // Bump when print/barcodes.blade.php changes so cached PDFs are re-rendered.
    private const TEMPLATE_VERSION = 4;
    private const MM_TO_PT = 2.8346457;
    // A queued render nobody picked up after this long (no queue worker, or it
    // is busy with another long job) is run by the web server instead.
    private const QUEUE_PATIENCE_SECONDS = 15;
    // A render that stopped reporting for this long is considered dead.
    private const STALLED_SECONDS = 300;

    private function disk()
    {
        return Storage::disk('local');
    }

    /** Raw PDF bytes for the given labels (shared with the on-demand /print/barcodes endpoint). */
    public function render(array $barcodes, array $dozenBarcodes, string $style = 'hangtag', array $paper = [], ?callable $progress = null): string
    {
        // PDF generation is CPU/memory heavy for thousands of labels.
        @ini_set('memory_limit', '1024M');
        @set_time_limit(0);

        // Vector (SVG) QR instead of PNG: dompdf re-decodes and re-encodes
        // every raster image through GD, which dominated render time (~5x
        // slower for hundreds of labels). SVG is drawn as plain PDF paths.
        // Progress: QR codes are the first 50%, page layout the rest.
        $writer = new SvgWriter();
        $total = max(1, count($barcodes) + count($dozenBarcodes));
        $done = 0;
        $renderQr = function (array $barcode) use ($writer, $progress, $total, &$done) {
            $qrCode = new QrCode(data: $barcode['code'], size: 240, margin: 0);
            $barcode['qr'] = $writer->write($qrCode)->getDataUri();
            $done++;
            if ($progress) {
                $progress((int) floor(50 * $done / $total));
            }
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

        $pdf = Pdf::loadView('print.barcodes', [
            'barcodes' => collect(array_map($renderQr, $barcodes)),
            'dozenBarcodes' => collect(array_map($renderQr, $dozenBarcodes)),
            'paper' => $paper,
            'style' => $style,
        ])->setPaper([0, 0, $paperWidthMm * self::MM_TO_PT, $paperHeightMm * self::MM_TO_PT]);

        if ($progress) {
            $pages = $this->estimatePages(count($barcodes), count($dozenBarcodes), $style);
            $rendered = 0;
            $pdf->getDomPDF()->setCallbacks([[
                'event' => 'end_page_render',
                'f' => function () use ($progress, $pages, &$rendered) {
                    $rendered++;
                    $progress(min(99, 55 + (int) floor(44 * $rendered / $pages)));
                },
            ]]);
            $progress(52);
        }

        return $pdf->output();
    }

    /** Page count of the A4 sheets, used only to scale the progress bar. */
    private function estimatePages(int $pieces, int $dozens, string $style): int
    {
        $perPage = match ($style) {
            'rack' => 1,
            'rack-bulk' => 28,
            'compact' => 30,
            default => 15,
        };

        return max(1, (int) ceil($pieces / $perPage + $dozens / 30));
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
    public function ensure(string $type, string $id, string $kind, ?callable $progress = null): ?string
    {
        $payload = $this->payload($type, $id, $kind);
        $path = $this->path($type, $id, $kind, $payload);
        if ($path === null) {
            // Nothing left to print (e.g. batch deleted): drop old files.
            $this->disk()->deleteDirectory($this->directory($type, $id));
            return null;
        }
        if ($this->isFresh($path)) {
            return $path;
        }

        $pdf = $this->render($payload['barcodes'], $payload['dozenBarcodes'], $payload['style'], [], $progress);
        $this->disk()->put($path, $pdf);

        // Older versions of this kind (before an edit, or expired) are obsolete.
        foreach ($this->disk()->files($this->directory($type, $id)) as $file) {
            if ($file !== $path && str_starts_with(basename($file), "{$kind}-")) {
                $this->disk()->delete($file);
            }
        }

        return $path;
    }

    /**
     * What a waiting user polls. Starts a background render when the PDF is
     * missing and reports how far it is. Never renders inside this call.
     *
     * @return array{state:string,percent:int,total:int}|null null when there is nothing to print
     */
    public function prepare(string $type, string $id, string $kind): ?array
    {
        $payload = $this->payload($type, $id, $kind);
        $path = $this->path($type, $id, $kind, $payload);
        if ($path === null) {
            return null;
        }
        $total = count($payload['barcodes']) + count($payload['dozenBarcodes']);
        $report = fn(string $state, int $percent = 0) => ['state' => $state, 'percent' => $percent, 'total' => $total];

        if ($this->isFresh($path)) {
            return $report('ready', 100);
        }

        $key = $this->statusKey($type, $id, $kind);
        $status = Cache::get($key);
        $age = $status ? now()->timestamp - $status['at'] : 0;

        if (($status['state'] ?? null) === 'failed') {
            Cache::forget($key); // the next click tries again
            return $report('failed');
        }

        if (!$status || ($status['state'] === 'processing' && $age > self::STALLED_SECONDS)) {
            if ($status) {
                Cache::lock($this->lockName($type, $id, $kind))->forceRelease();
            }
            $this->putStatus($key, 'queued', 0);
            $this->startRender(new RenderBarcodePdfJob($type, $id, $kind));

            return $report('queued');
        }

        if ($status['state'] === 'queued' && $age > self::QUEUE_PATIENCE_SECONDS && empty($status['fallback'])) {
            Cache::put($key, ['fallback' => true] + $status, now()->addHour());
            $this->renderAfterResponse(new RenderBarcodePdfJob($type, $id, $kind));
        }

        return $report($status['state'], $status['percent']);
    }

    /** Hands the render to the queue; without a working queue it runs after the response. */
    private function startRender(RenderBarcodePdfJob $job): void
    {
        if (config('queue.default') !== 'sync') {
            try {
                // Not dispatch(): that defers the push until after this method, out of reach of the catch.
                Queue::push($job);
                return;
            } catch (\Throwable $e) {
                Log::warning('Could not queue barcode render: ' . $e->getMessage());
            }
        }
        $this->renderAfterResponse($job);
    }

    private function renderAfterResponse(RenderBarcodePdfJob $job): void
    {
        app()->terminating(fn() => app()->call([$job, 'handle']));
    }

    /** Renders one PDF while publishing its progress; a no-op if it is already being rendered. */
    public function renderTracked(string $type, string $id, string $kind): void
    {
        $lock = Cache::lock($this->lockName($type, $id, $kind), 900);
        if (!$lock->get()) {
            return;
        }

        $key = $this->statusKey($type, $id, $kind);
        try {
            $this->putStatus($key, 'processing', 0);
            $last = 0;
            $writtenAt = now()->timestamp;
            $this->ensure($type, $id, $kind, function (int $percent) use ($key, &$last, &$writtenAt) {
                // At most ~100 writes per render, plus a heartbeat.
                if ($percent > $last || now()->timestamp - $writtenAt >= 10) {
                    $last = max($last, $percent);
                    $writtenAt = now()->timestamp;
                    $this->putStatus($key, 'processing', $last);
                }
            });
            Cache::forget($key);
        } finally {
            $lock->release();
        }
    }

    public function markFailed(string $type, string $id, string $kind): void
    {
        Cache::lock($this->lockName($type, $id, $kind))->forceRelease();
        $this->putStatus($this->statusKey($type, $id, $kind), 'failed', 0);
    }

    /** Generate (or refresh) every kind for a source — used by the background job. */
    public function generateAll(string $type, string $id): void
    {
        foreach (self::KINDS as $kind) {
            $this->renderTracked($type, $id, $kind);
        }
    }

    private function directory(string $type, string $id): string
    {
        return self::DIR . "/{$type}/{$id}";
    }

    private function path(string $type, string $id, string $kind, array $payload): ?string
    {
        if (!$payload['barcodes'] && !$payload['dozenBarcodes']) {
            return null;
        }

        return $this->directory($type, $id) . "/{$kind}-" . sha1(self::TEMPLATE_VERSION . json_encode($payload)) . '.pdf';
    }

    private function statusKey(string $type, string $id, string $kind): string
    {
        return "barcode-pdf:{$type}:{$id}:{$kind}";
    }

    private function lockName(string $type, string $id, string $kind): string
    {
        return "barcode-pdf-lock:{$type}:{$id}:{$kind}";
    }

    private function putStatus(string $key, string $state, int $percent): void
    {
        Cache::put($key, ['state' => $state, 'percent' => $percent, 'at' => now()->timestamp], now()->addHour());
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
