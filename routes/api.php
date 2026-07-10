<?php

use App\Http\Controllers\Api\LazadaController;
use App\Http\Controllers\MasterData\AcmPermissionController;
use App\Http\Controllers\MasterData\AuditLogController;
use App\Http\Controllers\MasterData\ClothController;
use App\Http\Controllers\MasterData\RollSizeController;
use App\Http\Controllers\MasterData\SmallInventoryController;
use App\Http\Controllers\Transaction\FabricCuttingController;
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
use App\Http\Controllers\MasterData\NotificationController;
use App\Http\Controllers\MasterData\SizeController;
use App\Http\Controllers\MasterData\CMTController;
use App\Http\Controllers\MasterData\FactoryController;
use App\Http\Controllers\MasterData\ProductController;
use App\Http\Controllers\MasterData\RackController;
use App\Http\Controllers\MasterData\WarehouseController;
use App\Http\Controllers\MasterData\SequenceController;
use App\Http\Controllers\MasterData\ConfigurationController;
use App\Http\Controllers\Transaction\OrderController;
use App\Http\Controllers\Api\ShopeeController;
use App\Http\Controllers\Api\TiktokShopController;
use App\Http\Controllers\Transaction\CheckerController;
use App\Http\Controllers\Transaction\FabricPurchaseController;
use App\Http\Controllers\Transaction\DashboardController;
use Illuminate\Http\JsonResponse;
use App\Http\Controllers\Api\MarketplaceAuthController;
use App\Http\Controllers\Transaction\ManualOutboundController;

//Auth
Route::prefix('auth')->group(function () {
    Route::post('/login', [LoginController::class, 'login']);
    Route::post('/refresh', [LoginController::class, 'refresh']);
    Route::post('/logout', [LoginController::class, 'logout']);
});

Route::middleware('checkrole:admin')->post('/register', RegisterController::class);

// Marketplace Generic Auth
Route::prefix('marketplace-auth')->middleware('checkrole:admin')->group(function () {
    Route::post('/refresh-token', [MarketplaceAuthController::class, 'refreshToken']);
});

Route::prefix('tiktok-shop')->middleware('checkrole:admin')->group(function () {
    Route::get('/get-shop-cipher', [TiktokShopController::class, 'getShopCipher']);
    Route::get('/get-product/{productId}', [TiktokShopController::class, 'getProduct']);
    Route::get('/get-order-list', [TiktokShopController::class, 'getOrderList']);
    Route::get('/get-order/{orderId}', [TiktokShopController::class, 'getOrder']);
});

// ACM (admin only — admin bypasses ACM so no acm middleware needed here)
Route::prefix('acm')->middleware('checkrole:admin')->group(function () {
    Route::get('/{roleId}', [AcmPermissionController::class, 'index']);
    Route::post('/{roleId}', [AcmPermissionController::class, 'upsert']);
});

// Audit Log
Route::prefix('audit-log')->middleware(['checkrole', 'acm:audit_log,read'])->group(function () {
    Route::get('/', [AuditLogController::class, 'index']);
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
    Route::delete('/force', [RoleController::class, 'multiForceDestroy']);
    Route::delete('{id}', [RoleController::class, 'destroy']);
    Route::delete('{id}/force', [RoleController::class, 'forceDestroy']);
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
    Route::delete('/force', [UserController::class, 'multiForceDestroy']);
    Route::delete('{id}', [UserController::class, 'destroy']);
    Route::delete('{id}/force', [UserController::class, 'forceDestroy']);
});

Route::prefix('notification')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [NotificationController::class, 'index']);
    Route::get('/low-stock', [NotificationController::class, 'lowStock']);
    Route::patch('/{id}/read', [NotificationController::class, 'markAsRead']);
});

