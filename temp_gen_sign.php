<?php
use App\Models\MasterData\OnlineStore;

$partnerId = '1198129';
$path = '/api/v2/logistics/get_channel_list';
$timestamp = (string)time();
$accessToken = 'eyJhbGciOiJIUzI1NiJ9.CLGQSRABGP6N7WsgASjGvp_OBjCCuausBjgBQAE.oC_sjbBvM2TLkKtBgmr2e4O50WPApOcsiuZJEqKe320';
$shopId = '226182910';

$store = OnlineStore::where('client_id', $partnerId)->first();

if (!$store) {
    echo "Error: Store with partner_id $partnerId not found in DB.\n";
    exit(1);
}

$partnerKey = $store->client_secret;

// Shopee V2 Sign Pattern: partner_id + path + timestamp + access_token + shop_id
$baseString = $partnerId . $path . $timestamp . $accessToken . $shopId;
$sign = hash_hmac('sha256', $baseString, $partnerKey);

echo "Partner Key (from DB): " . $partnerKey . "\n";
echo "Base String: " . $baseString . "\n";
echo "New Sign: " . $sign . "\n";

$queryParams = [
    'partner_id' => $partnerId,
    'timestamp' => $timestamp,
    'sign' => $sign,
    'access_token' => $accessToken,
    'shop_id' => $shopId
];

$fullUrl = "https://openplatform.sandbox.test-stable.shopee.sg" . $path . "?" . http_build_query($queryParams);
echo "Full URL: " . $fullUrl . "\n";
