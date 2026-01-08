<?php

use App\Http\Controllers\Api\LazadaAuthController;
use App\Http\Controllers\Api\ShopeeController;
use App\Http\Controllers\Api\TiktokAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
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

Route::get('/tiktok-shop/login/{id}', [TiktokAuthController::class, 'redirectToTiktok']);
Route::get('/tiktok-shop/callback', [TiktokAuthController::class, 'handleCallback']);
Route::get('/tiktok-shop/refresh/{id}', [TiktokAuthController::class, 'refreshToken']);
