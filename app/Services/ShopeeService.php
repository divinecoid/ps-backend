<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

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
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/order/get_order_list";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = config('marketplace.shopee.shop_id');
        $accessToken = config('marketplace.shopee.access_token');
        $timestamp = time();

        $baseString = sprintf("%s%s%s%s%s", $partnerId, $path, $timestamp, $accessToken, $shopId);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $params = [
            'partner_id' => (int)$partnerId,
            'timestamp' => $timestamp,
            'access_token' => $accessToken,
            'shop_id' => (int)$shopId,
            'sign' => $sign,
            'time_range_field' => 'create_time',
            'time_from' => $timeFrom,
            'time_to' => $timeTo,
            'page_size' => $pageSize,
            'cursor' => $cursor
            // 'order_status' => 'READY_TO_SHIP' // Optional
        ];

        try {
            $response = Http::get($host . $path, $params);

            if ($response->failed()) {
                Log::error('Shopee getOrderList failed', ['body' => $response->body()]);
                return ['error' => true, 'message' => $response->body()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Shopee getOrderList exception', ['message' => $e->getMessage()]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get Order Detail from Shopee
     * 
     * @param array $orderSnList
     * @return array
     */
    public function getOrderDetail(array $orderSnList)
    {
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/order/get_order_detail";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = config('marketplace.shopee.shop_id');
        $accessToken = config('marketplace.shopee.access_token');
        $timestamp = time();

        $baseString = sprintf("%s%s%s%s%s", $partnerId, $path, $timestamp, $accessToken, $shopId);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $params = [
            'partner_id' => (int)$partnerId,
            'timestamp' => $timestamp,
            'access_token' => $accessToken,
            'shop_id' => (int)$shopId,
            'sign' => $sign,
            'order_sn_list' => implode(',', $orderSnList),
            'response_optional_fields' => 'item_list,message_to_seller,buyer_username,total_amount,recipient_address,estimated_shipping_fee,actual_shipping_fee,shipping_carrier'
        ];

        try {
            $response = Http::get($host . $path, $params);

            if ($response->failed()) {
                Log::error('Shopee getOrderDetail failed', ['body' => $response->body()]);
                return ['error' => true, 'message' => $response->body()];
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Shopee getOrderDetail exception', ['message' => $e->getMessage()]);
            return ['error' => true, 'message' => $e->getMessage()];
        }
    }

    /**
     * Get Shipping Parameter
     * 
     * @param string $orderSn
     * @return array
     */
    public function getShippingParameter($orderSn)
    {
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/logistics/get_shipping_parameter";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = config('marketplace.shopee.shop_id');
        $accessToken = config('marketplace.shopee.access_token');
        $timestamp = time();

        $baseString = sprintf("%s%s%s%s%s", $partnerId, $path, $timestamp, $accessToken, $shopId);
        $sign = hash_hmac('sha256', $baseString, $partnerKey);

        $params = [
            'partner_id' => (int)$partnerId,
            'timestamp' => $timestamp,
            'access_token' => $accessToken,
            'shop_id' => (int)$shopId,
            'sign' => $sign,
            'order_sn' => $orderSn
        ];

        try {
            $response = Http::get($host . $path, $params);

            if ($response->failed()) {
                Log::error('Shopee getShippingParameter failed', ['body' => $response->body()]);
                throw new \Exception("Shopee API Error: " . $response->body());
            }

            return $response->json();
        } catch (\Exception $e) {
            Log::error('Shopee getShippingParameter exception', ['message' => $e->getMessage()]);
            throw $e;
        }
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
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/logistics/ship_order";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = config('marketplace.shopee.shop_id');
        $accessToken = config('marketplace.shopee.access_token');
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
            'order_sn' => $orderSn,
            'pickup' => [
                'address_id' => (int)$pickupData['address_id'],
                'pickup_time_id' => $pickupData['pickup_time_id']
            ]
        ];

        try {
            $response = Http::post($host . $path . '?' . http_build_query($params), $data);

            if ($response->failed()) {
                Log::error('Shopee shipOrder failed', ['body' => $response->body()]);
                throw new \Exception("Shopee API Error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Shopee shipOrder exception', ['message' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Create Shipping Document
     * 
     * @param string $orderSn
     * @return array
     */
    public function createShippingDocument($orderSn)
    {
        $host = config('marketplace.shopee.base_url');
        $path = "/api/v2/logistics/create_shipping_document";
        $partnerId = config('marketplace.shopee.partner_id');
        $partnerKey = config('marketplace.shopee.partner_key');
        $shopId = config('marketplace.shopee.shop_id');
        $accessToken = config('marketplace.shopee.access_token');
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
            'order_list' => [
                ['order_sn' => $orderSn]
            ]
        ];

        try {
            $response = Http::post($host . $path . '?' . http_build_query($params), $data);

            if ($response->failed()) {
                Log::error('Shopee createShippingDocument failed', ['body' => $response->body()]);
                throw new \Exception("Shopee API Error: " . $response->body());
            }

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Shopee createShippingDocument exception', ['message' => $e->getMessage()]);
            throw $e;
        }
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
        $shopId = config('marketplace.shopee.shop_id');
        $accessToken = config('marketplace.shopee.access_token');
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
}
