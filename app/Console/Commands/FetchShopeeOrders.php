<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopeeService;
use Illuminate\Support\Facades\Log;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use App\Models\MasterData\Product;

class FetchShopeeOrders extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:fetch-orders {--days=1 : Number of days to look back}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Fetch orders from Shopee and process them';

    /**
     * Execute the console command.
     */
    public function handle(ShopeeService $shopeeService)
    {
        $this->info('Starting Shopee Order Fetch...');
        
        $days = $this->option('days');
        $timeTo = time();
        $timeFrom = $timeTo - ($days * 24 * 60 * 60);

        $this->info("Fetching orders from " . date('Y-m-d H:i:s', $timeFrom) . " to " . date('Y-m-d H:i:s', $timeTo));

        try {
            // 1. Get List of Orders
            $response = $shopeeService->getOrderList($timeFrom, $timeTo);
            
            if (isset($response['error']) && !empty($response['error'])) {
                $this->error('Shopee API Error: ' . ($response['message'] ?? 'Unknown error'));
                return 1;
            }

            $orders = $response['response']['order_list'] ?? [];
            $count = count($orders);
            
            $this->info("Found {$count} orders.");
            
            if ($count > 0) {
                // Extract all Order SNs
                $orderSns = array_column($orders, 'order_sn');
                
                // Chunk them if necessary (Shopee might have a limit per request, e.g. 50)
                // Assuming standard practice, let's chunk by 50
                $chunks = array_chunk($orderSns, 50);

                foreach ($chunks as $chunk) {
                    $this->info("Fetching details for " . count($chunk) . " orders...");
                    
                    // 2. Get Details for these orders
                    $detailResponse = $shopeeService->getOrderDetail($chunk);

                    if (isset($detailResponse['error']) && !empty($detailResponse['error'])) {
                        $this->error('Shopee Detail API Error: ' . ($detailResponse['message'] ?? 'Unknown error'));
                        continue;
                    }

                    $detailedOrders = $detailResponse['response']['order_list'] ?? [];

                    foreach ($detailedOrders as $detail) {
                        $this->line("--------------------------------------------------");
                        $this->line("🆔 Order SN   : " . $detail['order_sn']);
                        $this->line("👤 Buyer      : " . ($detail['buyer_username'] ?? '-'));
                        $this->line("💰 Total      : " . ($detail['total_amount'] ?? '-'));
                        $this->line("✉️  Note       : " . ($detail['message_to_seller'] ?? '-'));
                        
                        if (isset($detail['item_list'])) {
                            $this->line("📦 Items:");
                            foreach ($detail['item_list'] as $index => $item) {
                                $this->line("   " . ($index + 1) . ". " . $item['item_name'] . " [x" . $item['model_quantity_purchased'] . "]");
                            }
                        }
                        
                        $this->saveOrder($detail); 
                    }
                }
            }

            $this->info('Done.');
            return 0;

        } catch (\Exception $e) {
            $this->error('Exception: ' . $e->getMessage());
            Log::error('FetchShopeeOrders command failed', ['error' => $e->getMessage()]);
            return 1;
        }
    }

    protected function saveOrder($detail)
    {
        // Find Marketplace
        $marketplace = Marketplace::where('name', 'Shopee')->first();
        if (!$marketplace) {
            $this->error("Marketplace 'Shopee' not found in DB. Skipping order {$detail['order_sn']}.");
            return;
        }

        // Find OnlineStore
        // Assuming shop_id from config matches store_code
        $shopId = config('marketplace.shopee.shop_id');
        // Try to find by store_code (assuming it holds shop_id)
        $onlineStore = OnlineStore::where('store_code', $shopId)->first();
        
        if (!$onlineStore) {
             // Fallback: Try to find first online store for this marketplace
             $onlineStore = OnlineStore::where('marketplace_id', $marketplace->id)->first();
             if (!$onlineStore) {
                $this->error("OnlineStore for Shopee not found in DB. Skipping order {$detail['order_sn']}.");
                return;
             }
        }

        // Check if order exists
        $order = Order::where('order_sn', $detail['order_sn'])->first();

        if ($order) {
            $this->info("Order {$detail['order_sn']} already exists. Updating status...");
            $order->update([
                'marketplace_order_status' => $detail['order_status'],
                // Update other fields if needed
            ]);
            return;
        }

        // Calculate totals
        $itemCount = 0;
        $uniqueItemCount = count($detail['item_list'] ?? []);
        foreach ($detail['item_list'] ?? [] as $item) {
            $itemCount += $item['model_quantity_purchased'];
        }

        $totalShipping = $detail['actual_shipping_fee'] ?? $detail['estimated_shipping_fee'] ?? 0;

        // Create Order
        $order = Order::create([
            'order_sn' => $detail['order_sn'],
            'marketplace_order_status' => $detail['order_status'],
            'online_store_id' => $onlineStore->id,
            'marketplace_id' => $marketplace->id,
            'status' => 'pending', // Internal status
            'item_count' => $itemCount,
            'unique_item_count' => $uniqueItemCount,
            'total_price' => $detail['total_amount'] ?? 0,
            'total_shipping' => $totalShipping,
            'total_amount' => $detail['total_amount'] ?? 0,
            'total_weight' => 0, // Not always available
            'customer_name' => $detail['buyer_username'] ?? 'Unknown',
            'customer_address' => $detail['recipient_address']['full_address'] ?? null,
            'customer_phone' => $detail['recipient_address']['phone'] ?? null,
            'read_at' => now(),
            'preparist_user_id' => null, // Not assigned yet
        ]);

        // Create Order Items
        foreach ($detail['item_list'] ?? [] as $item) {
            // Find Product by SKU
            $product = null;
            if (!empty($item['item_sku'])) {
                $product = Product::where('sku', $item['item_sku'])->first();
            }

            OrderItem::create([
                'order_id' => $order->id,
                'order_item_id' => $item['order_item_id'],
                'sku' => $item['item_sku'] ?? null,
                'product_id' => $product ? $product->id : null,
                // 'quantity' => $item['model_quantity_purchased'], // Add this if column exists in DB
                // 'item_name' => $item['item_name'],
                // 'model_original_price' => $item['model_original_price'],
                // 'model_discounted_price' => $item['model_discounted_price'],
            ]);
        }
        
        $this->info("Order {$detail['order_sn']} saved successfully.");
    }
}
