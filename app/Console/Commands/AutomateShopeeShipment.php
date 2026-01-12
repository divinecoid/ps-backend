<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Transactions\Order;
use App\Models\MasterData\OnlineStore;
use App\Enums\OrderStatus;
use App\Services\ShopeeService;
use Illuminate\Support\Facades\Log;

class AutomateShopeeShipment extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shopee:automate-shipment {order_sn?} {--force}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automate shipment process: Get Params -> Ship -> Update DB -> Download Label';

    protected $shopeeService;

    public function __construct(ShopeeService $shopeeService)
    {
        parent::__construct();
        $this->shopeeService = $shopeeService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $orderSn = $this->argument('order_sn');
        $force = $this->option('force');

        if ($orderSn) {
            $orders = Order::where('order_sn', $orderSn)->get();
        } else {
            $orders = Order::whereIn('status', [OrderStatus::READY_TO_SHIP, OrderStatus::READY_TO_PICKUP])
                           ->whereHas('online_store.marketplace', function($q) {
                               $q->where('name', 'Shopee')->orWhere('alias', 'shopee');
                           })
                           ->get();
        }

        if ($orders->isEmpty()) {
            $this->warn("No eligible orders found.");
            return;
        }

        $this->info("Found " . $orders->count() . " orders to process.");

        foreach ($orders as $order) {
            $this->processOrder($order);
        }
    }

    private function processOrder(Order $order)
    {
        $this->line("------------------------------------------------");
        $this->info("Processing Order: {$order->order_sn} (Status: " . ($order->status->value ?? (string)$order->status) . ")");

        try {
            // Set Store Context
            $this->shopeeService->setStore($order->online_store);

            if ($order->status === OrderStatus::READY_TO_SHIP || $this->option('force')) {
                $this->info("   Step 1: Fetching Shipping Parameters...");
                $paramResponse = $this->shopeeService->getShippingParameter($order->order_sn);
                
                if (isset($paramResponse['error']) && !empty($paramResponse['error'])) {
                    $this->error("   Error fetching params: " . json_encode($paramResponse));
                    return;
                }

                // Determine Pickup or Dropoff
                // Logic: Prefer Pickup if available
                $pickupData = $paramResponse['response']['pickup']['address_list'][0] ?? null;
                $dropoffData = $paramResponse['response']['dropoff']['branch_list'][0] ?? null;

                if ($pickupData) {
                    $addressId = $pickupData['address_id'];
                    $pickupTimeId =
                        $pickupData['pickup_time_list'][0]['pickup_time_id']
                        ?? $pickupData['time_slot_list'][0]['pickup_time_id']
                        ?? null;
                    
                    if (!$pickupTimeId) {
                        $this->warn("   No pickup time slots available.");
                        return;
                    }

                    $this->info("   Mode: Pickup (Address: $addressId, Time: $pickupTimeId)");
                    
                    // 2. Ship Order
                    $this->info("   Step 2: Shipping Order...");
                    $shipResponse = $this->shopeeService->shipOrder($order->order_sn, [
                        'address_id' => $addressId,
                        'pickup_time_id' => $pickupTimeId
                    ]);

                    if (isset($shipResponse['error']) && !empty($shipResponse['error'])) {
                        // Check if already shipped
                        if (strpos($shipResponse['message'] ?? '', 'Order has been shipped') !== false) {
                            $this->info("   Order already shipped at Shopee.");
                        } else {
                            $this->error("   Shipment Failed: " . json_encode($shipResponse));
                            return;
                        }
                    } else {
                        $this->info("   Shipment Success!");
                    }

                } elseif ($dropoffData) {
                    $this->warn("   Mode: Dropoff (Not fully implemented in automation yet, skipping to avoid errors)");
                    // TODO: Implement dropoff logic
                    return;
                } else {
                    $this->error("   No valid pickup/dropoff options found.");
                    return;
                }

                // 3. Update DB
                // Fetch latest detail to get AWB
                $this->info("   Step 3: Updating Local Status...");
                $detailResponse = $this->shopeeService->getOrderDetail([$order->order_sn]);
                $detail = $detailResponse['response']['order_list'][0] ?? null;
                
                $awb = $detail['tracking_no'] ?? $detail['shipping_carrier'] ?? null;
                $shopeeStatus = $detail['order_status'] ?? null;

                $order->update([
                    'status' => OrderStatus::READY_TO_PICKUP,
                    'awb_code' => $awb ?? $order->awb_code,
                    'readytoship_at' => now(),
                    'readytoship_marketplace' => $shopeeStatus
                ]);
                $this->info("   Updated DB: Status=ready_to_pickup, AWB=" . ($awb ?? 'Pending'));
            }

            // 4. Download Label
            $this->info("   Step 4: Downloading Label...");
            try {
                // Ensure document is created (idempotent)
                $this->shopeeService->createShippingDocument($order->order_sn);
                
                $pdfContent = $this->shopeeService->downloadShippingDocument($order->order_sn);
                
                // Save to storage
                $filename = "shopee_label_{$order->order_sn}.pdf";
                $path = public_path("labels/{$filename}");
                
                if (!file_exists(dirname($path))) {
                    mkdir(dirname($path), 0755, true);
                }
                
                file_put_contents($path, $pdfContent);
                $this->info("   Label saved to: {$path}");

            } catch (\Exception $e) {
                $this->error("   Failed to download label: " . $e->getMessage());
            }

        } catch (\Exception $e) {
            $this->error("   Exception: " . $e->getMessage());
        }
    }
}
