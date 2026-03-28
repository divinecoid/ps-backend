<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\MasterData\OnlineStore;
use App\Models\MasterData\Marketplace;
use Carbon\Carbon;

class ShopeeService
{
    protected ?OnlineStore $store = null;

    public function setStore(OnlineStore $store)
    {
        $this->store = $store;
    }

    protected function getStore(): OnlineStore
    {
        if (!$this->store) {
            // Try to find any Shopee store
            $this->store = OnlineStore::whereHas('marketplace', function($q) {
                $q->where('name', 'Shopee')->orWhere('alias', 'shopee')->orWhere('alias', 'shopee_sandbox');
            })->first();

            if (!$this->store) {
                throw new \Exception("Shopee Store context not set and no default store found.");
            }
        }
        return $this->store;
    }

    /**
     * Generate Auth URL for Shopee Shop
     * 
     * @return string
     */
    public function generateAuthUrl()
    {
        $store = $this->getStore();
        // For Auth URL, we might need marketplace specific config if store is not fully setup
        // But assuming we have at least the marketplace/store record with credentials
        
        $host = $store->marketplace->base_api_url;
        $path = "/api/v2/shop/auth_partner";
        $partnerId = $store->client_id;
        $partnerKey = $store->client_secret;
        $redirectUrl = $store->redirect_uri;
        $timestamp = time();

        $baseString = sprintf("%s%s%s", $partnerId, $path, $timestamp);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $url = sprintf(
            "%s%s?partner_id=%s&timestamp=%s&sign=%s&redirect=%s&state=%s",
            $host,
            $path,
            $partnerId,
            $timestamp,
            $sign,
            urlencode($redirectUrl),
            $partnerId
        );

        return $url;
    }

