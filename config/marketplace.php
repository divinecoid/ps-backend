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
        'base_url' => env('SHOPEE_API_URL', 'https://partner.shopeemobile.com/'),
        'access_token' => env('SHOPEE_ACCESS_TOKEN'),
        'app_key' => env('SHOPEE_APP_KEY'),
    ],
];
