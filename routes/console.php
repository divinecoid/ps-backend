<?php

use App\Jobs\FetchShopeeOrdersJob;
use App\Jobs\RefreshShopeeTokenJob;
use App\Jobs\FetchTiktokOrdersJob;
use App\Jobs\RefreshTiktokTokenJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Fetch Shopee orders every hour via Queue Job
Schedule::job(new FetchShopeeOrdersJob(days: 1))
    ->hourly()
    ->withoutOverlapping()
    ->name('shopee-fetch-orders')
    ->onOneServer();

// Refresh Shopee access token every day at 00:30 via Queue Job
Schedule::job(new RefreshShopeeTokenJob())
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->name('shopee-refresh-token')
    ->onOneServer();

// Fetch TikTok Shop orders every hour via Queue Job
Schedule::job(new FetchTiktokOrdersJob(days: 1))
    ->hourly()
    ->withoutOverlapping()
    ->name('tiktok-fetch-orders')
    ->onOneServer();

// Refresh TikTok Shop access token every day at 00:30 via Queue Job
Schedule::job(new RefreshTiktokTokenJob())
    ->dailyAt('00:30')
    ->withoutOverlapping()
    ->name('tiktok-refresh-token')
    ->onOneServer();
