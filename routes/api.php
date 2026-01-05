//Shopee Auth & Logistics
Route::prefix('shopee')->middleware('checkrole:admin')->group(function () {
    Route::post('/auth-url', [ShopeeController::class, 'generateAuthUrl']);
    Route::get('/shipping-parameter', [ShopeeController::class, 'getShippingParameter']);
    Route::post('/ship-order', [ShopeeController::class, 'shipOrder']);
    Route::post('/download-shipping-document', [ShopeeController::class, 'downloadShippingDocument']);
});