    /**
     * Common Request Handler with Auto-Refresh Token
     */
    private function request($method, $path, $params = [], $data = [])
    {
        $store = $this->getStore();
        
        $host = $store->marketplace->base_api_url;
        $partnerId = (int)$store->client_id;
        $partnerKey = $store->client_secret;
        $shopId = (int)($store->shop_id ?? $store->store_code);
        
        // 1. Get Token & Prepare Params
        $accessToken = $store->access_token;
        if (!$accessToken) {
             throw new \Exception("Access token missing for store: " . $store->store_name);
        }

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
        if (isset($json['error']) && ($json['error'] === 'error_auth' || $json['error'] === 'invalid_access_token' || $json['error'] === 'invalid_acceess_token')) {
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
                $store->refresh(); // Reload from DB
                $accessToken = $store->access_token;
                
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
        ];
        // Only include cursor if present to avoid invalid cursor errors
        if (!empty($cursor)) {
            $params['cursor'] = $cursor;
        }

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
        $store = $this->getStore();
        
        $execute = function() use ($store, $orderSn, $shippingDocumentType) {
            $host = $store->marketplace->base_api_url;
            $path = "/api/v2/logistics/download_shipping_document";
            $partnerId = (int)$store->client_id;
            $partnerKey = $store->client_secret;
            $shopId = (int)($store->shop_id ?? $store->store_code);
            $accessToken = $store->access_token;
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

            return Http::post($host . $path . '?' . http_build_query($params), $data);
        };

        try {
            $response = $execute();
            $body = $response->body();
            $contentType = $response->header('Content-Type');

            // Check if response is JSON (error) or Binary (PDF)
            $isJson = false;
            $json = [];
            
            if (strpos($contentType, 'application/json') !== false || substr(trim($body), 0, 1) === '{') {
                $json = $response->json();
                $isJson = true;
            }

            // Handle Token Error
            $isTokenError = false;
            if ($isJson) {
                $errorCode = $json['error'] ?? $json['err_code'] ?? null;
                if (in_array($errorCode, ['error_auth', 'invalid_access_token', 'invalid_acceess_token'])) {
                    $isTokenError = true;
                }
                if (isset($json['message']) && stripos($json['message'], 'access_token') !== false) {
                    $isTokenError = true;
                }
            }

            if ($isTokenError) {
                Log::info("Shopee Token Expired/Invalid during Download. Refreshing...");
                $this->refreshAccessToken();
                $store->refresh();
                
                // Retry
                $response = $execute();
                $body = $response->body();
                // Re-evaluate JSON after retry
                if (strpos($response->header('Content-Type'), 'application/json') !== false || substr(trim($body), 0, 1) === '{') {
                    $json = $response->json();
                    $isJson = true;
                }
            }

            if ($response->failed() || ($isJson && (isset($json['error']) || isset($json['err_code'])) && !empty($json['error'] ?? $json['err_code']))) {
                $errorBody = $isJson ? json_encode($json) : $body;
                Log::error('Shopee downloadShippingDocument failed', ['body' => $errorBody]);
                throw new \Exception("Shopee API Error: " . $errorBody);
            }

            return $body; // Return raw content (likely PDF)

        } catch (\Exception $e) {
            Log::error('Shopee downloadShippingDocument exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getChannelList()
    {
        $path = "/api/v2/logistics/get_channel_list";
        return $this->request('GET', $path);
    }

    public function refreshAccessToken()
    {
        $store = $this->getStore();
        
        $host = $store->marketplace->base_api_url;
        $path = "/api/v2/auth/access_token/get";
        $partnerId = (int)$store->client_id;
        $partnerKey = $store->client_secret;
        $shopId = (int)($store->shop_id ?? $store->store_code);
        
        $refreshToken = $store->refresh_token;
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
            // Attempt fallback via auth_code if refresh token expired
            $body = $response->body();
            if (stripos($body, 'refresh_token_expired') !== false) {
                $code = $store->auth_code ?? null;
                if ($code && $store->shop_id) {
                    try {
                        Log::warning('Refresh token expired. Attempting exchange via auth_code fallback...');
                        return $this->exchangeAuthCodeForToken($code, (int)$store->shop_id);
                    } catch (\Exception $e) {
                        throw new \Exception("Shopee API Error: refresh_token_expired; exchange via auth_code failed: " . $e->getMessage());
                    }
                }
            }
            throw new \Exception("Shopee API Error: " . $body);
        }

        $json = $response->json();
        if (isset($json['error']) && !empty($json['error'])) {
            // Handle explicit refresh_token_expired with fallback via auth_code
            if ($json['error'] === 'refresh_token_expired') {
                $code = $store->auth_code ?? null;
                if ($code && $store->shop_id) {
                    try {
                        Log::warning('Refresh token expired. Attempting exchange via auth_code fallback (JSON error path)...');
                        return $this->exchangeAuthCodeForToken($code, (int)$store->shop_id);
                    } catch (\Exception $e) {
                        throw new \Exception("refresh_token_expired; exchange via auth_code failed: " . $e->getMessage());
                    }
                }
            }
            throw new \Exception($json['message'] ?? 'Refresh token failed');
        }

        $store->update([
            'access_token' => $json['access_token'] ?? $store->access_token,
            'refresh_token' => $json['refresh_token'] ?? $store->refresh_token,
            'access_token_expires_at' => isset($json['expire_in']) ? now()->addSeconds($json['expire_in']) : $store->access_token_expires_at,
        ]);

        return $json;
    }

    public function exchangeAuthCodeForToken(string $code, ?int $shopId = null): array
    {
        $store = $this->getStore();

        $host = $store->marketplace->base_api_url;
        $path = "/api/v2/auth/token/get";
        $partnerId = (int)$store->client_id;
        $partnerKey = $store->client_secret;
        $shopId = $shopId ?? (int)($store->shop_id ?? $store->store_code);

        $timestamp = time();

        $baseString = sprintf("%s%s%s", $partnerId, $path, $timestamp);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $params = [
            'partner_id' => $partnerId,
            'timestamp' => $timestamp,
            'sign' => $sign,
        ];

        $data = [
            'code' => $code,
            'shop_id' => $shopId,
            'partner_id' => $partnerId,
        ];

        $response = Http::post($host . $path . '?' . http_build_query($params), $data);

        if ($response->failed()) {
            Log::error('Shopee exchangeAuthCodeForToken failed', ['body' => $response->body()]);
            throw new \Exception("Shopee API Error: " . $response->body());
        }

        $json = $response->json();
        if (isset($json['error']) && !empty($json['error'])) {
            throw new \Exception($json['message'] ?? 'Exchange auth code failed');
        }

        $accessExpireIn = isset($json['expire_in']) ? (int)$json['expire_in'] : null;
        $refreshExpireIn = isset($json['refresh_token_expire_in']) ? (int)$json['refresh_token_expire_in'] : null;

        $store->update([
            'access_token' => $json['access_token'] ?? $store->access_token,
            'refresh_token' => $json['refresh_token'] ?? $store->refresh_token,
            'access_token_expires_at' => $accessExpireIn ? Carbon::now()->addSeconds($accessExpireIn) : $store->access_token_expires_at,
            'refresh_token_expires_at' => $refreshExpireIn ? Carbon::now()->addSeconds($refreshExpireIn) : $store->refresh_token_expires_at,
        ]);

        return $json;
    }
}
