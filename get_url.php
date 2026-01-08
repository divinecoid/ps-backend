$store = App\Models\MasterData\OnlineStore::where('store_name', 'Shopee Sandbox Store')->first();
$service = app(App\Services\ShopeeService::class);
$service->setStore($store);
echo "\n" . $service->generateAuthUrl() . "\n";
exit;
