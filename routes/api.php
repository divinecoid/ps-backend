<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\MasterData\ColorController;
use App\Http\Controllers\MasterData\InventoryController;
use App\Http\Controllers\MasterData\MarketplaceController;
use App\Http\Controllers\MasterData\OnlineStoreController;
use App\Http\Controllers\MasterData\ProductModelController;
use App\Http\Controllers\MasterData\RoleController;
use App\Http\Controllers\MasterData\UserController;
use App\Http\Controllers\MasterData\SizeController;
use App\Http\Controllers\MasterData\CMTController;
use App\Http\Controllers\MasterData\FactoryController;
use App\Http\Controllers\MasterData\ProductController;
use App\Http\Controllers\MasterData\RackController;
use App\Http\Controllers\MasterData\WarehouseController;

//Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/refresh', [LoginController::class, 'refresh']);
    Route::post('/logout', [LoginController::class, 'logout']);
});

Route::middleware('checkrole:admin')->post('/register', RegisterController::class);

//MasterData
//Role
Route::prefix('role')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('{id}', [RoleController::class, 'show']);
    Route::post('/', [RoleController::class, 'store']);
    Route::patch('{id}', [RoleController::class, 'update']);
    Route::delete('{id}', [RoleController::class, 'destroy']);
});
//User
Route::prefix('user')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('{id}', [UserController::class, 'show']);
    Route::post('/', [UserController::class, 'store']);
    Route::patch('{id}', [UserController::class, 'update']);
    Route::delete('{id}', [UserController::class, 'destroy']);
});
//Marketplace
Route::prefix('marketplace')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [MarketplaceController::class, 'index']);
    Route::get('{id}', [MarketplaceController::class, 'show']);
    Route::post('/', [MarketplaceController::class, 'store']);
    Route::patch('{id}', [MarketplaceController::class, 'update']);
    Route::delete('{id}', [MarketplaceController::class, 'destroy']);
});
//Online Store
Route::prefix('onlinestore')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [OnlineStoreController::class, 'index']);
    Route::get('{id}', [OnlineStoreController::class, 'show']);
    Route::post('/', [OnlineStoreController::class, 'store']);
    Route::patch('{id}', [OnlineStoreController::class, 'update']);
    Route::delete('{id}', [OnlineStoreController::class, 'destroy']);
});
//Color
Route::prefix('color')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [ColorController::class, 'index']);
    Route::get('{id}', [ColorController::class, 'show']);
    Route::post('/', [ColorController::class, 'store']);
    Route::patch('{id}', [ColorController::class, 'update']);
    Route::delete('{id}', [ColorController::class, 'destroy']);
});
//Model
Route::prefix('model')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [ProductModelController::class, 'index']);
    Route::get('{id}', [ProductModelController::class, 'show']);
    Route::post('/', [ProductModelController::class, 'store']);
    Route::patch('{id}', [ProductModelController::class, 'update']);
    Route::delete('{id}', [ProductModelController::class, 'destroy']);
});
//Size
Route::prefix('size')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [SizeController::class, 'index']);
    Route::get('{id}', [SizeController::class, 'show']);
    Route::post('/', [SizeController::class, 'store']);
    Route::patch('{id}', [SizeController::class, 'update']);
    Route::delete('{id}', [SizeController::class, 'destroy']);
});
//Product
Route::prefix('product')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::patch('{id}', [ProductController::class, 'update']);
    Route::delete('{id}', [ProductController::class, 'destroy']);
});
//Factory
Route::prefix('factory')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [FactoryController::class, 'index']);
    Route::get('{id}', [FactoryController::class, 'show']);
    Route::post('/', [FactoryController::class, 'store']);
    Route::patch('{id}', [FactoryController::class, 'update']);
    Route::delete('{id}', [FactoryController::class, 'destroy']);
});
//CMT
Route::prefix('cmt')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [CMTController::class, 'index']);
    Route::get('{id}', [CMTController::class, 'show']);
    Route::post('/', [CMTController::class, 'store']);
    Route::patch('{id}', [CMTController::class, 'update']);
    Route::delete('{id}', [CMTController::class, 'destroy']);
});
//Inventory
Route::prefix('inventory')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::get('{id}', [InventoryController::class, 'show']);
    Route::post('/', [InventoryController::class, 'store']);
    Route::patch('{id}', [InventoryController::class, 'update']);
    Route::delete('{id}', [InventoryController::class, 'destroy']);
});
//Rack
Route::prefix('rack')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [RackController::class, 'index']);
    Route::get('{id}', [RackController::class, 'show']);
    Route::post('/', [RackController::class, 'store']);
    Route::post('{id}/restore', [RackController::class, 'restore']);
    Route::patch('{id}', [RackController::class, 'update']);
    Route::delete('{id}', [RackController::class, 'destroy']);
});
//Warehouse
Route::prefix('warehouse')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::get('{id}', [WarehouseController::class, 'show']);
    Route::post('/', [WarehouseController::class, 'store']);
    Route::post('{id}/restore', [WarehouseController::class, 'restore']);
    Route::patch('{id}', [WarehouseController::class, 'update']);
    Route::delete('{id}', [WarehouseController::class, 'destroy']);
});
