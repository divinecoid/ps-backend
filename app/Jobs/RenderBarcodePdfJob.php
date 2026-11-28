<?php

namespace App\Jobs;

use App\Services\BarcodePdfService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

/**
 * Renders the one PDF a user is waiting for. Progress is published by
 * BarcodePdfService and polled by the "prepare" endpoint.
 */
class RenderBarcodePdfJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 1;
    public int $timeout = 900;

    public function __construct(public string $type, public string $id, public string $kind)
    {
    }

    public function handle(BarcodePdfService $service): void
    {
        try {
            $service->renderTracked($this->type, $this->id, $this->kind);
        } catch (\Throwable $e) {
            // Also reached when run after the response, where failed() is not called.
            Log::error("[RenderBarcodePdfJob] {$this->type} {$this->id} {$this->kind} failed: " . $e->getMessage());
            $service->markFailed($this->type, $this->id, $this->kind);
        }
    }

    /** Timeout / killed worker. */
    public function failed(\Throwable $e): void
    {
        app(BarcodePdfService::class)->markFailed($this->type, $this->id, $this->kind);
    }
}
