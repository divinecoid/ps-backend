<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MasterData\OnlineStore;
use App\Services\ShopeeService;

class RefreshShopeeToken extends Command
{
    protected $signature = 'shopee:refresh-token {--store_id=} {--shop_id=} {--code=} {--auth-url}';
    protected $description = 'Shopee token helper: print auth URL, exchange code, or refresh token for all/specific store';

    public function handle(ShopeeService $service)
    {
        try {
            $storeId = $this->option('store_id');
            $shopId  = $this->option('shop_id');
            $code    = $this->option('code');

            // --- Single store mode ---
            if ($storeId || $shopId) {
                if ($storeId) {
                    $service->setStore(OnlineStore::findOrFail($storeId));
                } else {
                    $store = OnlineStore::where('store_code', (string)$shopId)
                        ->orWhere('shop_id', (string)$shopId)
                        ->firstOrFail();
                    $service->setStore($store);
                }

                if ($this->option('auth-url')) {
                    $this->line($service->generateAuthUrl());
                    return 0;
                }

                if ($code) {
                    $result = $service->exchangeAuthCodeForToken($code, $shopId ? (int)$shopId : null);
                    $this->info('Exchange code berhasil');
                    $this->line('Access Token: '  . ($result['access_token'] ?? ''));
                    $this->line('Refresh Token: ' . ($result['refresh_token'] ?? ''));
                    $this->line('Expire In: '     . ($result['expire_in'] ?? ''));
                    return 0;
                }

                $result = $service->refreshAccessToken();
                $this->info('Refresh token berhasil');
                $this->line('Access Token: '  . ($result['access_token'] ?? ''));
                $this->line('Refresh Token: ' . ($result['refresh_token'] ?? ''));
                $this->line('Expire In: '     . ($result['expire_in'] ?? ''));
                return 0;
            }

            // --- All active stores mode (untuk scheduler) ---
            $stores = OnlineStore::where('is_active', true)
                ->whereNotNull('refresh_token')
                ->where('refresh_token', '!=', '')
                ->get();

            if ($stores->isEmpty()) {
                $this->warn('Tidak ada store aktif dengan refresh token.');
                return 0;
            }

            $this->info("Ditemukan {$stores->count()} store aktif, memulai refresh token...");

            foreach ($stores as $store) {
                $this->line("Processing: {$store->store_name} ({$store->shop_id})");
                try {
                    $service->setStore($store);
                    $result = $service->refreshAccessToken();
                    $this->info("  ✓ Berhasil - Expire In: " . ($result['expire_in'] ?? '-') . "s");
                } catch (\Exception $e) {
                    $this->error("  ✗ Gagal: " . $e->getMessage());
                }
            }

            $this->info('Selesai refresh semua store.');
            return 0;

        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
