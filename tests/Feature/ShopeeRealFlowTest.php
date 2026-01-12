<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\MasterData\OnlineStore;
use App\Models\Transactions\Order;
use App\Models\MasterData\User;
use App\Enums\OrderStatus;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Config;
use Tymon\JWTAuth\Facades\JWTAuth;

class ShopeeRealFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Force use of real MySQL database from .env
        Config::set('database.default', 'mysql_real');
        Config::set('database.connections.mysql_real', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3320',
            'database' => 'ps-db',
            'username' => 'dbadmin',
            'password' => 'dbadmin',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
    }
    
    public function test_real_shopee_shipment_flow()
    {
        echo "\nStarting Real Shopee Flow Test (MySQL)...\n";

        // 1. Find a valid Shopee Store (regardless of local orders)
        $store = OnlineStore::whereHas('marketplace', function($q) {
            $q->where('name', 'Shopee')->orWhere('alias', 'shopee')->orWhere('alias', 'shopee_sandbox');
        })->whereNotNull('access_token')
          ->first();

        if (!$store) {
            $this->markTestSkipped('No Shopee Store with access_token found in MySQL DB.');
        }
        echo "Using Store: " . $store->store_name . " (ID: " . $store->id . ")\n";

        if (!$store->is_active) {
            $store->is_active = true;
            $store->save();
        }

        // 2. FETCH ORDERS (via command, same logic as scheduler)
        echo "\n[STEP 1] Fetching Orders from Shopee API...\n";
        Artisan::call('shopee:fetch-orders', ['--days' => 15]);
        echo Artisan::output();

        // 3. Find orders to process (READY_TO_SHIP, RETRY_SHIP, READY_TO_PICKUP, SHIPPED)
        $orders = Order::query()
            ->whereIn('status', [OrderStatus::READY_TO_SHIP, OrderStatus::RETRY_SHIP, OrderStatus::READY_TO_PICKUP, OrderStatus::SHIPPED])
            ->orderBy('created_at', 'desc')
            ->get();
        
        if ($orders->isEmpty()) {
             $this->markTestSkipped('No suitable Order (ready_to_ship/retry_ship/pickup/shipped) found in MySQL DB after fetch.');
        }

        echo "Found " . $orders->count() . " orders to process.\n";

        // 4. Authenticate as Admin
        $admin = User::whereHas('roles', function($q) {
            $q->where('name', 'admin');
        })->first();
        
        if (!$admin) {
            echo "No Admin found. Creating temporary admin user...\n";
            $role = \App\Models\MasterData\Role::firstOrCreate(['name' => 'admin'], ['description' => 'Admin']);
            
            // Check if user exists by email to avoid dupes if role check failed
            $admin = User::where('email', 'testadmin@example.com')->first();
            if (!$admin) {
                $admin = User::create([
                    'name' => 'Test Admin',
                    'email' => 'testadmin@example.com',
                    'password' => bcrypt('password'),
                    'username' => 'testadmin'
                ]);
            }
            // Attach role if not attached
            if (!$admin->roles()->where('name', 'admin')->exists()) {
                $admin->roles()->attach($role->id);
            }
        }
        $token = JWTAuth::fromUser($admin);

        foreach ($orders as $order) {
            echo "\n------------------------------------------------\n";
            echo "Processing Order: " . $order->order_sn . " (Status: " . ($order->status->value ?? 'null') . ")\n";

            try {
                $orderStore = $order->online_store ?? null;
                if ($orderStore) {
                    $shopeeService = app(\App\Services\ShopeeService::class);
                    $shopeeService->setStore($orderStore);
                    $this->app->instance(\App\Services\ShopeeService::class, $shopeeService);
                }

                // 4. If status is READY_TO_SHIP or RETRY_SHIP, do Shipment Flow
                echo "Current Status: " . ($order->status->value ?? $order->status) . "\n";

                if ($order->status === OrderStatus::READY_TO_SHIP || $order->status === OrderStatus::RETRY_SHIP) {
                    echo "Trying to Download Shipping Document (pre-ship)...\n";
                    $preDownloadResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
                        ->post('/api/shopee/download-shipping-document', [
                            'order_sn' => $order->order_sn,
                            'shipping_document_type' => 'NORMAL_AIR_WAYBILL',
                        ]);

                    if ($preDownloadResponse->status() === 200) {
                        $contentType = (string) $preDownloadResponse->headers->get('Content-Type');
                        echo "Pre-Download Status: 200, Content-Type: {$contentType}, Bytes: " . strlen($preDownloadResponse->getContent()) . "\n";
                    } else {
                        echo "Pre-Download Status: " . $preDownloadResponse->status() . "\n";
                        echo "Pre-Download Response: " . $preDownloadResponse->getContent() . "\n";
                    }

                    echo "Fetching Shipping Parameters...\n";
                    
                    // A. Get Params
                    $response = $this->withHeaders(['Authorization' => "Bearer $token"])
                                     ->getJson('/api/shopee/shipping-parameter?order_sn=' . $order->order_sn);
                    
                    if ($response->status() !== 200) {
                         echo "Error getting params: " . $response->getContent() . "\n";
                         continue; // Skip to next order
                    }

                    $pickupData = $response->json('data.response.pickup.address_list.0');
                    
                    if (!$pickupData) {
                         // Try Dropoff
                         $dropoff = $response->json('data.response.dropoff');
                         if ($dropoff) {
                             echo "Pickup not available, but Dropoff is. Skipping Pickup test.\n";
                             continue;
                         }
                         echo "No pickup/dropoff options found. Response: " . json_encode($response->json()) . "\n";
                         continue;
                    }

                    $addressId = $pickupData['address_id'];
                    echo "Pickup Address ID: " . ($addressId ?? 'N/A') . "\n";
                    echo "Pickup Time List: " . json_encode($pickupData['pickup_time_list'] ?? []) . "\n";
                    echo "Time Slot List: " . json_encode($pickupData['time_slot_list'] ?? []) . "\n";

                    $pickupTimeId =
                        $pickupData['pickup_time_list'][0]['pickup_time_id']
                        ?? $pickupData['time_slot_list'][0]['pickup_time_id']
                        ?? null;

                    if (!$pickupTimeId) {
                        echo "No pickup time found.\n";
                        continue;
                    }

                    echo "Shipping Order (Address ID: $addressId, Pickup Time ID: $pickupTimeId)...\n";

                    // B. Ship Order
                    $payload = [
                        'order_sn' => $order->order_sn,
                        'address_id' => $addressId,
                        'pickup_time_id' => $pickupTimeId
                    ];

                    $shipResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
                                         ->postJson('/api/shopee/ship-order', $payload);
                    
                    if ($shipResponse->status() !== 200) {
                         echo "Ship Error: " . $shipResponse->getContent() . "\n";
                         continue;
                    }
                    
                    echo "Order Shipped! Message: " . $shipResponse->json('message') . "\n";

                    // Refresh and check status
                    $order->refresh();
                    echo "New Status after Ship: " . ($order->status->value ?? $order->status) . "\n";

                    echo "Downloading Shipping Document (post-ship)...\n";
                    $downloadResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
                        ->post('/api/shopee/download-shipping-document', [
                            'order_sn' => $order->order_sn,
                            'shipping_document_type' => 'NORMAL_AIR_WAYBILL',
                        ]);

                    if ($downloadResponse->status() !== 200) {
                        echo "Download Error: " . $downloadResponse->getContent() . "\n";
                    } else {
                        $contentType = (string) $downloadResponse->headers->get('Content-Type');
                        $contentDisposition = (string) $downloadResponse->headers->get('Content-Disposition');
                        echo "Download OK. Content-Type: {$contentType}\n";
                        echo "Content-Disposition: {$contentDisposition}\n";
                        echo "Bytes: " . strlen($downloadResponse->getContent()) . "\n";
                    }

                    // Verify DB Update
                    $updatedOrder = Order::find($order->id); // Refresh from DB
                    echo "DB Status: " . ($updatedOrder->status->value ?? 'null') . "\n";
                    echo "DB AWB: " . $updatedOrder->awb_code . "\n";
                    
                    if ($updatedOrder->status !== OrderStatus::READY_TO_PICKUP) {
                        echo "WARNING: DB Status not updated to ready_to_pickup!\n";
                    }
                }
                
                // 5. If status is READY_TO_PICKUP (or just became it), Print Label
                // Refresh order object to get latest status if we just updated it
                $order->refresh();

                if ($order->status === OrderStatus::READY_TO_PICKUP || $order->status === OrderStatus::SHIPPED) {
                    echo "Downloading Shipping Document...\n";
                    $downloadResponse = $this->withHeaders(['Authorization' => "Bearer $token"])
                        ->post('/api/shopee/download-shipping-document', [
                            'order_sn' => $order->order_sn,
                            'shipping_document_type' => 'NORMAL_AIR_WAYBILL',
                        ]);

                    if ($downloadResponse->status() !== 200) {
                        echo "Download Error: " . $downloadResponse->getContent() . "\n";
                        continue;
                    }

                    $contentType = (string) $downloadResponse->headers->get('Content-Type');
                    $contentDisposition = (string) $downloadResponse->headers->get('Content-Disposition');
                    echo "Download OK. Content-Type: {$contentType}\n";
                    echo "Content-Disposition: {$contentDisposition}\n";
                    echo "Bytes: " . strlen($downloadResponse->getContent()) . "\n";
                    echo "SUCCESS: Full flow completed for " . $order->order_sn . "\n";
                }

            } catch (\Exception $e) {
                echo "Error processing " . $order->order_sn . ": " . $e->getMessage() . "\n";
            }
        }
    }
}
