<?php

use App\Http\Controllers\Api\LazadaAuthController;
use App\Http\Controllers\Api\ShopeeController;
use App\Http\Controllers\Api\TiktokAuthController;
use App\Http\Controllers\LogViewerController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (file_exists(public_path('index.html'))) {
        return file_get_contents(public_path('index.html'));
    }
    return view('welcome');
});

// Lazada OAuth Flow
// Route::middleware(['checkrole:admin'])->group(function () {
//     Route::get('/lazada/login/{id}', [LazadaAuthController::class, 'redirectToLazada']);
//     Route::get('/lazada/refresh/{id}', [LazadaAuthController::class, 'refreshToken']);
// });
Route::get('/lazada/login/{id}', [LazadaAuthController::class, 'redirectToLazada']);
Route::get('/lazada/refresh/{id}', [LazadaAuthController::class, 'refreshToken']);

// Callback – must remain public (Lazada needs access)
Route::get('/lazada/callback', [LazadaAuthController::class, 'handleCallback']);

Route::get('/shopee/login/{id}', [ShopeeController::class, 'redirectToShopee']);
Route::get('/shopee/callback', [ShopeeController::class, 'handleCallback']);
Route::get('/shopee/refresh/{id}', [ShopeeController::class, 'refreshToken']);

Route::get('/shopee_sandbox/login/{id}', [ShopeeController::class, 'redirectToShopee']);
Route::get('/shopee_sandbox/callback', [ShopeeController::class, 'handleCallback']);
Route::get('/shopee_sandbox/refresh/{id}', [ShopeeController::class, 'refreshToken']);

Route::get('/tiktok-shop/login/{id}', [TiktokAuthController::class, 'redirectToTiktok']);
Route::get('/tiktok-shop/callback', [TiktokAuthController::class, 'handleCallback']);
Route::get('/tiktok-shop/refresh/{id}', [TiktokAuthController::class, 'refreshToken']);

Route::get('/tiktok_shop/login/{id}', [TiktokAuthController::class, 'redirectToTiktok']);
Route::get('/tiktok_shop/callback', [TiktokAuthController::class, 'handleCallback']);
Route::get('/tiktok_shop/refresh/{id}', [TiktokAuthController::class, 'refreshToken']);

Route::get('/logs', [LogViewerController::class, 'index']);

// SPA fallback: Serve the React index.html for unmatched web requests (for browser users)
Route::fallback(function () {
    if (file_exists(public_path('index.html'))) {
        return file_get_contents(public_path('index.html'));
    }
    return view('welcome');
});
