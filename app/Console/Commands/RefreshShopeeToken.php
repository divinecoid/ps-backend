<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopeeService;

class RefreshShopeeToken extends Command
{
    protected $signature = 'shopee:refresh-token';
    protected $description = 'Refresh Shopee access token and update APIShopee/config.json';

    public function handle(ShopeeService $service)
    {
        try {
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
