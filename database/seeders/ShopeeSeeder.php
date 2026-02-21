<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Carbon\Carbon;

class ShopeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::transaction(function () {
            // Create or update Marketplace Shopee
            $marketplace = Marketplace::updateOrCreate(
                ['alias' => 'shopee'],
                [
                    'name' => 'Shopee',
                    'code' => 'shopee',
                    'base_api_url' => 'https://partner.shopeemobile.com',
                    'description' => 'Shopee Marketplace Integration',
                    'is_need_checker' => false,
                ]
            );

            // Create or update OnlineStore (Production)
            OnlineStore::updateOrCreate(
                ['store_code' => '46821355'], // Shop ID
                [
                    'store_name' => 'Shopee Store',
                    'marketplace_id' => $marketplace->id,
                    'shop_id' => '46821355',
                    'client_id' => '2014481', // Partner ID
                    'client_secret' => 'shpk6356625343585571577678536858664c47597073665947435a68745a4d4d', // Partner Key
                    'store_url' => 'https://shopee.co.id',
                    'is_active' => true,
                    'redirect_uri' => 'https://ps.divineproject.my.id',
                    'auth_code' => '4f425a45415a55514d4e4d5044615963',
                    'access_token' => '775951786f7574494763504e59794f51',
                    'refresh_token' => '674a446954454e58504d546e4e797249',
                    'access_token_expires_at' => Carbon::parse('12/30/2025, 1:00:03 AM'),
                    'refresh_token_expires_at' => Carbon::parse('12/30/2025, 1:00:03 AM')->addDays(30),
                ]
            );

            // Create or update Marketplace (Sandbox)
            $marketplaceSandbox = Marketplace::updateOrCreate(
                ['code' => 'shopee_sandbox'],
                [
                    'name' => 'Shopee Sandbox',
                    'alias' => 'shopee_sandbox',
                    'base_api_url' => 'https://openplatform.sandbox.test-stable.shopee.sg',
                    'description' => 'Shopee Sandbox Marketplace Integration',
                    'is_need_checker' => false,
                ]
            );

            // Create or update OnlineStore (Sandbox)
            OnlineStore::updateOrCreate(
                ['store_code' => '226182910'], // SHOP_ID
                [
                    'store_name' => 'Shopee Sandbox Store',
                    'marketplace_id' => $marketplaceSandbox->id,
                    'shop_id' => '226182910',
                    'client_id' => '1198129',
                    'client_secret' => 'shpk54746f6d6545646f4542617176486f4358775150626769675861524b714e',
                    'store_url' => 'https://openplatform.sandbox.test-stable.shopee.sg',
                    'auth_code' => '4547624763704a53624a646b58504d6f',
                    'is_active' => true,
                    'redirect_uri' => 'https://google.com',
                    'access_token' => 'eyJhbGciOiJIUzI1NiJ9.CLGQSRABGP6N7WsgASiWw7rKBjDWuIDgAzgBQAE.jnCUXDl8nyWKzFPXUCRAmf5pYCyp6y_a1xr73u3U5JU',
                    'refresh_token' => 'eyJhbGciOiJIUzI1NiJ9.CLGQSRABGP6N7WsgAiiWw7rKBjCDqpbfDDgBQAE.yZ58IhmGo7HDnkTiUzOL5tPDEUxn9MyeKEgd2T1mmvI',
                    'access_token_expires_at' => Carbon::parse('12/27/2025, 1:54:15 AM'),
                    'refresh_token_expires_at' => Carbon::parse('12/27/2025, 1:54:15 AM')->addDays(30),
                ]
            );

            // Create or update Marketplace TikTok Shop
            $marketplaceTiktok = Marketplace::updateOrCreate(
                ['alias' => 'tiktok_shop'],
                [
                    'name' => 'TikTok Shop',
                    'code' => 'tiktok_shop',
                    'base_api_url' => 'https://open-api.tiktokglobalshop.com',
                    'description' => 'TikTok Shop Marketplace Integration',
                    'is_need_checker' => false,
                ]
            );

            // Create or update OnlineStore TikTok Divine Solutions
            OnlineStore::updateOrCreate(
                ['store_code' => 'TIKTOK_DIVINE_SOLUTIONS'],
                [
                    'store_name' => 'Divine Solutions',
                    'marketplace_id' => $marketplaceTiktok->id,
                    'api_key' => '6i5289l7qjvro',
                    'client_id' => '7574194545879320341',
                    'client_secret' => '6665ae720ac777940b808d597e0187e842a7d825',
                    'store_url' => 'https://open-api.tiktokglobalshop.com',
                    'is_active' => true,
                    'redirect_uri' => 'https://ps-test.divineproject.my.id/tiktok-shop/callback',
                    'access_token' => 'ROW_BHeE4QAAAABhcOyhkzK8vbv1KGMuep13vFmO9k6EVoK63L15hq0htZaeaE-6gT4z8wK6_JfyNI9M24s6Ho2rFShJseqQoH_a8ac7w-V4ZHZVe2_Rin-Ed7i2rNshVFumTAFFMCc7VsEW5TupnJzuWYdi4wYYxfZf-CCVC2ZaB5Aybnw6TmhIIg',
                    'refresh_token' => 'ROW_zNKmQQAAAACS089RV4HMhVqhG23hrZ2OChUgHKcOVT99YW-lQYQpl97Krhm-QulI5RlwQLKq5eo',
                    'access_token_expires_at' => null,
                    'refresh_token_expires_at' => null,
                ]
            );
        });
    }
}
