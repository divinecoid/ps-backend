<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ShopeeService;
use App\Models\MasterData\OnlineStore;
use App\Models\Transactions\Order;
use App\Models\Transactions\OrderItem;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

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

        // Fetch all active Shopee stores
        $stores = OnlineStore::whereHas('marketplace', function ($q) {
            $q->where('name', 'like', '%Shopee%')
              ->orWhere('alias', 'like', '%shopee%');
        })->where('is_active', true)->get();

        if ($stores->isEmpty()) {
            $this->warn("No active Shopee stores found.");
            return 0;
        }

        $this->info("Found " . $stores->count() . " active Shopee stores.");

        foreach ($stores as $store) {
            $this->info("Processing Store: " . $store->store_name . " (" . $store->store_code . ")");
            
            try {
                // Set the store context for the service
                $shopeeService->setStore($store);

                // Check and refresh token if needed
                if ($store->access_token_expires_at && Carbon::parse($store->access_token_expires_at)->lt(now()->addMinutes(5))) {
                    $this->info("[" . $store->store_name . "] Token expiring soon or expired. Refreshing...");
                    try {
                        $shopeeService->refreshAccessToken();
                        $store->refresh();
                        $this->info("[" . $store->store_name . "] Token refreshed successfully.");
                    } catch (\Exception $e) {
                        $msg = $e->getMessage();
                        // Downgrade to warning for known reauth scenario to avoid noisy logs before other stores succeed
                        if (stripos($msg, 'refresh_token_expired') !== false) {
                            $this->warn("[" . $store->store_name . "] Refresh token expired. Please re-authorize.");
                        } else {
                            $this->error("[" . $store->store_name . "] Failed to refresh token: " . $msg);
                        }
                        
                        // Check if error is due to expired refresh token
                        if (strpos($msg, 'refresh_token_expired') !== false) {
                            // already warned above; keep behavior to skip this store
                        }
                        
                        continue;
                    }
                }

                $this->info("Fetching orders from " . date('Y-m-d H:i:s', $timeFrom) . " to " . date('Y-m-d H:i:s', $timeTo));

                // Split time range into 15-day chunks because Shopee API limit
                $chunks = [];
                $currentStart = $timeFrom;
                while ($currentStart < $timeTo) {
                    $currentEnd = min($currentStart + (15 * 24 * 60 * 60), $timeTo);
                    // Ensure start < end
                    if ($currentEnd > $currentStart) {
                         $chunks[] = ['start' => $currentStart, 'end' => $currentEnd];
                    }
                    $currentStart = $currentEnd;
                }

                $allOrders = [];
                foreach ($chunks as $index => $chunk) {
                    $this->info(sprintf("Processing chunk %d/%d: %s to %s", 
                        $index + 1, 
                        count($chunks), 
                        date('Y-m-d H:i:s', $chunk['start']), 
                        date('Y-m-d H:i:s', $chunk['end'])
                    ));

                    // 1. Get List of Orders
                    // Note: We might need to handle pagination (cursor) here if orders > 50 in 15 days
                    // For now assuming getOrderList fetches first page, implementing simple loop if has_more is true
                    
                    $cursor = "";
                    do {
                        $response = $shopeeService->getOrderList($chunk['start'], $chunk['end'], 50, $cursor);
                        
                        // Log response to file (only first page to avoid spamming logs too much, or all pages)
                        $this->saveApiResponse('order_list_' . ($index+1), $store->store_name, $response);
                        
                        if (isset($response['error']) && !empty($response['error'])) {
                            // Check for token expiry in API response
                            if (strpos($response['message'] ?? '', 'access_token') !== false || 
                                ($response['error'] === 'error_auth') || 
                                ($response['error'] === 'invalid_access_token')) {
                                
                                $this->info("[" . $store->store_name . "] Access token expired during fetch. Attempting refresh...");
                                try {
                                    $shopeeService->refreshAccessToken();
                                    $store->refresh();
                                    $this->info("[" . $store->store_name . "] Token refreshed. Retrying fetch...");
                                    
                                    // Retry the request
                                    $response = $shopeeService->getOrderList($chunk['start'], $chunk['end'], 50, $cursor);
                                    if (isset($response['error']) && !empty($response['error'])) {
                                        $this->error('[' . $store->store_name . '] Retry failed: ' . ($response['message'] ?? 'Unknown error'));
                                        break;
                                    }
                                } catch (\Exception $e) {
                                    $msg = $e->getMessage();
                                    if (stripos($msg, 'refresh_token_expired') !== false) {
                                        $this->warn('[' . $store->store_name . '] Refresh token expired during fetch. Please re-authorize.');
                                    } else {
                                        $this->error("[" . $store->store_name . "] Failed to refresh token during fetch: " . $msg);
                                    }
                                    break;
                                }
                            } else {
                                $this->error('[' . $store->store_name . '] Shopee API Error: ' . ($response['message'] ?? 'Unknown error'));
                                break; 
                            }
                        }

                        $orders = $response['response']['order_list'] ?? [];
                        $allOrders = array_merge($allOrders, $orders);
                        
                        $more = $response['response']['more'] ?? false;
                        $cursor = $response['response']['next_cursor'] ?? "";
                        
                        if ($more) {
                             $this->info("Fetching next page...");
                        }

                    } while($more && !empty($cursor));
                }

                $count = count($allOrders);
                $this->info("Found total {$count} orders for store {$store->store_name}.");
                
                if ($count > 0) {
                    // Extract all Order SNs
                    $orderSns = array_column($allOrders, 'order_sn');
                    
                    // Unique Order SNs just in case
                    $orderSns = array_unique($orderSns);
                    
                    // Chunk them if necessary (Shopee might have a limit per request, e.g. 50)
                    $snChunks = array_chunk($orderSns, 50);

                    foreach ($snChunks as $chunk) {
                        $this->info("Fetching details for " . count($chunk) . " orders...");
                        
                        // 2. Get Details for these orders
                        $detailResponse = $shopeeService->getOrderDetail($chunk);

                        // Log response to file
                        $this->saveApiResponse('order_detail', $store->store_name, $detailResponse);

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
                            
                            // Save to Database
                            $this->saveOrder($detail, $store);
                        }
                    }
                }

            } catch (\Exception $e) {
                $this->error('Exception for store ' . $store->store_name . ': ' . $e->getMessage());
                Log::error('FetchShopeeOrders command failed for store ' . $store->store_name, ['error' => $e->getMessage()]);
            }
        }

        $this->info('All stores processed.');
        return 0;
    }

    private function saveApiResponse($type, $storeName, $data)
    {
        try {
            $path = storage_path("logs/shopee/orders/" . date('Y-m-d'));
            if (!file_exists($path)) {
                mkdir($path, 0777, true);
            }

            $filename = sprintf(
                "%s_%s_%s_%s.json",
                date('H-i-s'),
                str_replace([' ', '/', '\\'], '_', $storeName),
                $type,
                uniqid()
            );

            file_put_contents($path . '/' . $filename, json_encode($data, JSON_PRETTY_PRINT));
            $this->info("   Saved JSON response to: " . $path . '/' . $filename);
        } catch (\Exception $e) {
            $this->warn("   Failed to save JSON response: " . $e->getMessage());
        }
    }

    private function saveOrder($detail, $store)
    {
        try {
            DB::beginTransaction();

            // Map Status
            $statusMap = [
                'UNPAID' => null, // Skip per user request
                'READY_TO_SHIP' => OrderStatus::READY_TO_SHIP,
                'RETRY_SHIP' => OrderStatus::RETRY_SHIP,
                'PROCESSED' => OrderStatus::READY_TO_PICKUP,
                'SHIPPED' => OrderStatus::SHIPPED,
                'TO_CONFIRM_RECEIVE' => OrderStatus::SHIPPED,
                'COMPLETED' => OrderStatus::SHIPPED,
                'CANCELLED' => OrderStatus::CANCELLED,
                'TO_RETURN' => OrderStatus::CANCELLED,
            ];

            $status = $statusMap[$detail['order_status']] ?? null;

            if ($status === null) {
                $this->info("   Skipping order " . $detail['order_sn'] . " with status: " . $detail['order_status']);
                DB::rollBack();
                return;
            }

            $recipient = $detail['recipient_address'] ?? [];

            $this->info("   Saving Order: " . $detail['order_sn']);

            $orderData = [
                'online_store_id' => $store->id,
                'status' => $status,
                'awb_code' => null, // Empty for now as requested
                'total_price' => $detail['goods_to_declare'] ?? 0,
                'total_shipping' => $detail['estimated_shipping_fee'] ?? 0,
                'total_amount' => $detail['total_amount'] ?? 0,
                'customer_name' => $recipient['name'] ?? $detail['buyer_username'],
                'customer_phone' => $recipient['phone'] ?? null,
                'customer_address' => $this->formatAddress($recipient),
                'item_count' => count($detail['item_list'] ?? []),
                'unique_item_count' => count($detail['item_list'] ?? []), 
                'read_at' => now(), 
            ];

            $order = Order::updateOrCreate(
                ['order_sn' => $detail['order_sn']],
                $orderData
            );

            if (isset($detail['item_list'])) {
                foreach ($detail['item_list'] as $itemData) {
                    
                    // Logic to extract color and size from model_name
                    // Assumed format "Color,Size" or similar. Shopee usually sends "VariationName, VariationName2"
                    // If model_name is "Merah,L" -> color=Merah, size=L
                    // We will split by comma.
                    $color = null;
                    $size = null;
                    if (!empty($itemData['model_name'])) {
                        $parts = explode(',', $itemData['model_name']);
                        $color = trim($parts[0] ?? '');
                        $size = trim($parts[1] ?? '');
                    }

                    OrderItem::updateOrCreate(
                        [
                            'order_id' => $order->id,
                            'order_item_id' => (string)$itemData['order_item_id'],
                        ],
                        [
                            'item_name' => $itemData['item_name'],
                            'sku' => $itemData['item_sku'] ?? null,
                            'color' => $color,
                            'size' => $size,
                            'quantity_purchased' => $itemData['model_quantity_purchased'],
                            'price' => $itemData['model_original_price'],
                            'discounted_price' => $itemData['model_discounted_price'],
                        ]
                    );
                }
            }

            DB::commit();
            $this->info("   ✅ Order saved successfully.");

        } catch (\Exception $e) {
            DB::rollBack();
            $this->error("   ❌ Failed to save order " . $detail['order_sn'] . ": " . $e->getMessage());
            Log::error("Failed to save order " . $detail['order_sn'], [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'data' => $detail
            ]);
        }
    }

    private function formatAddress($recipient)
    {
        if (empty($recipient)) return null;

        $parts = [
            $recipient['full_address'] ?? '',
            $recipient['district'] ?? '',
            $recipient['city'] ?? '',
            $recipient['state'] ?? '',
            $recipient['zipcode'] ?? '',
            $recipient['region'] ?? ''
        ];

        return implode(', ', array_filter($parts));
    }
}
