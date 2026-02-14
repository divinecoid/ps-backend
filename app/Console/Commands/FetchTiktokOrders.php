<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\MasterData\OnlineStore;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class FetchTiktokOrders extends Command
{
    protected $signature = 'tiktok:fetch-orders {--days=1 : Number of days to look back}';

    protected $description = 'Fetch orders from TikTok Shop and log the raw response';

    public function handle()
    {
        $this->info('Starting TikTok Shop Order Fetch...');

        $days = (int) $this->option('days');
        $timeTo = time();
        $timeFrom = $timeTo - ($days * 24 * 60 * 60);

        $stores = OnlineStore::whereHas('marketplace', function ($q) {
                $q->where('code', 'tiktok_shop')
                  ->orWhere('alias', 'tiktok_shop');
            })
            ->where('is_active', true)
            ->get();

        if ($stores->isEmpty()) {
            $this->warn('No active TikTok Shop stores found.');
            return 0;
        }

        $this->info('Found ' . $stores->count() . ' active TikTok Shop stores.');

        foreach ($stores as $store) {
            $this->info('Processing Store: ' . $store->store_name . ' (' . $store->store_code . ')');

            $baseUrl = rtrim($store->store_url ?: ($store->marketplace->base_api_url ?? ''), '/');
            $appKey = $store->api_key;
            $clientSecret = $store->client_secret;
            $accessToken = $store->access_token;

            if (!$baseUrl || !$appKey || !$clientSecret || !$accessToken) {
                $this->warn('   Skipping store due to incomplete configuration (baseUrl/appKey/clientSecret/accessToken missing).');
                continue;
            }

            $shopCipher = $this->getShopCipher($baseUrl, $appKey, $clientSecret, $accessToken, $store, false);

            if (!$shopCipher) {
                $this->warn('   Skipping store because shop cipher could not be resolved.');
                continue;
            }

            $url = $baseUrl . '/order/202309/orders/search';

            $query = [
                'app_key' => $appKey,
                'page_size' => 20,
                'sort_field' => 'create_time',
                'sort_order' => 'ASC',
                'timestamp' => $timeTo,
                'shop_cipher' => $shopCipher,
                'shop_id' => $store->shop_id ?? '',
                'version' => '202309',
            ];

            $body = new \stdClass();

            $sign = $this->generateSignature('/order/202309/orders/search', $query, $clientSecret, $body);
            $query['sign'] = $sign;

            $this->info('   Requesting TikTok orders from ' . date('Y-m-d H:i:s', $timeFrom) . ' to ' . date('Y-m-d H:i:s', $timeTo));

            $headers = [
                'x-tts-access-token' => $accessToken,
                'content-type' => 'application/json',
            ];

            $response = Http::withHeaders($headers)->post($url . '?' . http_build_query($query), $body);

            if (!$response->ok()) {
                $this->error('   TikTok API HTTP error: ' . $response->status());
                $this->line($response->body());
                continue;
            }

            $json = $response->json();

            $code = $json['code'] ?? null;
            $message = $json['message'] ?? '';

            $this->info('   TikTok API responded with code: ' . $code);
            $this->info('   Message: ' . $message);

            $this->saveApiResponse('order_search_' . $store->store_code, [
                'request' => [
                    'method' => 'POST',
                    'url' => $url,
                    'query' => $query,
                    'body' => $body,
                    'headers' => $headers,
                ],
                'response' => $json,
            ]);
        }

        return 0;
    }

    private function getShopCipher(string $baseUrl, string $appKey, string $clientSecret, string $accessToken, OnlineStore $store, bool $hasRefreshed = false): ?string
    {
        if (!empty($store->shop_cipher)) {
            $this->info('   Using existing shop cipher from database.');
            return $store->shop_cipher;
        }

        $path = '/authorization/202309/shops';
        $timestamp = time();

        $queries = [
            'app_key' => $appKey,
            'timestamp' => (string) $timestamp,
        ];

        $sign = $this->generateSignature($path, $queries, $clientSecret, null);
        $queries['sign'] = $sign;

        $url = $baseUrl . $path . '?' . http_build_query($queries);

        $this->info('   Requesting TikTok authorized shops...');

        $headers = [
            'x-tts-access-token' => $accessToken,
            'content-type' => 'application/json',
        ];

        $response = Http::withHeaders($headers)->get($url);

        $json = $response->json();

        if (!$response->ok()) {
            $code = $json['code'] ?? null;
            $message = $json['message'] ?? '';

            if ($response->status() === 401 && ($code === 105002 || $code === '105002') && !$hasRefreshed) {
                $this->warn('   Access token appears to be expired. Attempting refresh...');

                if ($this->refreshAccessToken($store)) {
                    $this->info('   Refresh successful. Retrying authorized shops request...');

                    return $this->getShopCipher($baseUrl, $appKey, $clientSecret, $store->access_token, $store, true);
                }

                $this->error('   Failed to refresh TikTok access token.');
                return null;
            }

            $this->error('   TikTok Authorized Shops HTTP error: ' . $response->status());
            if ($message !== '') {
                $this->line($message);
            } else {
                $this->line($response->body());
            }
            return null;
        }

        $code = $json['code'] ?? null;
        $message = $json['message'] ?? '';

        $this->info('   Authorized Shops API responded with code: ' . $code);
        $this->info('   Message: ' . $message);

        $this->saveApiResponse('authorized_shops_' . $store->store_code, [
            'request' => [
                'method' => 'GET',
                'url' => $url,
                'query' => $queries,
                'headers' => $headers,
            ],
            'response' => $json,
        ]);

        if ($code !== 0 && $code !== '0') {
            $this->warn('   Authorized Shops API returned non-success code.');
            return null;
        }

        $data = $json['data'] ?? null;

        if (!is_array($data)) {
            $this->warn('   Authorized Shops response data is not an array.');
            return null;
        }

        $shop = null;

        if (isset($data['shops']) && is_array($data['shops']) && isset($data['shops'][0]) && is_array($data['shops'][0])) {
            $shop = $data['shops'][0];
        } elseif (isset($data['shop_list']) && is_array($data['shop_list']) && isset($data['shop_list'][0]) && is_array($data['shop_list'][0])) {
            $shop = $data['shop_list'][0];
        } elseif (isset($data[0]) && is_array($data[0])) {
            $shop = $data[0];
        }

        if (!is_array($shop)) {
            $this->warn('   Authorized Shops response does not contain shop information.');
            return null;
        }

        $cipher = null;
        $shopId = null;

        if (!empty($shop['cipher'])) {
            $cipher = (string) $shop['cipher'];
        } elseif (!empty($shop['shop_cipher'])) {
            $cipher = (string) $shop['shop_cipher'];
        }

        if (isset($shop['shop_id']) && $shop['shop_id'] !== null && $shop['shop_id'] !== '') {
            $shopId = (string) $shop['shop_id'];
        } elseif (isset($shop['id']) && $shop['id'] !== null && $shop['id'] !== '') {
            $shopId = (string) $shop['id'];
        }

        if ($cipher === null || $cipher === '') {
            $this->warn('   Could not determine shop cipher from Authorized Shops response.');
            return null;
        }

        $dirty = false;

        if ($shopId !== null && $store->shop_id !== $shopId) {
            $store->shop_id = $shopId;
            $dirty = true;
        }

        if ($store->shop_cipher !== $cipher) {
            $store->shop_cipher = $cipher;
            $dirty = true;
        }

        if ($dirty) {
            $store->save();
            $this->info('   Saved shop_id/shop_cipher to database.');
        }

        return $cipher;
    }

    private function refreshAccessToken(OnlineStore $store): bool
    {
        $appKey = $store->api_key;
        $clientSecret = $store->client_secret;
        $refreshToken = $store->refresh_token;

        if (!$appKey || !$clientSecret || !$refreshToken) {
            $this->warn('   Cannot refresh token because app_key/client_secret/refresh_token is missing.');
            return false;
        }

        $url = 'https://auth.tiktok-shops.com/api/v2/token/refresh';

        $query = [
            'app_key' => $appKey,
            'app_secret' => $clientSecret,
            'refresh_token' => $refreshToken,
            'grant_type' => 'refresh_token',
        ];

        $this->info('   Refreshing TikTok access token...');

        $response = Http::get($url, $query);

        if (!$response->ok()) {
            $this->error('   TikTok Refresh Token HTTP error: ' . $response->status());
            $this->line($response->body());
            return false;
        }

        $json = $response->json();

        if (!is_array($json)) {
            $this->warn('   Unexpected refresh token response format.');
            return false;
        }

        $code = $json['code'] ?? null;
        if ($code !== null && $code !== 0 && $code !== '0') {
            $message = $json['message'] ?? '';
            $this->warn('   Refresh token API returned non-success code: ' . $code . ($message !== '' ? ' - ' . $message : ''));
            return false;
        }

        $data = $json['data'] ?? $json;

        if (!is_array($data)) {
            $this->warn('   Refresh token data is not an array.');
            return false;
        }

        $newAccessToken = $data['access_token'] ?? null;

        if (!$newAccessToken) {
            $this->warn('   Refresh token response does not contain access_token.');
            return false;
        }

        $store->access_token = $newAccessToken;

        if (!empty($data['refresh_token'])) {
            $store->refresh_token = $data['refresh_token'];
        }

        if (!empty($data['access_token_expire_in']) && is_numeric($data['access_token_expire_in'])) {
            $store->access_token_expires_at = Carbon::createFromTimestamp((int) $data['access_token_expire_in']);
        }

        if (!empty($data['refresh_token_expire_in']) && is_numeric($data['refresh_token_expire_in'])) {
            $store->refresh_token_expires_at = Carbon::createFromTimestamp((int) $data['refresh_token_expire_in']);
        }

        $store->save();

        $this->info('   TikTok tokens refreshed and saved to database.');

        return true;
    }

    private function generateSignature(string $path, array $queries, string $secret, $body = null): string
    {
        $filtered = [];

        foreach ($queries as $key => $value) {
            if ($key === 'sign' || $key === 'access_token') {
                continue;
            }
            $filtered[$key] = (string) $value;
        }

        ksort($filtered);

        $input = $path;

        foreach ($filtered as $key => $value) {
            $input .= $key . $value;
        }

        if ($body !== null) {
            $bodyString = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $input .= $bodyString;
        }

        $input = $secret . $input . $secret;

        return hash_hmac('sha256', $input, $secret, false);
    }

    private function saveApiResponse(string $type, array $data): void
    {
        try {
            $path = storage_path('logs/tiktok/orders/' . date('Y-m-d'));
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $filename = sprintf(
                '%s_%s_%s.json',
                date('H-i-s'),
                $type,
                uniqid()
            );

            file_put_contents($path . '/' . $filename, json_encode($data, JSON_PRETTY_PRINT));
            $this->info('Saved JSON response to: ' . $path . '/' . $filename);
        } catch (\Exception $e) {
            $this->warn('Failed to save JSON response: ' . $e->getMessage());
        }
    }
}
