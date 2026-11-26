<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Http\Controllers\Api\TiktokAuthController;
use App\Jobs\FetchTiktokOrdersJob;
use App\Models\MasterData\OnlineStore;
use App\Models\Transactions\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class TiktokOrderSyncTest extends TestCase
{
    private string $marketplaceId;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the tables this feature touches; the full migration set is MySQL-specific.
        Schema::create('mdx_marketplaces', function ($t) {
            $t->uuid('id')->primary();
            $t->string('code');
            $t->string('alias')->nullable();
            $t->softDeletes();
        });
        Schema::create('mdx_online_stores', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('marketplace_id');
            foreach (['store_code', 'store_name', 'shop_id', 'shop_cipher', 'api_key', 'client_id', 'client_secret', 'store_url', 'redirect_uri', 'auth_code', 'access_token', 'refresh_token'] as $column) {
                $t->string($column)->nullable();
            }
            $t->boolean('is_active')->default(true);
            $t->timestamp('access_token_expires_at')->nullable();
            $t->timestamp('refresh_token_expires_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('trx_orders', function ($t) {
            $t->uuid('id')->primary();
            $t->string('order_sn');
            foreach (['online_store_id', 'marketplace_id', 'status', 'total_price', 'total_shipping', 'total_amount', 'customer_name', 'customer_phone', 'customer_address', 'awb_code', 'readytoship_marketplace'] as $column) {
                $t->string($column)->nullable();
            }
            $t->integer('item_count')->nullable();
            $t->integer('unique_item_count')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });
        Schema::create('trx_order_items', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('order_id');
            foreach (['order_item_id', 'sku', 'item_name', 'color', 'size', 'price', 'discounted_price'] as $column) {
                $t->string($column)->nullable();
            }
            $t->integer('quantity_purchased')->nullable();
            $t->boolean('is_checked')->default(false);
            $t->timestamps();
            $t->softDeletes();
        });

        $this->marketplaceId = (string) Str::uuid();
        \DB::table('mdx_marketplaces')->insert(['id' => $this->marketplaceId, 'code' => 'tiktok_shop', 'alias' => 'tiktok-shop']);

        Http::preventStrayRequests();
    }

    private function store(array $overrides = []): OnlineStore
    {
        return OnlineStore::create($overrides + [
            'marketplace_id' => $this->marketplaceId,
            'store_code' => 'tt',
            'store_name' => 'TikTok A',
            'shop_cipher' => 'OLD-CIPHER',
            'api_key' => 'key-a',
            'client_id' => 'service-1',
            'client_secret' => 'secret-a',
            // A wrong shop page URL must not affect where API calls go
            'store_url' => 'https://dead.example.test',
            'is_active' => true,
            'access_token' => 'access-old',
            'refresh_token' => 'refresh-old',
            'access_token_expires_at' => now()->addDay(),
        ]);
    }

    private function order(string $id, string $status): array
    {
        return [
            'id' => $id,
            'status' => $status,
            'line_items' => [['id' => 'line-' . $id, 'product_name' => 'Polo', 'sku_name' => 'Merah, L', 'seller_sku' => 'P1']],
            'payment' => ['total_amount' => '100000'],
            'recipient_address' => ['name' => 'Buyer'],
        ];
    }

    private function fakeShopAndOrders(array ...$pages): void
    {
        $sequence = Http::sequence();
        foreach ($pages as $index => $orders) {
            $sequence->push(['code' => 0, 'data' => [
                'orders' => $orders,
                'next_page_token' => $index < count($pages) - 1 ? 'page-' . ($index + 2) : '',
            ]]);
        }

        Http::fake([
            '*/authorization/202309/shops*' => Http::response(['code' => 0, 'data' => ['shops' => [['id' => '999', 'cipher' => 'NEW-CIPHER']]]]),
            '*/order/202309/orders/search*' => $sequence,
        ]);
    }

    public function test_fetch_saves_every_page_and_refreshes_shop_identity(): void
    {
        $store = $this->store();
        $this->fakeShopAndOrders(
            [$this->order('A1', 'AWAITING_SHIPMENT')],
            [$this->order('A2', 'IN_TRANSIT'), $this->order('A3', 'UNPAID')],
        );

        $this->artisan('tiktok:fetch-orders', ['--days' => 2])->assertExitCode(0);

        $this->assertSame(['A1', 'A2'], Order::orderBy('order_sn')->pluck('order_sn')->all());
        $this->assertSame(2, \DB::table('trx_order_items')->count());
        $this->assertSame('NEW-CIPHER', $store->fresh()->shop_cipher);
        $this->assertSame('999', $store->fresh()->shop_id);

        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/orders/search')
            && str_contains($r->url(), 'page_token=page-2')
            && str_contains($r->url(), 'shop_cipher=NEW-CIPHER')
            && !str_contains($r->url(), 'dead.example.test')
            && !str_contains($r->url(), 'access-old')
            && $r['update_time_lt'] - $r['update_time_ge'] === 2 * 86400);
    }

    public function test_sync_does_not_undo_warehouse_progress(): void
    {
        $store = $this->store();
        foreach (['B1' => OrderStatus::READ, 'B2' => OrderStatus::RETURNED, 'B3' => OrderStatus::READ] as $sn => $status) {
            Order::create(['order_sn' => $sn, 'online_store_id' => $store->id, 'status' => $status, 'customer_name' => 'x']);
        }
        $this->fakeShopAndOrders([
            $this->order('B1', 'AWAITING_SHIPMENT'),
            $this->order('B2', 'DELIVERED'),
            $this->order('B3', 'CANCELLED'),
        ]);

        $this->artisan('tiktok:fetch-orders')->assertExitCode(0);

        $status = fn (string $sn) => Order::where('order_sn', $sn)->first()->status;
        $this->assertSame(OrderStatus::READ, $status('B1'));
        $this->assertSame(OrderStatus::RETURNED, $status('B2'));
        $this->assertSame(OrderStatus::CANCELLED, $status('B3'));
    }

    public function test_api_error_fails_the_command(): void
    {
        $this->store();
        Http::fake([
            '*/authorization/202309/shops*' => Http::response(['code' => 0, 'data' => ['shops' => [['id' => '999', 'cipher' => 'C']]]]),
            '*/order/202309/orders/search*' => Http::response(['code' => 105001, 'message' => 'access denied']),
        ]);

        $this->artisan('tiktok:fetch-orders')->assertExitCode(1);
    }

    public function test_inactive_store_is_ignored(): void
    {
        $this->store(['is_active' => false]);

        $this->artisan('tiktok:fetch-orders')->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_refresh_command_updates_only_active_stores_with_their_own_credentials(): void
    {
        $old = $this->store(['store_code' => 'old', 'is_active' => false]);
        $active = $this->store(['store_code' => 'new', 'api_key' => 'key-b', 'client_secret' => 'secret-b']);
        Http::fake(['auth.tiktok-shops.com/*' => Http::response(['code' => 0, 'data' => [
            'access_token' => 'access-new',
            'refresh_token' => 'refresh-new',
            'access_token_expire_in' => now()->addDays(7)->timestamp,
            'refresh_token_expire_in' => now()->addYear()->timestamp,
        ]])]);

        $this->artisan('tiktok:refresh-token')->assertExitCode(0);

        $this->assertSame('access-new', $active->fresh()->access_token);
        $this->assertSame('access-old', $old->fresh()->access_token);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r) => str_contains($r->url(), 'app_key=key-b'));
    }

    public function test_manual_fetch_only_queues_a_job(): void
    {
        Queue::fake();

        $this->withoutMiddleware()
            ->getJson('/api/tiktok-shop/get-order-list?page_size=100')
            ->assertStatus(202)
            ->assertJsonPath('success', true);

        Queue::assertPushed(FetchTiktokOrdersJob::class, fn ($job) => $job->days === 7);
        Http::assertNothingSent();
    }

    public function test_connect_uses_service_id_and_clears_the_cached_cipher(): void
    {
        $store = $this->store();
        Http::fake(['auth.tiktok-shops.com/*' => Http::response(['code' => 0, 'data' => [
            'access_token' => 'a', 'refresh_token' => 'r',
            'access_token_expire_in' => now()->addDays(7)->timestamp,
            'refresh_token_expire_in' => now()->addYear()->timestamp,
        ]])]);

        $location = app(TiktokAuthController::class)->redirectToTiktok($store->id)->getTargetUrl();
        $this->assertStringContainsString('service_id=service-1', $location);

        $this->get('/tiktok-shop/callback?code=abc&state=' . $store->id)->assertOk();
        $this->assertNull($store->fresh()->shop_cipher);
    }
}
