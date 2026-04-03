<?php

use App\Http\Controllers\Transaction\MutationController;
use App\Http\Controllers\Transaction\RequestController;
use App\Http\Controllers\Transaction\InboundController;
use App\Http\Controllers\Transaction\OrderItemController;
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
use App\Http\Controllers\MasterData\ConfigurationController;
use App\Http\Controllers\Transaction\OrderController;
use App\Http\Controllers\Api\ShopeeController;
use App\Http\Controllers\Transaction\CheckerController;
use Illuminate\Http\JsonResponse;

//Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/refresh', [LoginController::class, 'refresh']);
    Route::post('/logout', [LoginController::class, 'logout']);
});

Route::middleware('checkrole:admin')->post('/register', RegisterController::class);

//Shopee Auth & Logistics
Route::prefix('shopee')->middleware('checkrole:admin')->group(function () {
    Route::post('/auth-url', [ShopeeController::class, 'generateAuthUrl']);
    Route::get('shopee/shipping-parameter', [ShopeeController::class, 'getShippingParameter']);
    Route::post('/ship-order', [ShopeeController::class, 'shipOrder']);
    Route::post('/download-shipping-document', [ShopeeController::class, 'downloadShippingDocument']);
});


//MasterData
//Role
Route::prefix('role')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [RoleController::class, 'index']);
    Route::get('/master', [RoleController::class, 'master']);
    Route::get('{id}', [RoleController::class, 'show']);
    Route::post('/', [RoleController::class, 'store']);
    Route::post('{id}/restore', [RoleController::class, 'restore']);
    Route::patch('{id}', [RoleController::class, 'update']);
    Route::delete('/', [RoleController::class, 'multiDestroy']);
    Route::delete('{id}', [RoleController::class, 'destroy']);
});
//User
Route::prefix('user')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [UserController::class, 'index']);
    Route::get('/master', [UserController::class, 'master']);
    Route::get('{id}', [UserController::class, 'show']);
    Route::post('/', [UserController::class, 'store']);
    Route::post('{id}/restore', [UserController::class, 'restore']);
    Route::patch('{id}', [UserController::class, 'update']);
    Route::delete('/', [UserController::class, 'multiDestroy']);
    Route::delete('{id}', [UserController::class, 'destroy']);
});
//Marketplace
Route::prefix('marketplace')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [MarketplaceController::class, 'index']);
    Route::get('/master', [MarketplaceController::class, 'master']);
    Route::get('{id}', [MarketplaceController::class, 'show']);
    Route::post('/', [MarketplaceController::class, 'store']);
    Route::post('{id}/restore', [MarketplaceController::class, 'restore']);
    Route::patch('{id}', [MarketplaceController::class, 'update']);
    Route::delete('/', [MarketplaceController::class, 'multiDestroy']);
    Route::delete('{id}', [MarketplaceController::class, 'destroy']);
});
//Online Store
Route::prefix('onlinestore')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [OnlineStoreController::class, 'index']);
    Route::get('/master', [OnlineStoreController::class, 'master']);
    Route::get('{id}', [OnlineStoreController::class, 'show']);
    Route::post('/', [OnlineStoreController::class, 'store']);
    Route::post('{id}/restore', [OnlineStoreController::class, 'restore']);
    Route::patch('{id}', [OnlineStoreController::class, 'update']);
    Route::delete('/', [OnlineStoreController::class, 'multiDestroy']);
    Route::delete('{id}', [OnlineStoreController::class, 'destroy']);
});
//Color
Route::prefix('color')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [ColorController::class, 'index']);
    Route::get('/master', [ColorController::class, 'master']);
    Route::get('{id}', [ColorController::class, 'show']);
    Route::post('/', [ColorController::class, 'store']);
    Route::post('{id}/restore', [ColorController::class, 'restore']);
    Route::patch('{id}', [ColorController::class, 'update']);
    Route::delete('/', [ColorController::class, 'multiDestroy']);
    Route::delete('{id}', [ColorController::class, 'destroy']);
});
//Model
Route::prefix('model')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [ProductModelController::class, 'index']);
    Route::get('/master', [ProductModelController::class, 'master']);
    Route::get('{id}', [ProductModelController::class, 'show']);
    Route::post('/', [ProductModelController::class, 'store']);
    Route::post('{id}/restore', [ProductModelController::class, 'restore']);
    Route::patch('{id}', [ProductModelController::class, 'update']);
    Route::delete('/', [ProductModelController::class, 'multiDestroy']);
    Route::delete('{id}', [ProductModelController::class, 'destroy']);
});
//Size
Route::prefix('size')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [SizeController::class, 'index']);
    Route::get('/master', [SizeController::class, 'master']);
    Route::get('{id}', [SizeController::class, 'show']);
    Route::post('/', [SizeController::class, 'store']);
    Route::post('{id}/restore', [SizeController::class, 'restore']);
    Route::patch('{id}', [SizeController::class, 'update']);
    Route::delete('/', [SizeController::class, 'multiDestroy']);
    Route::delete('{id}', [SizeController::class, 'destroy']);
});
//Product
Route::prefix('product')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/master', [ProductController::class, 'master']);
    Route::get('{id}', [ProductController::class, 'show']);
    Route::post('/', [ProductController::class, 'store']);
    Route::post('{id}/restore', [ProductController::class, 'restore']);
    Route::patch('{id}', [ProductController::class, 'update']);
    Route::delete('/', [ProductController::class, 'multiDestroy']);
    Route::delete('{id}', [ProductController::class, 'destroy']);
});
//Factory
Route::prefix('factory')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [FactoryController::class, 'index']);
    Route::get('/master', [FactoryController::class, 'master']);
    Route::get('{id}', [FactoryController::class, 'show']);
    Route::post('/', [FactoryController::class, 'store']);
    Route::post('{id}/restore', [FactoryController::class, 'restore']);
    Route::patch('{id}', [FactoryController::class, 'update']);
    Route::delete('/', [FactoryController::class, 'multiDestroy']);
    Route::delete('{id}', [FactoryController::class, 'destroy']);
});
//CMT
Route::prefix('cmt')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [CMTController::class, 'index']);
    Route::get('/master', [CMTController::class, 'master']);
    Route::get('{id}', [CMTController::class, 'show']);
    Route::post('/', [CMTController::class, 'store']);
    Route::post('{id}/restore', [CMTController::class, 'restore']);
    Route::patch('{id}', [CMTController::class, 'update']);
    Route::delete('/', [CMTController::class, 'multiDestroy']);
    Route::delete('{id}', [CMTController::class, 'destroy']);
});
//Inventory
Route::prefix('inventory')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::get('/master', [InventoryController::class, 'master']);
    Route::get('{id}', [InventoryController::class, 'show']);
    Route::post('/', [InventoryController::class, 'store']);
    Route::post('{id}/restore', [InventoryController::class, 'restore']);
    Route::patch('{id}', [InventoryController::class, 'update']);
    Route::delete('/', [InventoryController::class, 'multiDestroy']);
    Route::delete('{id}', [InventoryController::class, 'destroy']);
});
//Rack
Route::prefix('rack')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [RackController::class, 'index']);
    Route::get('/master', [RackController::class, 'master']);
    Route::get('{id}', [RackController::class, 'show']);
    Route::post('/', [RackController::class, 'store']);
    Route::post('{id}/restore', [RackController::class, 'restore']);
    Route::patch('{id}', [RackController::class, 'update']);
    Route::delete('/', [RackController::class, 'multiDestroy']);
    Route::delete('{id}', [RackController::class, 'destroy']);
});
//Warehouse
Route::prefix('warehouse')->middleware('checkrole')->group(function () {
    Route::get('/master', [WarehouseController::class, 'master']);
});
Route::prefix('warehouse')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::get('{id}', [WarehouseController::class, 'show']);
    Route::post('/', [WarehouseController::class, 'store']);
    Route::post('{id}/restore', [WarehouseController::class, 'restore']);
    Route::patch('{id}', [WarehouseController::class, 'update']);
    Route::delete('/', [WarehouseController::class, 'multiDestroy']);
    Route::delete('{id}', [WarehouseController::class, 'destroy']);
});


