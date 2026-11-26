<?php

namespace App\Console\Commands;

use App\Http\Services\TiktokShopService;
use App\Models\MasterData\OnlineStore;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Throwable;

class RefreshTiktokToken extends Command
{
    protected $signature = 'tiktok:refresh-token {--store_id= : Only refresh this online store}';

    protected $description = 'Refresh the access token of active TikTok Shop stores';

    public function handle(TiktokShopService $tiktok): int
    {
        $stores = OnlineStore::activeTiktok()
            ->when($this->option('store_id'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('No active TikTok Shop stores found.');

            return self::SUCCESS;
        }

        $exitCode = self::SUCCESS;

        foreach ($stores as $store) {
            try {
                $tiktok->forStore($store)->getAccessToken(forceRefresh: true);
                $this->info("[{$store->store_name}] token refreshed.");
            } catch (Throwable $e) {
                $exitCode = self::FAILURE;
                // The error body can echo request data, so only the store and message are logged
                $this->error("[{$store->store_name}] {$e->getMessage()}");
                Log::error('TikTok token refresh failed', ['store_id' => $store->id, 'error' => $e->getMessage()]);
            }
        }

        return $exitCode;
    }
}
