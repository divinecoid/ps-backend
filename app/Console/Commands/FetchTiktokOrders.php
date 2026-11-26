<?php

namespace App\Console\Commands;

use App\Http\Services\TiktokShopService;
use App\Models\MasterData\OnlineStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class FetchTiktokOrders extends Command
{
    protected $signature = 'tiktok:fetch-orders {--days=1 : Number of days to look back}';

    protected $description = 'Fetch recently updated TikTok Shop orders and save them to the database';

    public function handle(TiktokShopService $tiktok): int
    {
        $days = max(1, (int) $this->option('days'));
        $stores = OnlineStore::activeTiktok()->get();

        if ($stores->isEmpty()) {
            $this->warn('No active TikTok Shop stores found.');

            return self::SUCCESS;
        }

        $exitCode = self::SUCCESS;

        foreach ($stores as $store) {
            try {
                $result = $tiktok->forStore($store)->syncRecentOrders($days);
                $this->info("[{$store->store_name}] {$result['orders']} order(s) from {$result['pages']} page(s).");
            } catch (Throwable $e) {
                $exitCode = self::FAILURE;
                $this->error("[{$store->store_name}] {$e->getMessage()}");
                Log::error('TikTok order fetch failed', ['store_id' => $store->id, 'error' => $e->getMessage()]);
            }
        }

        return $exitCode;
    }
}
