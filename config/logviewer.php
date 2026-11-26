<?php

/*
 * Basic-auth credentials for /logs and /seed-product. Without a password both are disabled.
 */
return [
    'user' => env('LOG_VIEWER_USER', 'admin'),
    'password' => env('LOG_VIEWER_PASSWORD'),
];