//Order
Route::prefix('order')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{id}', [OrderController::class, 'show']);
    Route::get('/lazada/{id}', [OrderController::class, 'getLazadaOrder']);
    Route::get('/tiktokshop/{id}', [OrderController::class, 'getTiktokShopOrder']);
    Route::get('/shopee/{id}', [OrderController::class, 'getShopeeOrder']);
});

//Order Items
Route::prefix('order')->middleware('checkrole:admin')->group(function () {
    Route::get('/items/lazada/{id}', [OrderController::class, 'getLazadaOrderItems']);
    Route::get('/items/tiktokshop/{id}', [OrderController::class, 'getTiktokShopOrderItems']);
    Route::get('/items/shopee/{id}', [OrderController::class, 'getShopeeOrderItems']);
});

//Outbound Operations
Route::prefix('outbound')->middleware('checkrole')->group(function () {
    Route::post('/validate-awb', [OrderItemController::class, 'validateAwb']);
    Route::get('/order-items/{orderId}', [OrderItemController::class, 'getOrderItems']);
    Route::post('/validate-product-barcode', [OrderItemController::class, 'validateProductBarcode']);
    Route::post('/submit-preparation', [OrderController::class, 'submitPreparation']);
    Route::post('/assign-order', [OrderController::class, 'assignToMe']);
    Route::post('/unassign-order', [OrderController::class, 'unassignOrder']);
    Route::get('/assigned-orders', [OrderController::class, 'assignedOrders']);
});

