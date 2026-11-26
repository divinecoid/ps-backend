<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RefreshTiktokTokenJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 60;

    public function handle(): void
    {
        Log::info('[RefreshTiktokTokenJob] Starting token refresh for all active stores...');

        $exitCode = Artisan::call('tiktok:refresh-token');

        $output = Artisan::output();
        Log::info('[RefreshTiktokTokenJob] Done.\n' . $output);

        if ($exitCode !== 0) {
            throw new \RuntimeException("tiktok:refresh-token failed, see log output above.");
        }
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[RefreshTiktokTokenJob] Failed: ' . $e->getMessage());
    }
}
