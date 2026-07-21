<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Marketplace;
use Illuminate\Database\Seeder;

class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        $marketplaces = [
            [
                'code' => 'TIKTOK',
                'name' => 'TikTok Shop',
                'alias' => 'TikTok',
                'base_api_url' => 'https://open-api.tiktokglobalshop.com',
                'description' => 'TikTok Shop marketplace integration for e-commerce',
                'is_need_checker' => true,
            ],
            [
                'code' => 'SHOPEE',
                'name' => 'Shopee',
                'alias' => 'Shopee',
                'base_api_url' => 'https://partner.shopeemobile.com',
                'description' => 'Shopee marketplace integration for e-commerce',
                'is_need_checker' => true,
            ],
            [
                'code' => 'LAZADA',
                'name' => 'Lazada',
                'alias' => 'Lazada',
                'base_api_url' => 'https://api.lazada.co.id/rest',
                'description' => 'Lazada marketplace integration for e-commerce',
                'is_need_checker' => true,
            ],
        ];

        foreach ($marketplaces as $marketplace) {
            Marketplace::firstOrCreate(['code' => $marketplace['code']], $marketplace);
        }

        $this->command->info('Created ' . count($marketplaces) . ' marketplaces successfully!');
    }
}
