<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\MasterData\OnlineStore;
use App\Models\MasterData\Marketplace;

class ShopeeService
{
    /**
     * Generate Auth URL for Shopee Shop
     * 
     * @return string
     */
    public function generateAuthUrl()
    {
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/shop/auth_partner";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $redirectUrl = config('marketplace.shopee.redirect_url');
        $timestamp = time();

        $baseString = sprintf("%s%s%s", $partnerId, $path, $timestamp);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $url = sprintf(
            "%s%s?partner_id=%s&timestamp=%s&sign=%s&redirect=%s",
            $host,
            $path,
            $partnerId,
            $timestamp,
            $sign,
            urlencode($redirectUrl)
        );

        return $url;
    }

    /**
     * Common Request Handler with Auto-Refresh Token
     */
    private function request($method, $path, $params = [], $data = [])
    {
        $host = config('marketplace.shopee.base_url');
        $partnerId = (int)config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = (int)config('marketplace.shopee.shop_id');
        
        // 1. Get Token & Prepare Params
        $accessToken = $this->getAccessToken();
        $timestamp = time();
        
        // Base String Construction
        $baseString = sprintf("%s%s%s%s%s", $partnerId, $path, $timestamp, $accessToken, $shopId);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        // Merge Common Params
        $commonParams = [
            'partner_id' => $partnerId,
            'timestamp' => $timestamp,
            'access_token' => $accessToken,
            'shop_id' => $shopId,
            'sign' => $sign
        ];
        $finalParams = array_merge($commonParams, $params);

        // 2. Execute Request
        $url = $host . $path;
        $response = null;

        try {
            if (strtoupper($method) === 'POST') {
                $response = Http::post($url . '?' . http_build_query($finalParams), $data);
            } else {
                $response = Http::get($url, $finalParams);
            }
        } catch (\Exception $e) {
            Log::error("Shopee API Exception: {$path}", ['message' => $e->getMessage()]);
            return ['error' => true, 'message' => $e->getMessage()];
        }

        // 3. Handle Token Expiration (Auto Refresh)
        // Shopee V2 typically returns "error": "error_auth" or similar for invalid token
        $json = $response->json();
        $isTokenError = false;
        
        // Check for common token errors
        if (isset($json['error']) && ($json['error'] === 'error_auth' || $json['error'] === 'invalid_access_token')) {
            $isTokenError = true;
        }
        // Also check if message contains "access_token" keywords just in case
        if (isset($json['message']) && stripos($json['message'], 'access_token') !== false) {
            $isTokenError = true;
        }

        if ($isTokenError) {
            Log::info("Shopee Token Expired/Invalid. Refreshing... Path: {$path}");
            try {
                $this->refreshAccessToken();
                
                // Retry with new token
                $accessToken = $this->getAccessToken();
                $timestamp = time();
                $baseString = sprintf("%s%s%s%s%s", $partnerId, $path, $timestamp, $accessToken, $shopId);
                $sign = hash_hmac('sha256', $baseString, $partnerKey);
                
                $finalParams['timestamp'] = $timestamp;
                $finalParams['access_token'] = $accessToken;
                $finalParams['sign'] = $sign;

                if (strtoupper($method) === 'POST') {
                    $response = Http::post($url . '?' . http_build_query($finalParams), $data);
                } else {
                    $response = Http::get($url, $finalParams);
                }
                
                return $response->json(); // Return retried response

            } catch (\Exception $e) {
                Log::error("Shopee Token Refresh & Retry Failed: {$path}", ['message' => $e->getMessage()]);
                return ['error' => true, 'message' => 'Token refresh failed: ' . $e->getMessage()];
            }
        }

        // 4. Handle General API Errors
        if ($response->failed()) {
            Log::error("Shopee API Failed: {$path}", ['body' => $response->body()]);
            return ['error' => true, 'message' => $response->body()];
        }

        return $json;
    }

    /**
     * Get Order List from Shopee
     * 
     * @param int $timeFrom
     * @param int $timeTo
     * @param int $pageSize
     * @param string $cursor
     * @return array
     */
    public function getOrderList($timeFrom, $timeTo, $pageSize = 50, $cursor = "")
    {
        $path = "/api/v2/order/get_order_list";
        $params = [
            'time_range_field' => 'create_time',
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'page_size' => $pageSize,
            'cursor' => $cursor
        ];

        return $this->request('GET', $path, $params);
    }

    /**
     * Get Order Detail from Shopee
     * 
     * @param array $orderSnList
     * @return array
     */
    public function getOrderDetail(array $orderSnList)
    {
        $path = "/api/v2/order/get_order_detail";
        $params = [
            'order_sn_list' => implode(',', $orderSnList),
            'response_optional_fields' => 'item_list,message_to_seller,buyer_username,total_amount,recipient_address,estimated_shipping_fee,actual_shipping_fee,shipping_carrier'
        ];

        return $this->request('GET', $path, $params);
    }

