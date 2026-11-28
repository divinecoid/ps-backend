<?php

namespace App\Jobs;

use App\Services\BarcodePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pre-generates the dozen + piece barcode PDFs of an old-stock batch or a CMT
 * request so printing is a plain file download. Safe to run repeatedly: files
 * are content-addressed and only re-rendered when the labels changed.
 */
class GenerateBarcodePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 2;
    // Thousands of labels take a while; must stay below the connection's retry_after.
    public int $timeout = 900;

    public function __construct(public string $type, public string $id)
    {
    }

    /**
     * Queues the pre-generation without ever making the caller wait for it.
     * With the "sync" queue there is no background, so nothing is queued and
     * the PDF is made when someone prints (with a progress indicator).
     */
    public static function queue(string $type, string $id): void
    {
        if (config('queue.default') === 'sync') {
            return;
        }

        DB::afterCommit(function () use ($type, $id) {
            try {
                static::dispatch($type, $id);
            } catch (\Throwable $e) {
                // The data is saved; the PDF is simply made at print time instead.
                Log::warning("[GenerateBarcodePdfJob] could not queue {$type} {$id}: " . $e->getMessage());
            }
        });
    }

    public function handle(BarcodePdfService $service): void
    {
        $service->generateAll($this->type, $this->id);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[GenerateBarcodePdfJob] {$this->type} {$this->id} failed: " . $e->getMessage());
    }
}
