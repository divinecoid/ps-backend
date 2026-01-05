<?php

return [
    'lazada' => [
        'base_url' => env('LAZADA_API_URL', 'https://api.lazada.com'),
        'access_token' => env('LAZADA_ACCESS_TOKEN'),
        'app_key' => env('LAZADA_APP_KEY'),
    ],

    'tiktok_shop' => [
        'base_url' => env('TIKTOKSHOP_API_URL', 'https://open-api.tiktokglobalshop.com'),
        'access_token' => env('TIKTOKSHOP_ACCESS_TOKEN'),
        'app_key' => env('TIKTOKSHOP_APP_KEY'),
    ],

    'shopee' => [
        'base_url' => env('SHOPEE_HOST', 'https://partner.shopeemobile.com'), // Changed key to match standard
        'partner_id' => env('SHOPEE_PARTNER_ID', 2014481),
        'partner_key' => env('SHOPEE_PARTNER_KEY', 'shpk6356625343585571577678536858664c47597073665947435a68745a4d4d'),
        'shop_id' => env('SHOPEE_SHOP_ID', 46821355),
        'access_token' => env('SHOPEE_ACCESS_TOKEN', '775951786f7574494763504e59794f51'), // Default from config.json for dev
        'redirect_url' => env('SHOPEE_REDIRECT_URL', 'https://ps.divineproject.my.id'),
    ],
];