    /**
     * Get Shipping Parameter
     * 
     * @param string $orderSn
     * @return array
     */
    public function getShippingParameter($orderSn)
    {
        $path = "/api/v2/logistics/get_shipping_parameter";
        $params = ['order_sn' => $orderSn];
        
        return $this->request('GET', $path, $params);
    }

    /**
     * Ship Order (Request Pickup)
     * 
     * @param string $orderSn
     * @param array $pickupData ['address_id' => ..., 'pickup_time_id' => ...]
     * @return array
     */
    public function shipOrder($orderSn, $pickupData)
    {
        $path = "/api/v2/logistics/ship_order";
        $data = [
            'order_sn' => $orderSn,
            'pickup' => [
                'address_id' => (int)$pickupData['address_id'],
                'pickup_time_id' => $pickupData['pickup_time_id']
            ]
        ];

        return $this->request('POST', $path, [], $data);
    }

    /**
     * Create Shipping Document
     * 
     * @param string $orderSn
     * @return array
     */
    public function createShippingDocument($orderSn)
    {
        $path = "/api/v2/logistics/create_shipping_document";
        $data = [
            'order_list' => [
                ['order_sn' => $orderSn]
            ]
        ];

        return $this->request('POST', $path, [], $data);
    }


    /**
     * Download Shipping Document
     * 
     * @param string $orderSn
     * @param string|null $shippingDocumentType
     * @return string (Binary content of the PDF)
     */
    public function downloadShippingDocument($orderSn, $shippingDocumentType = 'NORMAL_AIR_WAYBILL')
    {
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/logistics/download_shipping_document";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = (int)config('marketplace.shopee.shop_id');
        $accessToken = $this->getAccessToken();
        $timestamp = time();

        $baseString = sprintf("%s%s%s%s%s", $partnerId, $path, $timestamp, $accessToken, $shopId);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $params = [
            'partner_id' => (int)$partnerId,
            'timestamp' => $timestamp,
            'access_token' => $accessToken,
            'shop_id' => (int)$shopId,
            'sign' => $sign
        ];

        $data = [
            'shipping_document_type' => $shippingDocumentType,
            'order_list' => [
                ['order_sn' => $orderSn]
            ]
        ];

        try {
            // Note: Laravel Http client handles response body automatically
            $response = Http::post($host . $path . '?' . http_build_query($params), $data);

            if ($response->failed()) {
                Log::error('Shopee downloadShippingDocument failed', ['body' => $response->body()]);
                throw new \Exception("Shopee API Error: " . $response->body());
            }

            return $response->body(); // Return raw content (likely PDF)

        } catch (\Exception $e) {
            Log::error('Shopee downloadShippingDocument exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function refreshAccessToken()
    {
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/auth/access_token/get";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = (int)config('marketplace.shopee.shop_id');
        $store = null;
        try {
            $store = $this->getShopeeStore();
        } catch (\Throwable $e) {
            $store = null;
        }
        $refreshToken = $store ? $store->refresh_token : config('marketplace.shopee.refresh_token');
        $timestamp = time();

        if (!$refreshToken) {
            throw new \Exception("No refresh token available");
        }

        $baseString = sprintf("%s%s%s", $partnerId, $path, $timestamp);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $params = [
            'partner_id' => (int)$partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign
        ];

        $data = [
            'refresh_token' => $refreshToken,
            'partner_id' => (int)$partnerId,
            'shop_id' => $shopId
        ];

        $response = Http::post($host . $path . '?' . http_build_query($params), $data);
        if ($response->failed()) {
            Log::error('Shopee refreshAccessToken failed', ['body' => $response->body()]);
            throw new \Exception("Shopee API Error: " . $response->body());
        }

        $json = $response->json();
        if (isset($json['error']) && !empty($json['error'])) {
            throw new \Exception($json['message'] ?? 'Refresh token failed');
        }

        if ($store) {
            $store->update([
                'access_token' => $json['access_token'] ?? $store->access_token,
                'refresh_token' => $json['refresh_token'] ?? $store->refresh_token,
                'access_token_expires_at' => isset($json['expire_in']) ? now()->addSeconds($json['expire_in']) : $store->access_token_expires_at,
            ]);
        }

        return $json;
    }

    private function getShopeeStore(): OnlineStore
    {
        $shopId = (string)config('marketplace.shopee.shop_id');
        $store = OnlineStore::where('store_code', $shopId)->first();
        if (!$store) {
            $store = OnlineStore::whereHas('marketplace', function($q) {
                $q->where('name', 'Shopee')->orWhere('alias', 'Shopee');
            })->first();
        }
        if (!$store) {
            throw new \Exception("OnlineStore for Shopee not found");
        }
        return $store;
    }

    private function getAccessToken(): string
    {
        try {
            $store = $this->getShopeeStore();
            if ($store && $store->access_token) {
                return $store->access_token;
            }
        } catch (\Throwable $e) {
            // ignore and try env fallback
        }
        $envToken = config('marketplace.shopee.access_token');
        if ($envToken) {
            return $envToken;
        }
        throw new \Exception("Shopee access token is missing. Set in OnlineStore or .env");
    }
}
