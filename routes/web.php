<?php

use App\Http\Controllers\Api\LazadaAuthController;
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
Route::get('/lazada/callback/{id}', [LazadaAuthController::class, 'handleCallback']);
