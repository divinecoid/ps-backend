<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MasterData\OnlineStore;
use App\Services\ShopeeService;

class RefreshShopeeToken extends Command
{
    protected $signature = 'shopee:refresh-token {--store_id=} {--shop_id=} {--code=} {--auth-url}';
    protected $description = 'Shopee token helper: print auth URL, exchange code, or refresh token';

    public function handle(ShopeeService $service)
    {
        try {
            $storeId = $this->option('store_id');
            $shopId = $this->option('shop_id');
            $code = $this->option('code');

            if ($storeId) {
                $service->setStore(OnlineStore::findOrFail($storeId));
            } elseif ($shopId) {
                $store = OnlineStore::where('store_code', (string)$shopId)
                    ->orWhere('shop_id', (string)$shopId)
                    ->first();
                if ($store) {
                    $service->setStore($store);
                }
            }

            if ($this->option('auth-url')) {
                $this->line($service->generateAuthUrl());
                return 0;
            }

            if ($code) {
                $result = $service->exchangeAuthCodeForToken($code, $shopId ? (int)$shopId : null);
                $this->info('Exchange code berhasil');
                $this->line('Access Token: ' . ($result['access_token'] ?? ''));
                $this->line('Refresh Token: ' . ($result['refresh_token'] ?? ''));
                $this->line('Expire In: ' . ($result['expire_in'] ?? ''));
                $this->line('Refresh Token Expire In: ' . ($result['refresh_token_expire_in'] ?? ''));
                return 0;
            }

            $result = $service->refreshAccessToken();
            $this->info('Refresh token berhasil');
            $this->line('Access Token: ' . ($result['access_token'] ?? ''));
            $this->line('Refresh Token: ' . ($result['refresh_token'] ?? ''));
            $this->line('Expire In: ' . ($result['expire_in'] ?? ''));
            return 0;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }
    }
}
