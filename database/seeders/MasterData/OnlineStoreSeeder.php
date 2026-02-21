<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use Illuminate\Database\Seeder;

class OnlineStoreSeeder extends Seeder
{
    public function run(): void
    {
        // Fetch marketplaces
        $tiktok = Marketplace::where('code', 'TIKTOK')->first();
        $shopee = Marketplace::where('code', 'SHOPEE')->first();
        $lazada = Marketplace::where('code', 'LAZADA')->first();

        $stores = [
            [
                'marketplace_id' => $tiktok->id,
                'store_code' => 'TIKTOK_STORE_001',
                'store_name' => 'Official TikTok Shop Store',
                'api_key' => 'tiktok_dummy_api_key_' . bin2hex(random_bytes(16)),
                'client_id' => 'tiktok_client_' . bin2hex(random_bytes(8)),
                'client_secret' => 'tiktok_secret_' . bin2hex(random_bytes(16)),
                'store_url' => 'https://shop.tiktok.com/official-store',
                'is_active' => true,
                'redirect_uri' => 'https://yourapp.com/callback/tiktok',
                'access_token' => 'tiktok_access_' . bin2hex(random_bytes(32)),
                'refresh_token' => 'tiktok_refresh_' . bin2hex(random_bytes(32)),
                'access_token_expires_at' => now()->addDays(30),
                'refresh_token_expires_at' => now()->addDays(90),
            ],
            [
                'marketplace_id' => $shopee->id,
                'store_code' => 'SHOPEE_STORE_001',
                'store_name' => 'Official Shopee Store',
                'api_key' => 'shopee_dummy_api_key_' . bin2hex(random_bytes(16)),
                'client_id' => 'shopee_client_' . bin2hex(random_bytes(8)),
                'client_secret' => 'shopee_secret_' . bin2hex(random_bytes(16)),
                'store_url' => 'https://shopee.co.id/official-store',
                'is_active' => true,
                'redirect_uri' => 'https://yourapp.com/callback/shopee',
                'access_token' => 'shopee_access_' . bin2hex(random_bytes(32)),
                'refresh_token' => 'shopee_refresh_' . bin2hex(random_bytes(32)),
                'access_token_expires_at' => now()->addDays(30),
                'refresh_token_expires_at' => now()->addDays(90),
            ],
            [
                'marketplace_id' => $lazada->id,
                'store_code' => 'LAZADA_STORE_001',
                'store_name' => 'Official Lazada Store',
                'api_key' => 'lazada_dummy_api_key_' . bin2hex(random_bytes(16)),
                'client_id' => 'lazada_client_' . bin2hex(random_bytes(8)),
                'client_secret' => 'lazada_secret_' . bin2hex(random_bytes(16)),
                'store_url' => 'https://www.lazada.co.id/official-store',
                'is_active' => true,
                'redirect_uri' => 'https://yourapp.com/callback/lazada',
                'access_token' => 'lazada_access_' . bin2hex(random_bytes(32)),
                'refresh_token' => 'lazada_refresh_' . bin2hex(random_bytes(32)),
                'access_token_expires_at' => now()->addDays(30),
                'refresh_token_expires_at' => now()->addDays(90),
            ],
        ];

        foreach ($stores as $store) {
            OnlineStore::create($store);
        }

        $this->command->info('Created ' . count($stores) . ' online stores successfully!');
    }
}
