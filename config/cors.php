<?php

return [

    'paths' => ['api/*', 'sanctum/csrf-cookie', '/lazada/*'],

    'allowed_methods' => ['*'],

    // Localhost only
    'allowed_origins' => [
        'http://localhost:3000',
        'http://localhost:5173',
        'http://localhost:8000',
        'http://localhost:5000',
        'http://127.0.0.1:8000',
        'http://127.0.0.1:3000',
        'http://127.0.0.1:5173',
        'http://156.67.219.209:8091',
        'https://ps.divineproject.my.id',
        'https://ps.divine.co.id',
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,

];
