<?php
return [
    'lazada' => [
        'base_url' => env('LAZADA_API_URL', 'https://api.lazada.com'),
        'access_token' => env('LAZADA_ACCESS_TOKEN'),
        'app_key' => env('LAZADA_APP_KEY'),
    ],

    'tiktok_shop' => [
        'base_url' => env('TIKTOKSHOP_API_URL', 'https://open-api.tiktokglobalshop.com'),
        'auth_url' => env('TIKTOKSHOP_AUTH_URL', 'https://auth.tiktok-shops.com/api/v2/token/get'),
        'app_key' => env('TIKTOKSHOP_APP_KEY', '6i5289l7qjvro'),
        'app_secret' => env('TIKTOKSHOP_APP_SECRET', '6665ae720ac777940b808d597e0187e842a7d825'),
        'auth_code' => env('TIKTOKSHOP_AUTH_CODE'),
        'access_token' => env('TIKTOKSHOP_ACCESS_TOKEN'),
        'order_page_size' => env('TIKTOKSHOP_ORDER_PAGE_SIZE', 20),
        'tls_verify' => env('TIKTOKSHOP_TLS_VERIFY', true),
        'ca_bundle' => env('TIKTOKSHOP_CA_BUNDLE'),
    ],

    'shopee' => [
        'base_url' => env('SHOPEE_HOST', 'https://partner.shopeemobile.com'),
        'partner_id' => env('SHOPEE_PARTNER_ID', 2014481),
        'partner_key' => env('SHOPEE_PARTNER_KEY', ''),
        'shop_id' => env('SHOPEE_SHOP_ID', 0),
        'access_token' => env('SHOPEE_ACCESS_TOKEN', ''),
        'refresh_token' => env('SHOPEE_REFRESH_TOKEN', ''),
        'redirect_url' => env('SHOPEE_REDIRECT_URL', ''),
    ],
];
