<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopeeService;
use Illuminate\Support\Facades\Log;

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
                        
                        // TODO: Save to Database (Order, OrderItem, etc.)
                        // $this->saveOrder($detail); 
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
}