//Shopee Auth & Logistics
Route::prefix('shopee')->middleware('checkrole:admin')->group(function () {
    Route::post('/auth-url', [ShopeeController::class, 'generateAuthUrl']);
    Route::get('/shipping-parameter', [ShopeeController::class, 'getShippingParameter']);
    Route::post('/ship-order', [ShopeeController::class, 'shipOrder']);
    Route::post('/download-shipping-document', [ShopeeController::class, 'downloadShippingDocument']);
});

Route::get('shopee/fetch-orders', [ShopeeController::class, 'fetchOrders']);

Route::prefix('request')->middleware('checkrole')->group(function () {
    Route::get('/', [RequestController::class, 'index']);
    Route::get('/{id}', [RequestController::class, 'show']);
    Route::get('/barcode/{id}', [RequestController::class, 'barcode']);
    Route::post('/', [RequestController::class, 'store']);
    Route::delete('/{id}', [RequestController::class, 'destroy']);
});

// Inbound Receiving
Route::prefix('inbound')->middleware('checkrole')->group(function () {
    Route::get('/', [InboundController::class, 'index']);
    Route::get('/{id}', [InboundController::class, 'show']);
    Route::post('/', [InboundController::class, 'store']);
    Route::post('/validate', [InboundController::class, 'validate']);
    Route::post('/generate-next', [InboundController::class, 'generateNext']);
});

Route::prefix('model_color')->middleware('checkrole:admin')->group(function () {
    Route::get('/{id}', [ProductModelController::class, 'modelColor']);
});
Route::prefix('model_size')->middleware('checkrole:admin')->group(function () {
    Route::get('/{id}', [ProductModelController::class, 'modelSize']);
});

Route::prefix('mutation')->middleware('checkrole')->group(function () {
    Route::get('/', [MutationController::class, 'index']);
    Route::post('/', [MutationController::class, 'store']);
    Route::post('/validate', [MutationController::class, 'validate']);
});

//Configuration
Route::prefix('configuration')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [ConfigurationController::class, 'index']);
    Route::get('/master', [ConfigurationController::class, 'master']);
    Route::get('{id}', [ConfigurationController::class, 'show']);
    Route::get('{id}/histories', [ConfigurationController::class, 'histories']);
    Route::post('/', [ConfigurationController::class, 'store']);
    Route::post('{id}/restore', [ConfigurationController::class, 'restore']);
    Route::patch('{id}', [ConfigurationController::class, 'update']);
    Route::delete('/', [ConfigurationController::class, 'multiDestroy']);
    Route::delete('{id}', [ConfigurationController::class, 'destroy']);
});

Route::prefix('checker')->middleware('checkrole:admin')->group(function () {
    Route::get('/assigned-orders', [CheckerController::class, 'assignedOrders']);
    Route::get('/search', [CheckerController::class, 'searchOrders']);
    Route::get('/search-by-serial/{serial}', [CheckerController::class, 'getOrderBySerial']);
    Route::get('/order-items/{orderId}', [CheckerController::class, 'getOrderItems']);
    Route::post('/validate-product-barcode', [CheckerController::class, 'validateProductBarcode']);
    Route::patch('/approve-order/{id}', [CheckerController::class, 'approveOrder']);
});