//Marketplace
Route::prefix('marketplace')->middleware(['checkrole:admin', 'acm:master_marketplace,read'])->group(function () {
    Route::get('/', [MarketplaceController::class, 'index']);
    Route::get('/master', [MarketplaceController::class, 'master']);
    Route::get('{id}', [MarketplaceController::class, 'show']);
});
Route::prefix('marketplace')->middleware(['checkrole:admin', 'acm:master_marketplace,create'])->group(function () {
    Route::post('/', [MarketplaceController::class, 'store']);
    Route::post('{id}/restore', [MarketplaceController::class, 'restore']);
});
Route::prefix('marketplace')->middleware(['checkrole:admin', 'acm:master_marketplace,update'])->group(function () {
    Route::patch('{id}', [MarketplaceController::class, 'update']);
});
Route::prefix('marketplace')->middleware(['checkrole:admin', 'acm:master_marketplace,delete'])->group(function () {
    Route::delete('/', [MarketplaceController::class, 'multiDestroy']);
    Route::delete('{id}', [MarketplaceController::class, 'destroy']);
});
Route::prefix('marketplace')->middleware(['checkrole:admin', 'acm:master_marketplace,force_delete'])->group(function () {
    Route::delete('/force', [MarketplaceController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [MarketplaceController::class, 'forceDestroy']);
});

//Online Store
Route::prefix('onlinestore')->middleware(['checkrole:admin', 'acm:master_toko,read'])->group(function () {
    Route::get('/', [OnlineStoreController::class, 'index']);
    Route::get('/master', [OnlineStoreController::class, 'master']);
    Route::get('{id}', [OnlineStoreController::class, 'show']);
});
Route::prefix('onlinestore')->middleware(['checkrole:admin', 'acm:master_toko,create'])->group(function () {
    Route::post('/', [OnlineStoreController::class, 'store']);
    Route::post('{id}/restore', [OnlineStoreController::class, 'restore']);
});
Route::prefix('onlinestore')->middleware(['checkrole:admin', 'acm:master_toko,update'])->group(function () {
    Route::patch('{id}', [OnlineStoreController::class, 'update']);
});
Route::prefix('onlinestore')->middleware(['checkrole:admin', 'acm:master_toko,delete'])->group(function () {
    Route::delete('/', [OnlineStoreController::class, 'multiDestroy']);
    Route::delete('{id}', [OnlineStoreController::class, 'destroy']);
});
Route::prefix('onlinestore')->middleware(['checkrole:admin', 'acm:master_toko,force_delete'])->group(function () {
    Route::delete('/force', [OnlineStoreController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [OnlineStoreController::class, 'forceDestroy']);
});

//Color
Route::prefix('color')->middleware(['checkrole:admin', 'acm:master_warna,read'])->group(function () {
    Route::get('/', [ColorController::class, 'index']);
    Route::get('/master', [ColorController::class, 'master']);
    Route::get('{id}', [ColorController::class, 'show']);
});
Route::prefix('color')->middleware(['checkrole:admin', 'acm:master_warna,create'])->group(function () {
    Route::post('/', [ColorController::class, 'store']);
    Route::post('{id}/restore', [ColorController::class, 'restore']);
});
Route::prefix('color')->middleware(['checkrole:admin', 'acm:master_warna,update'])->group(function () {
    Route::patch('{id}', [ColorController::class, 'update']);
});
Route::prefix('color')->middleware(['checkrole:admin', 'acm:master_warna,delete'])->group(function () {
    Route::delete('/', [ColorController::class, 'multiDestroy']);
    Route::delete('{id}', [ColorController::class, 'destroy']);
});
Route::prefix('color')->middleware(['checkrole:admin', 'acm:master_warna,force_delete'])->group(function () {
    Route::delete('/force', [ColorController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [ColorController::class, 'forceDestroy']);
});

//Model
Route::prefix('model')->middleware(['checkrole:admin', 'acm:master_model,read'])->group(function () {
    Route::get('/', [ProductModelController::class, 'index']);
    Route::get('/master', [ProductModelController::class, 'master']);
    Route::get('{id}', [ProductModelController::class, 'show']);
});
Route::prefix('model')->middleware(['checkrole:admin', 'acm:master_model,create'])->group(function () {
    Route::post('/', [ProductModelController::class, 'store']);
    Route::post('{id}/restore', [ProductModelController::class, 'restore']);
});
Route::prefix('model')->middleware(['checkrole:admin', 'acm:master_model,update'])->group(function () {
    Route::patch('{id}', [ProductModelController::class, 'update']);
});
Route::prefix('model')->middleware(['checkrole:admin', 'acm:master_model,delete'])->group(function () {
    Route::delete('/', [ProductModelController::class, 'multiDestroy']);
    Route::delete('{id}', [ProductModelController::class, 'destroy']);
});
Route::prefix('model')->middleware(['checkrole:admin', 'acm:master_model,force_delete'])->group(function () {
    Route::delete('/force', [ProductModelController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [ProductModelController::class, 'forceDestroy']);
});

//Size
Route::prefix('size')->middleware(['checkrole:admin', 'acm:master_ukuran,read'])->group(function () {
    Route::get('/', [SizeController::class, 'index']);
    Route::get('/master', [SizeController::class, 'master']);
    Route::get('{id}', [SizeController::class, 'show']);
});
Route::prefix('size')->middleware(['checkrole:admin', 'acm:master_ukuran,create'])->group(function () {
    Route::post('/', [SizeController::class, 'store']);
    Route::post('{id}/restore', [SizeController::class, 'restore']);
});
Route::prefix('size')->middleware(['checkrole:admin', 'acm:master_ukuran,update'])->group(function () {
    Route::patch('{id}', [SizeController::class, 'update']);
});
Route::prefix('size')->middleware(['checkrole:admin', 'acm:master_ukuran,delete'])->group(function () {
    Route::delete('/', [SizeController::class, 'multiDestroy']);
    Route::delete('{id}', [SizeController::class, 'destroy']);
});
Route::prefix('size')->middleware(['checkrole:admin', 'acm:master_ukuran,force_delete'])->group(function () {
    Route::delete('/force', [SizeController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [SizeController::class, 'forceDestroy']);
});

//Product
Route::prefix('product')->middleware(['checkrole:admin', 'acm:master_product,read'])->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::get('/master', [ProductController::class, 'master']);
    Route::get('{id}', [ProductController::class, 'show']);
});
Route::prefix('product')->middleware(['checkrole:admin', 'acm:master_product,create'])->group(function () {
    Route::post('/', [ProductController::class, 'store']);
    Route::post('{id}/restore', [ProductController::class, 'restore']);
});
Route::prefix('product')->middleware(['checkrole:admin', 'acm:master_product,update'])->group(function () {
    Route::patch('{id}', [ProductController::class, 'update']);
});
Route::prefix('product')->middleware(['checkrole:admin', 'acm:master_product,delete'])->group(function () {
    Route::delete('/', [ProductController::class, 'multiDestroy']);
    Route::delete('{id}', [ProductController::class, 'destroy']);
});
Route::prefix('product')->middleware(['checkrole:admin', 'acm:master_product,force_delete'])->group(function () {
    Route::delete('/force', [ProductController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [ProductController::class, 'forceDestroy']);
});

//Factory
Route::prefix('factory')->middleware(['checkrole:admin', 'acm:master_pabrik,read'])->group(function () {
    Route::get('/', [FactoryController::class, 'index']);
    Route::get('/master', [FactoryController::class, 'master']);
    Route::get('{id}', [FactoryController::class, 'show']);
});
Route::prefix('factory')->middleware(['checkrole:admin', 'acm:master_pabrik,create'])->group(function () {
    Route::post('/', [FactoryController::class, 'store']);
    Route::post('{id}/restore', [FactoryController::class, 'restore']);
});
Route::prefix('factory')->middleware(['checkrole:admin', 'acm:master_pabrik,update'])->group(function () {
    Route::patch('{id}', [FactoryController::class, 'update']);
});
Route::prefix('factory')->middleware(['checkrole:admin', 'acm:master_pabrik,delete'])->group(function () {
    Route::delete('/', [FactoryController::class, 'multiDestroy']);
    Route::delete('{id}', [FactoryController::class, 'destroy']);
});
Route::prefix('factory')->middleware(['checkrole:admin', 'acm:master_pabrik,force_delete'])->group(function () {
    Route::delete('/force', [FactoryController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [FactoryController::class, 'forceDestroy']);
});

//CMT
Route::prefix('cmt')->middleware(['checkrole:admin', 'acm:master_cmt,read'])->group(function () {
    Route::get('/', [CMTController::class, 'index']);
    Route::get('/master', [CMTController::class, 'master']);
    Route::get('{id}', [CMTController::class, 'show']);
});
Route::prefix('cmt')->middleware(['checkrole:admin', 'acm:master_cmt,create'])->group(function () {
    Route::post('/', [CMTController::class, 'store']);
    Route::post('{id}/restore', [CMTController::class, 'restore']);
});
Route::prefix('cmt')->middleware(['checkrole:admin', 'acm:master_cmt,update'])->group(function () {
    Route::patch('{id}', [CMTController::class, 'update']);
});
Route::prefix('cmt')->middleware(['checkrole:admin', 'acm:master_cmt,delete'])->group(function () {
    Route::delete('/', [CMTController::class, 'multiDestroy']);
    Route::delete('{id}', [CMTController::class, 'destroy']);
});
Route::prefix('cmt')->middleware(['checkrole:admin', 'acm:master_cmt,force_delete'])->group(function () {
    Route::delete('/force', [CMTController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [CMTController::class, 'forceDestroy']);
});

//Inventory Small
Route::prefix('small-inventory')->middleware(['checkrole', 'acm:gudang_kecil,read'])->group(function () {
    Route::get('/', [SmallInventoryController::class, 'index']);
    Route::get('/master', [SmallInventoryController::class, 'master']);
    Route::get('{id}', [SmallInventoryController::class, 'show']);
});

//Inventory Large (Gudang Besar)
Route::prefix('inventory')->middleware(['checkrole', 'acm:gudang_besar,read'])->group(function () {
    Route::get('/', [InventoryController::class, 'index']);
    Route::get('/master', [InventoryController::class, 'master']);
    Route::get('{id}', [InventoryController::class, 'show']);
});
Route::prefix('inventory')->middleware(['checkrole', 'acm:gudang_besar,create'])->group(function () {
    Route::post('/', [InventoryController::class, 'store']);
    Route::post('{id}/restore', [InventoryController::class, 'restore']);
});
Route::prefix('inventory')->middleware(['checkrole', 'acm:gudang_besar,update'])->group(function () {
    Route::patch('{id}', [InventoryController::class, 'update']);
});
Route::prefix('inventory')->middleware(['checkrole', 'acm:gudang_besar,delete'])->group(function () {
    Route::delete('/', [InventoryController::class, 'multiDestroy']);
    Route::delete('{id}', [InventoryController::class, 'destroy']);
});
Route::prefix('inventory')->middleware(['checkrole', 'acm:gudang_besar,force_delete'])->group(function () {
    Route::delete('/force', [InventoryController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [InventoryController::class, 'forceDestroy']);
});

//Rack
Route::prefix('rack')->middleware(['checkrole:admin', 'acm:master_rak,read'])->group(function () {
    Route::get('/', [RackController::class, 'index']);
    Route::get('/master', [RackController::class, 'master']);
    Route::get('{id}', [RackController::class, 'show']);
});
Route::prefix('rack')->middleware(['checkrole:admin', 'acm:master_rak,create'])->group(function () {
    Route::post('/', [RackController::class, 'store']);
    Route::post('{id}/restore', [RackController::class, 'restore']);
});
Route::prefix('rack')->middleware(['checkrole:admin', 'acm:master_rak,update'])->group(function () {
    Route::patch('{id}', [RackController::class, 'update']);
});
Route::prefix('rack')->middleware(['checkrole:admin', 'acm:master_rak,delete'])->group(function () {
    Route::delete('/', [RackController::class, 'multiDestroy']);
    Route::delete('{id}', [RackController::class, 'destroy']);
});
Route::prefix('rack')->middleware(['checkrole:admin', 'acm:master_rak,force_delete'])->group(function () {
    Route::delete('/force', [RackController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [RackController::class, 'forceDestroy']);
});

//Warehouse
Route::prefix('warehouse')->middleware('checkrole')->group(function () {
    Route::get('/master', [WarehouseController::class, 'master']);
});
Route::prefix('warehouse')->middleware(['checkrole:admin', 'acm:master_gudang,read'])->group(function () {
    Route::get('/', [WarehouseController::class, 'index']);
    Route::get('{id}', [WarehouseController::class, 'show']);
});
Route::prefix('warehouse')->middleware(['checkrole:admin', 'acm:master_gudang,create'])->group(function () {
    Route::post('/', [WarehouseController::class, 'store']);
    Route::post('{id}/restore', [WarehouseController::class, 'restore']);
});
Route::prefix('warehouse')->middleware(['checkrole:admin', 'acm:master_gudang,update'])->group(function () {
    Route::patch('{id}', [WarehouseController::class, 'update']);
});
Route::prefix('warehouse')->middleware(['checkrole:admin', 'acm:master_gudang,delete'])->group(function () {
    Route::delete('/', [WarehouseController::class, 'multiDestroy']);
    Route::delete('{id}', [WarehouseController::class, 'destroy']);
});
Route::prefix('warehouse')->middleware(['checkrole:admin', 'acm:master_gudang,force_delete'])->group(function () {
    Route::delete('/force', [WarehouseController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [WarehouseController::class, 'forceDestroy']);
});

//Roll size
Route::prefix('roll-size')->middleware(['checkrole:admin', 'acm:master_roll_size,read'])->group(function () {
    Route::get('/', [RollSizeController::class, 'index']);
    Route::get('/master', [RollSizeController::class, 'master']);
    Route::get('{id}', [RollSizeController::class, 'show']);
});
Route::prefix('roll-size')->middleware(['checkrole:admin', 'acm:master_roll_size,create'])->group(function () {
    Route::post('/', [RollSizeController::class, 'store']);
    Route::post('{id}/restore', [RollSizeController::class, 'restore']);
});
Route::prefix('roll-size')->middleware(['checkrole:admin', 'acm:master_roll_size,update'])->group(function () {
    Route::patch('{id}', [RollSizeController::class, 'update']);
});
Route::prefix('roll-size')->middleware(['checkrole:admin', 'acm:master_roll_size,delete'])->group(function () {
    Route::delete('/', [RollSizeController::class, 'multiDestroy']);
    Route::delete('{id}', [RollSizeController::class, 'destroy']);
});
Route::prefix('roll-size')->middleware(['checkrole:admin', 'acm:master_roll_size,force_delete'])->group(function () {
    Route::delete('/force', [RollSizeController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [RollSizeController::class, 'forceDestroy']);
});

//Cloth (Gudang Kain)
Route::prefix('cloth')->middleware(['checkrole', 'acm:gudang_kain,read'])->group(function () {
    Route::get('/', [ClothController::class, 'index']);
    Route::get('/uncut', [ClothController::class, 'uncut']);
    Route::get('/master', [ClothController::class, 'master']);
    Route::get('{id}', [ClothController::class, 'show']);
});
Route::prefix('cloth')->middleware(['checkrole:admin', 'acm:master_roll_size,create'])->group(function () {
    Route::post('/', [ClothController::class, 'store']);
    Route::post('{id}/restore', [ClothController::class, 'restore']);
});
Route::prefix('cloth')->middleware(['checkrole:admin', 'acm:master_roll_size,update'])->group(function () {
    Route::patch('{id}', [ClothController::class, 'update']);
});
Route::prefix('cloth')->middleware(['checkrole:admin', 'acm:master_roll_size,delete'])->group(function () {
    Route::delete('/', [ClothController::class, 'multiDestroy']);
    Route::delete('{id}', [ClothController::class, 'destroy']);
});
Route::prefix('cloth')->middleware(['checkrole:admin', 'acm:master_roll_size,force_delete'])->group(function () {
    Route::delete('/force', [ClothController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [ClothController::class, 'forceDestroy']);
});

//Sequence
Route::prefix('sequence')->middleware('checkrole:admin')->group(function () {
    Route::get('/', [SequenceController::class, 'index']);
    Route::get('/master', [SequenceController::class, 'master']);
    Route::get('{id}', [SequenceController::class, 'show']);
    Route::post('/', [SequenceController::class, 'store']);
    Route::post('{id}/restore', [SequenceController::class, 'restore']);
    Route::patch('{id}', [SequenceController::class, 'update']);
    Route::delete('/', [SequenceController::class, 'multiDestroy']);
    Route::delete('/force', [SequenceController::class, 'multiForceDestroy']);
    Route::delete('{id}', [SequenceController::class, 'destroy']);
    Route::delete('{id}/force', [SequenceController::class, 'forceDestroy']);
});

//Order (Pesanan Toko Online)
Route::prefix('order')->middleware(['checkrole', 'acm:pesanan,read'])->group(function () {
    Route::get('/', [OrderController::class, 'index']);
    Route::get('/{id}', [OrderController::class, 'show']);
    Route::get('/lazada/{id}', [OrderController::class, 'getLazadaOrder']);
    Route::get('/tiktokshop/{id}', [OrderController::class, 'getTiktokShopOrder']);
    Route::get('/shopee/{id}', [OrderController::class, 'getShopeeOrder']);
});

//Order Items
Route::prefix('order')->middleware(['checkrole', 'acm:pesanan,read'])->group(function () {
    Route::get('/items/lazada/{id}', [OrderController::class, 'getLazadaOrderItems']);
    Route::get('/items/tiktokshop/{id}', [OrderController::class, 'getTiktokShopOrderItems']);
    Route::get('/items/shopee/{id}', [OrderController::class, 'getShopeeOrderItems']);
});

//Dashboard Operations
Route::prefix('dashboard')->middleware('checkrole')->group(function () {
    Route::get('/stats', [DashboardController::class, 'getStats']);
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

//Outbound Manual (Pengeluaran Stok Manual)
Route::prefix('outbound-manual')->middleware(['checkrole', 'acm:outbound_manual,read'])->group(function () {
    Route::get('/', [ManualOutboundController::class, 'index']);
    Route::get('/{id}', [ManualOutboundController::class, 'show']);
});
Route::prefix('outbound-manual')->middleware(['checkrole', 'acm:outbound_manual,create'])->group(function () {
    Route::post('/validate-barcode', [ManualOutboundController::class, 'validateBarcode']);
    Route::post('/submit', [ManualOutboundController::class, 'submit']);
});

//Shopee Auth & Logistics
Route::prefix('shopee')->middleware('checkrole:admin')->group(function () {
    Route::post('/auth-url', [ShopeeController::class, 'generateAuthUrl']);
    Route::get('/shipping-parameter', [ShopeeController::class, 'getShippingParameter']);
    Route::post('/ship-order', [ShopeeController::class, 'shipOrder']);
    Route::post('/create-shipping-document', [ShopeeController::class, 'createShippingDocument']);
    Route::post('/download-shipping-document', [ShopeeController::class, 'downloadShippingDocument']);
    Route::get('/sync-shipping-logistics', [ShopeeController::class, 'syncShippingLogistics']);
    Route::get('/redirect/{id}', [ShopeeController::class, 'redirectToShopee']);
});

Route::prefix('lazada')->middleware('checkrole:admin')->group(function() {
    Route::get('/get-order/{id}', [LazadaController::class, 'getOrderList']);
    Route::get('/get-order/{id}/{orderId}', [LazadaController::class, 'getOrder']);
    Route::get('/get-order/{id}/{orderId}/item', [LazadaController::class, 'getOrderItem']);
    Route::get('/get-order/{id}/{orderId}/pickup', [LazadaController::class, 'pickupOrder']);
    Route::get('/get-order/{id}/{orderId}/download', [LazadaController::class, 'getReceipt']);
});

Route::get('shopee/callback', [ShopeeController::class, 'handleCallback']);
Route::get('shopee/fetch-orders', [ShopeeController::class, 'fetchOrders']);

//Request CMT (Permintaan)
Route::prefix('request')->middleware(['checkrole', 'acm:permintaan,read'])->group(function () {
    Route::get('/search-cmt', [RequestController::class, 'searchCmt']);
    Route::get('/', [RequestController::class, 'index']);
    Route::get('/{id}', [RequestController::class, 'show']);
    Route::get('/barcode/{id}', [RequestController::class, 'barcode']);
});
Route::prefix('request')->middleware(['checkrole', 'acm:permintaan,create'])->group(function () {
    Route::post('/', [RequestController::class, 'store']);
});
Route::prefix('request')->middleware(['checkrole', 'acm:permintaan,delete'])->group(function () {
    Route::delete('/{id}', [RequestController::class, 'destroy']);
});

//Fabric Purchase (Pembelian Kain)
Route::prefix('fabric-purchase')->middleware(['checkrole', 'acm:pembelian_kain,read'])->group(function () {
    Route::get('/', [FabricPurchaseController::class, 'index']);
    Route::get('/series', [FabricPurchaseController::class, 'series']);
    Route::get('/{id}', [FabricPurchaseController::class, 'show']);
});
Route::prefix('fabric-purchase')->middleware(['checkrole', 'acm:pembelian_kain,create'])->group(function () {
    Route::post('/', [FabricPurchaseController::class, 'store']);
});
Route::prefix('fabric-purchase')->middleware(['checkrole', 'acm:pembelian_kain,update'])->group(function () {
    Route::patch('/{id}/complete', [FabricPurchaseController::class, 'complete']);
});
Route::prefix('fabric-purchase')->middleware(['checkrole', 'acm:pembelian_kain,delete'])->group(function () {
    Route::delete('/{id}', [FabricPurchaseController::class, 'destroy']);
});

// Inbound Receiving (Penerimaan CMT)
Route::prefix('inbound')->middleware(['checkrole', 'acm:penerimaan,read'])->group(function () {
    Route::get('/', [InboundController::class, 'index']);
    Route::get('/{id}', [InboundController::class, 'show']);
});
Route::prefix('inbound')->middleware(['checkrole', 'acm:penerimaan,create'])->group(function () {
    Route::post('/', [InboundController::class, 'store']);
    Route::post('/validate', [InboundController::class, 'validate']);
    Route::post('/generate-next', [InboundController::class, 'generateNext']);
});

Route::prefix('model_color')->middleware(['checkrole:admin', 'acm:master_model,read'])->group(function () {
    Route::get('/{id}', [ProductModelController::class, 'modelColor']);
});
Route::prefix('model_size')->middleware(['checkrole:admin', 'acm:master_model,read'])->group(function () {
    Route::get('/{id}', [ProductModelController::class, 'modelSize']);
});

Route::get('/get-fabric-color/{id}', [ProductModelController::class, 'getFabricColor'])->middleware(['checkrole:admin', 'acm:master_model,read']);
Route::post('/get-fabric-qty', [ProductModelController::class, 'getFabricQty'])->middleware(['checkrole:admin', 'acm:master_model,read']);

//Mutation (Mutasi Gudang)
Route::prefix('mutation')->middleware(['checkrole', 'acm:mutasi,read'])->group(function () {
    Route::get('/', [MutationController::class, 'index']);
});
Route::prefix('mutation')->middleware(['checkrole', 'acm:mutasi,create'])->group(function () {
    Route::post('/', [MutationController::class, 'store']);
    Route::post('/validate', [MutationController::class, 'validate']);
});

//Configuration
Route::prefix('configuration')->middleware(['checkrole:admin', 'acm:master_konfigurasi,read'])->group(function () {
    Route::get('/', [ConfigurationController::class, 'index']);
    Route::get('/master', [ConfigurationController::class, 'master']);
    Route::get('{id}', [ConfigurationController::class, 'show']);
    Route::get('{id}/histories', [ConfigurationController::class, 'histories']);
});
Route::prefix('configuration')->middleware(['checkrole:admin', 'acm:master_konfigurasi,create'])->group(function () {
    Route::post('/', [ConfigurationController::class, 'store']);
    Route::post('{id}/restore', [ConfigurationController::class, 'restore']);
});
Route::prefix('configuration')->middleware(['checkrole:admin', 'acm:master_konfigurasi,update'])->group(function () {
    Route::patch('{id}', [ConfigurationController::class, 'update']);
});
Route::prefix('configuration')->middleware(['checkrole:admin', 'acm:master_konfigurasi,delete'])->group(function () {
    Route::delete('/', [ConfigurationController::class, 'multiDestroy']);
    Route::delete('{id}', [ConfigurationController::class, 'destroy']);
});
Route::prefix('configuration')->middleware(['checkrole:admin', 'acm:master_konfigurasi,force_delete'])->group(function () {
    Route::delete('/force', [ConfigurationController::class, 'multiForceDestroy']);
    Route::delete('{id}/force', [ConfigurationController::class, 'forceDestroy']);
});

Route::prefix('checker')->middleware('checkrole:admin')->group(function () {
    Route::get('/assigned-orders', [CheckerController::class, 'assignedOrders']);
    Route::get('/search', [CheckerController::class, 'searchOrders']);
    Route::get('/search-by-serial/{serial}', [CheckerController::class, 'getOrderBySerial']);
    Route::get('/order-items/{orderId}', [CheckerController::class, 'getOrderItems']);
    Route::post('/validate-product-barcode', [CheckerController::class, 'validateProductBarcode']);
    Route::patch('/approve-order/{id}', [CheckerController::class, 'approveOrder']);
});

//Fabric Cutting (Pemotongan Kain)
Route::prefix('fabric-cutting')->middleware(['checkrole', 'acm:pemotongan_kain,read'])->group(function () {
    Route::get('/search-cutting', [FabricCuttingController::class, 'searchCutting']);
    Route::get('/closed', [FabricCuttingController::class, 'closedIndex']);
    Route::get('/get-next-series', [FabricCuttingController::class, 'getNextSeries']);
    Route::get('/', [FabricCuttingController::class, 'index']);
    Route::get('/{id}', [FabricCuttingController::class, 'show']);
    Route::get('/{id}/fabrics', [FabricCuttingController::class, 'getFabrics']);
});
Route::prefix('fabric-cutting')->middleware(['checkrole', 'acm:permintaan,create'])->group(function () {
    Route::post('/', [FabricCuttingController::class, 'store']);
    Route::patch('/{id}', [FabricCuttingController::class, 'update']);
    Route::patch('/{id}/receive', [FabricCuttingController::class, 'setReceived']);
});
Route::prefix('fabric-cutting')->middleware(['checkrole', 'acm:permintaan,delete'])->group(function () {
    Route::delete('/{id}', [FabricCuttingController::class, 'destroy']);
});