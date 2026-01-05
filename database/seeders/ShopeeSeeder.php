<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

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
        });
    }
}
