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

        Artisan::call('tiktok:refresh-token');

        $output = Artisan::output();
        Log::info('[RefreshTiktokTokenJob] Done.\n' . $output);
    }

    public function failed(\Throwable $e): void
    {
        Log::error('[RefreshTiktokTokenJob] Failed: ' . $e->getMessage());
    }
}
