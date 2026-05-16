<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class FetchShopeeOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300; // 5 menit

    public function __construct(public int $days = 1) {}

    public function handle(): void
    {
        Log::info("[FetchShopeeOrdersJob] Starting fetch for last {$this->days} day(s)...");

        Artisan::call('shopee:fetch-orders', ['--days' => $this->days]);

        $output = Artisan::output();
        Log::info("[FetchShopeeOrdersJob] Done.\n" . $output);
    }

    public function failed(\Throwable $e): void
    {
        Log::error("[FetchShopeeOrdersJob] Failed: " . $e->getMessage());
    }
}
