<?php

namespace Tests\Feature;

use App\Enums\OrderStatus;
use App\Jobs\FetchShopeeOrdersJob;
use App\Models\MasterData\OnlineStore;
use App\Models\Transactions\Order;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopeeOrderSyncTest extends TestCase
{
    private OnlineStore $store;

    private string $storagePath;

    protected function setUp(): void
    {
        parent::setUp();

        // The command dumps every API response under storage/logs; keep test dumps out of the project
        $this->storagePath = sys_get_temp_dir() . '/shopee-sync-' . Str::random(8);
        $this->app->useStoragePath($this->storagePath);

        // Only the tables this feature touches; the full migration set is MySQL-specific.
        Schema::create('mdx_marketplaces', function ($t) {
            $t->uuid('id')->primary();
            $t->string('code');
            $t->string('name');
            $t->string('alias')->nullable();
            $t->string('base_api_url')->nullable();
            $t->softDeletes();
        });
        Schema::create('mdx_online_stores', function ($t) {
            $t->uuid('id')->primary();
            $t->uuid('marketplace_id');
            foreach (['store_code', 'store_name', 'shop_id', 'client_id', 'client_secret', 'access_token', 'refresh_token'] as $column) {
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
            $t->string('status');
            foreach (['online_store_id', 'marketplace_id', 'total_price', 'total_shipping', 'total_amount', 'customer_name', 'customer_phone', 'customer_address', 'awb_code'] as $column) {
                $t->string($column)->nullable();
            }
            $t->integer('item_count')->nullable();
            $t->integer('unique_item_count')->nullable();
            $t->boolean('is_outbounded')->default(false);
            foreach (['service_type', 'shipping_courier', 'delivery_option'] as $column) {
                $t->string($column)->nullable();
            }
            $t->timestamp('ship_by_at')->nullable();
            $t->timestamp('ordered_at')->nullable();
            $t->timestamp('read_at')->nullable();
            $t->timestamps();
            $t->softDeletes();
        });

        $marketplaceId = (string) Str::uuid();
        \DB::table('mdx_marketplaces')->insert([
            'id' => $marketplaceId,
            'code' => 'shopee',
            'name' => 'Shopee',
            'alias' => 'shopee',
            'base_api_url' => 'https://shopee.example.test',
        ]);

        $this->store = OnlineStore::create([
            'marketplace_id' => $marketplaceId,
            'store_code' => 'sp',
            'store_name' => 'Shopee A',
            'shop_id' => '1',
            'client_id' => '2',
            'client_secret' => 'secret',
            'access_token' => 'access',
            'refresh_token' => 'refresh',
            'access_token_expires_at' => now()->addDay(),
        ]);

        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->storagePath);

        parent::tearDown();
    }

    private function existing(string $sn, OrderStatus $status, bool $outbounded = false): void
    {
        Order::create([
            'order_sn' => $sn,
            'online_store_id' => $this->store->id,
            'status' => $status,
            'is_outbounded' => $outbounded,
            'customer_name' => 'x',
        ]);
    }

    /** @param array<string, string> $statuses order_sn => Shopee order_status */
    private function sync(array $statuses): void
    {
        $list = [];
        $details = [];
        foreach ($statuses as $sn => $status) {
            $list[] = ['order_sn' => $sn];
            $details[] = ['order_sn' => $sn, 'order_status' => $status, 'buyer_username' => 'buyer'];
        }

        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response(['error' => '', 'response' => ['order_list' => $list]]),
            '*/api/v2/order/get_order_detail*' => Http::response(['error' => '', 'response' => ['order_list' => $details]]),
        ]);

        $this->artisan('shopee:fetch-orders')->assertExitCode(0);
    }

    private function assertStatus(OrderStatus $expected, string $sn): void
    {
        $this->assertSame($expected, Order::where('order_sn', $sn)->first()?->status, $sn);
    }

    public function test_processed_order_stays_scannable_until_it_is_outbounded(): void
    {
        $this->existing('ARRANGED', OrderStatus::READY_TO_PICKUP);
        // Shipment arranged outside this app, e.g. in Seller Center
        $this->existing('ELSEWHERE', OrderStatus::READY_TO_SHIP);
        // Already overwritten by an earlier sync
        $this->existing('STUCK', OrderStatus::WAIT_COURIER);

        $this->sync(['ARRANGED' => 'PROCESSED', 'ELSEWHERE' => 'PROCESSED', 'STUCK' => 'PROCESSED', 'NEW' => 'PROCESSED']);

        foreach (['ARRANGED', 'ELSEWHERE', 'STUCK', 'NEW'] as $sn) {
            $this->assertStatus(OrderStatus::READY_TO_PICKUP, $sn);
        }
    }

    public function test_processed_order_waits_for_courier_after_outbound(): void
    {
        $this->existing('SCANNED', OrderStatus::READY_TO_PICKUP, outbounded: true);

        $this->sync(['SCANNED' => 'PROCESSED']);

        $this->assertStatus(OrderStatus::WAIT_COURIER, 'SCANNED');
    }

    public function test_later_shopee_statuses_still_apply_to_unscanned_orders(): void
    {
        $this->existing('SENT', OrderStatus::READY_TO_PICKUP);
        $this->existing('VOID', OrderStatus::READY_TO_PICKUP);

        $this->sync(['SENT' => 'SHIPPED', 'VOID' => 'CANCELLED']);

        $this->assertStatus(OrderStatus::SHIPPED, 'SENT');
        $this->assertStatus(OrderStatus::CANCELLED, 'VOID');
    }

    /** One reply of get_order_list. */
    private function listPage(array $sns, string $nextCursor = ''): array
    {
        return ['error' => '', 'response' => [
            'order_list' => array_map(fn (string $sn) => ['order_sn' => $sn], $sns),
            'more' => $nextCursor !== '',
            'next_cursor' => $nextCursor,
        ]];
    }

    /** Answers get_order_list with the given replies in turn, and details for whatever is asked. */
    private function fakeShopee(array ...$listReplies): void
    {
        $sequence = Http::sequence();
        foreach ($listReplies as $reply) {
            $sequence->push($reply);
        }

        Http::fake([
            '*/api/v2/order/get_order_list*' => $sequence,
            '*/api/v2/order/get_order_detail*' => fn (Request $r) => Http::response(['error' => '', 'response' => [
                'order_list' => array_map(
                    fn (string $sn) => ['order_sn' => $sn, 'order_status' => 'SHIPPED', 'buyer_username' => 'buyer'],
                    explode(',', $this->query($r)['order_sn_list']),
                ),
            ]]),
        ]);
    }

    private function query(Request $request): array
    {
        parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

        return $query;
    }

    /** @return list<array> query strings of the get_order_list calls, in order */
    private function listQueries(): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), 'get_order_list'))
            ->map(fn (array $pair) => $this->query($pair[0]))
            ->values()
            ->all();
    }

    public function test_courier_assigned_later_is_added_to_an_existing_order(): void
    {
        $this->existing('K1', OrderStatus::READY_TO_SHIP);
        Http::fake([
            '*/api/v2/order/get_order_list*' => Http::response($this->listPage(['K1'])),
            '*/api/v2/order/get_order_detail*' => Http::response(['error' => '', 'response' => ['order_list' => [[
                'order_sn' => 'K1', 'order_status' => 'READY_TO_SHIP', 'buyer_username' => 'buyer',
                'create_time' => 1_795_000_000, 'pay_time' => 1_795_000_100, 'ship_by_date' => 1_795_090_000,
                'checkout_shipping_carrier' => 'Instant', 'shipping_carrier' => 'SPX Instant',
            ]]]]),
        ]);

        $this->artisan('shopee:fetch-orders')->assertSuccessful();

        $order = Order::where('order_sn', 'K1')->first();
        $this->assertSame('instant', $order->service_type);
        $this->assertSame('Shopee Express (SPX)', $order->shipping_courier);
        $this->assertSame(1_795_003_700, $order->ship_by_at->timestamp);
        $this->assertSame(1_795_000_000, $order->ordered_at->timestamp);
    }

    public function test_fetch_follows_every_page(): void
    {
        $first = array_map(fn (int $i) => "P1-$i", range(1, 50));
        $this->fakeShopee($this->listPage($first, '50'), $this->listPage(['P2-1', 'P2-2']));

        $this->artisan('shopee:fetch-orders')->assertExitCode(0);

        $this->assertSame(52, Order::count());
        $this->assertSame('50', $this->listQueries()[1]['cursor']);
    }

    public function test_fetch_asks_for_orders_updated_in_the_period(): void
    {
        $this->existing('OLD', OrderStatus::WAIT_COURIER, outbounded: true);
        $this->fakeShopee($this->listPage(['OLD']));

        $this->artisan('shopee:fetch-orders')->assertExitCode(0);

        $query = $this->listQueries()[0];
        $this->assertSame('update_time', $query['time_range_field']);
        $this->assertSame(86400, $query['time_to'] - $query['time_from']);
        $this->assertStatus(OrderStatus::SHIPPED, 'OLD');
    }

    public function test_long_periods_are_split_into_windows_shopee_accepts(): void
    {
        // The same order on a window boundary must only be saved once
        $this->fakeShopee($this->listPage(['W1']), $this->listPage(['W1', 'W2']), $this->listPage(['W3']));

        $this->artisan('shopee:fetch-orders', ['--days' => 35])->assertExitCode(0);

        $queries = $this->listQueries();
        $this->assertCount(3, $queries);
        $this->assertSame([15 * 86400, 15 * 86400, 5 * 86400], array_map(fn (array $q) => $q['time_to'] - $q['time_from'], $queries));
        $this->assertSame($queries[0]['time_to'], $queries[1]['time_from']);
        $this->assertSame($queries[1]['time_to'], $queries[2]['time_from']);
        $this->assertSame(['W1', 'W2', 'W3'], Order::orderBy('order_sn')->pluck('order_sn')->all());
    }

    public function test_orders_from_earlier_pages_are_kept_when_a_later_page_fails(): void
    {
        $this->fakeShopee($this->listPage(['KEPT'], '50'), ['error' => 'error_server', 'message' => 'busy']);

        $this->artisan('shopee:fetch-orders')->assertExitCode(0);

        $this->assertStatus(OrderStatus::SHIPPED, 'KEPT');
        $this->assertCount(2, $this->listQueries());
    }

    public function test_manual_fetch_only_queues_a_job(): void
    {
        Queue::fake();

        $this->withoutMiddleware()
            ->getJson('/api/shopee/fetch-orders')
            ->assertStatus(202)
            ->assertJsonPath('success', true);

        Queue::assertPushed(FetchShopeeOrdersJob::class, fn ($job) => $job->days === 1);
        Http::assertNothingSent();
    }
}
