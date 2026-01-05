<?php

namespace App\Services;

use App\Models\MasterData\OnlineStore;
use Illuminate\Support\Facades\Log;

class ShopeeService
{
    /**
     * Generate Shopee Shop Authentication URL
     *
     * @param OnlineStore $store
     * @return string
     */
    public function generateAuthUrl(OnlineStore $store): string
    {
        $host = $store->marketplace->base_api_url ?? 'https://partner.shopeemobile.com'; // Default to prod if not set, or use sandbox
        $path = "/api/v2/shop/auth_partner";
        $timestamp = time();
        $partnerId = $store->client_id; // Mapping client_id to partner_id
        $partnerKey = $store->client_secret; // Mapping client_secret to partner_key
        $redirectUrl = $store->redirect_uri;

        if (!$partnerId || !$partnerKey || !$redirectUrl) {
            throw new \Exception("Missing credentials for Shopee store (client_id, client_secret, or redirect_uri)");
        }

        // Logic: partner_id + path + timestamp
        $baseString = $partnerId . $path . $timestamp;

        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $url = $host . $path . 
            "?partner_id=" . $partnerId . 
            "&timestamp=" . $timestamp . 
            "&sign=" . $sign . 
            "&redirect=" . urlencode($redirectUrl);

        return $url;
    }
}
