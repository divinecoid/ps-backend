<?php

use App\Http\Controllers\Api\LazadaAuthController;
use App\Http\Controllers\Api\ShopeeController;
use App\Http\Controllers\Api\TiktokAuthController;
use App\Http\Controllers\LogViewerController;
use App\Http\Controllers\ProductSeederController;
use App\Http\Middleware\LogViewerAuth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (file_exists(public_path('index.html'))) {
        return file_get_contents(public_path('index.html'));
    }
    return view('welcome');
});

// Marketplace OAuth.
// Login links open in a browser window without the API token, so they must be signed:
// the web app asks POST /api/marketplace-auth/connect-url for a short-lived link.
// Callbacks stay public because the marketplaces redirect to them.
// Token refresh is only available through the authenticated API.
$marketplaceLogins = [
    'shopee' => [ShopeeController::class, 'redirectToShopee'],
    'shopee_sandbox' => [ShopeeController::class, 'redirectToShopee'],
    'tiktok-shop' => [TiktokAuthController::class, 'redirectToTiktok'],
    'tiktok_shop' => [TiktokAuthController::class, 'redirectToTiktok'],
    'lazada' => [LazadaAuthController::class, 'redirectToLazada'],
];
foreach ($marketplaceLogins as $alias => $action) {
    Route::get("/{$alias}/login/{id}", $action)
        ->middleware('signed:relative')
        ->name("oauth.login.{$alias}");
}

Route::get('/lazada/callback', [LazadaAuthController::class, 'handleCallback']);
Route::get('/shopee/callback', [ShopeeController::class, 'handleCallback']);
Route::get('/shopee_sandbox/callback', [ShopeeController::class, 'handleCallback']);
Route::get('/tiktok-shop/callback', [TiktokAuthController::class, 'handleCallback']);
Route::get('/tiktok_shop/callback', [TiktokAuthController::class, 'handleCallback']);

// Throttle runs first so password guessing is rate limited
Route::middleware(['throttle:30,1', LogViewerAuth::class])->group(function () {
    Route::get('/logs', [LogViewerController::class, 'index']);
    Route::post('/logs/clear', [LogViewerController::class, 'clear']);

    // Maintenance tool that writes products: same operator login as the logs
    Route::get('/seed-product', [ProductSeederController::class, 'index']);
    Route::post('/seed-product', [ProductSeederController::class, 'store']);
});

// SPA fallback: Serve the React index.html for unmatched web requests (for browser users)
Route::fallback(function () {
    if (file_exists(public_path('index.html'))) {
        return file_get_contents(public_path('index.html'));
    }
    return view('welcome');
});
