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
            // Create or update Marketplace
            $marketplace = Marketplace::updateOrCreate(
                ['name' => 'Shopee'],
                [
                    'code' => 'shopee',
                    'alias' => 'shopee',
                    'base_api_url' => 'https://partner.shopeemobile.com',
                    'description' => 'Shopee Marketplace Integration',
                    'is_need_checker' => false,
                ]
            );

            // Create or update OnlineStore
            OnlineStore::updateOrCreate(
                ['store_code' => '46821355'], // Shop ID
                [
                    'store_name' => 'Shopee Store',
                    'marketplace_id' => $marketplace->id,
                    'client_id' => '2014481', // Partner ID
                    'client_secret' => 'shpk6356625343585571577678536858664c47597073665947435a68745a4d4d', // Partner Key
                    'store_url' => 'https://shopee.co.id',
                    'is_active' => true,
                    'redirect_uri' => 'https://ps.divineproject.my.id',
                    'access_token' => '775951786f7574494763504e59794f51',
                    'refresh_token' => '674a446954454e58504d546e4e797249',
                    // Set expires_at to now to force a refresh on next run, or set it to future if we assume it's valid
                    // User said "if not valid try run refresh token", so maybe we assume it's invalid or just set it.
                    // Let's set it to now() so the refresh logic is triggered if it checks expiration.
                    // But wait, the refresh logic checks if token exists.
                    // Let's just set some value.
                    'access_token_expires_at' => now()->subMinute(), 
                    'refresh_token_expires_at' => now()->addDays(30),
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
                    'client_id' => '1198129', // PARTNER_ID
                    'client_secret' => 'shpk54746f6d6545646f4542617176486f4358775150626769675861524b714e', // PARTNER_KEY
                    'store_url' => 'https://sandbox.shopee.sg',
                    'is_active' => true,
                    'redirect_uri' => 'https://google.com',
                    'access_token' => 'eyJhbGciOiJIUzI1NiJ9.CLGQSRABGP6N7WsgASiWw7rKBjDWuIDgAzgBQAE.jnCUXDl8nyWKzFPXUCRAmf5pYCyp6y_a1xr73u3U5JU',
                    'refresh_token' => 'eyJhbGciOiJIUzI1NiJ9.CLGQSRABGP6N7WsgAiiWw7rKBjCDqpbfDDgBQAE.yZ58IhmGo7HDnkTiUzOL5tPDEUxn9MyeKEgd2T1mmvI',
                    'access_token_expires_at' => Carbon::parse('12/27/2025, 1:54:15 AM'),
                    'refresh_token_expires_at' => Carbon::parse('12/27/2025, 1:54:15 AM')->addDays(30),
                ]
            );
        });
    }
}
