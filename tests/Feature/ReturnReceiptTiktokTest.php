<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Transactions\Order;
use App\Models\MasterData\Marketplace;
use App\Models\MasterData\OnlineStore;
use App\Enums\OrderStatus;
use Illuminate\Foundation\Testing\DatabaseTransactions;

use Illuminate\Support\Facades\Config;

class ReturnReceiptTiktokTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'mysql_test');
        Config::set('database.connections.mysql_test', [
            'driver' => 'mysql',
            'host' => '127.0.0.1',
            'port' => '3306',
            'database' => 'ps-db',
            'username' => 'root',
            'password' => '',
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'strict' => true,
            'engine' => null,
        ]);
    }

    public function test_tiktok_order_transition_to_returned_on_store()
    {
        // 1. Create a TikTok Shop Marketplace and Store
        $marketplace = Marketplace::firstOrCreate(
            ['code' => 'tiktok_shop'],
            [
                'name' => 'TikTok Shop',
                'alias' => 'tiktok_shop',
            ]
        );

        $store = OnlineStore::firstOrCreate(
            ['store_code' => 'tiktok_test'],
            [
                'store_name' => 'TikTok Store Test',
                'marketplace_id' => $marketplace->id,
                'store_url' => 'https://test.com',
                'is_active' => true,
            ]
        );

        $orderSn = 'TT-' . uniqid();
        $awbCode = 'AWB-' . $orderSn;
        $order = null;

        try {
            // 2. Create a TikTok Order in 'shipped' status
            $order = Order::create([
                'online_store_id' => $store->id,
                'marketplace_id' => $marketplace->id,
                'order_sn' => $orderSn,
                'awb_code' => $awbCode,
                'status' => OrderStatus::SHIPPED,
                'customer_name' => 'John Doe',
                'item_count' => 1,
                'unique_item_count' => 1,
                'total_price' => '100000',
                'total_shipping' => '10000',
                'total_amount' => '110000',
            ]);

            // Create an order item for details creation
            $order->order_items()->create([
                'order_item_id' => 'ITEM-' . uniqid(),
                'item_name' => 'Test Product',
                'quantity_purchased' => 1,
                'price' => '100000',
                'discounted_price' => '100000',
            ]);

            // 3. Hit the return receipt store endpoint with order_sn (mimicking barcode scan)
            $response = $this->withoutMiddleware()->postJson('/api/return-receipt', [
                'awb_code' => $orderSn
            ]);

            $response->assertStatus(200);
            $response->assertJson([
                'success' => true,
            ]);

            // Verify order status is updated to returned
            $this->assertEquals(OrderStatus::RETURNED, $order->fresh()->status);
        } finally {
            if ($order) {
                // Delete return receipt detail and return receipt if created
                $receipt = \App\Models\Transactions\ReturnReceipt::where('order_id', $order->id)->first();
                if ($receipt) {
                    $receipt->details()->delete();
                    $receipt->delete();
                }
                $order->order_items()->delete();
                $order->delete();
            }
        }
    }
}
