<?php

namespace Tests\Feature;

use App\Models\MasterData\OnlineStore;
use App\Services\ShopeeService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;
use Tests\TestCase;

class ShopeeShippingLabelTest extends TestCase
{
    private ShopeeService $shopee;

    protected function setUp(): void
    {
        parent::setUp();

        // Only the tables this feature touches; the full migration set is MySQL-specific.
        Schema::create('mdx_marketplaces', function ($t) {
            $t->uuid('id')->primary();
            $t->string('code');
            $t->string('name');
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

        $marketplaceId = (string) Str::uuid();
        \DB::table('mdx_marketplaces')->insert([
            'id' => $marketplaceId,
            'code' => 'shopee',
            'name' => 'Shopee',
            'base_api_url' => 'https://shopee.example.test',
        ]);

        $this->shopee = new ShopeeService();
        $this->shopee->setStore(OnlineStore::create([
            'marketplace_id' => $marketplaceId,
            'store_code' => 'sp',
            'store_name' => 'Shopee A',
            'shop_id' => '1',
            'client_id' => '2',
            'client_secret' => 'secret',
            'access_token' => 'access',
        ]));

        Http::preventStrayRequests();
        Sleep::fake();
    }

    private function reply(array $entry): array
    {
        return ['error' => '', 'response' => ['result_list' => [['order_sn' => 'A1'] + $entry]]];
    }

    /** What Shopee answers when no label of the asked type exists. */
    private function notCreated(): array
    {
        return ['error' => 'common.batch_api_all_failed'] + $this->reply([
            'fail_error' => 'logistics.shipping_document_should_print_first',
        ]);
    }

    /** @param list<array> $results replies of get_shipping_document_result, in order */
    private function fakeShopee(array $results): void
    {
        $sequence = Http::sequence();
        foreach ($results as $reply) {
            $sequence->push($reply);
        }

        Http::fake([
            '*/get_shipping_document_parameter*' => Http::response($this->reply([
                'suggest_shipping_document_type' => 'THERMAL_AIR_WAYBILL',
            ])),
            '*/get_shipping_document_result*' => $sequence,
            '*/get_tracking_number*' => Http::response(['error' => '', 'response' => ['tracking_number' => 'RESI1']]),
            '*/create_shipping_document*' => Http::response($this->reply([])),
            '*/download_shipping_document*' => Http::response('%PDF-1.7 label', 200, ['Content-Type' => 'application/pdf']),
        ]);
    }

    /** @return list<array> bodies sent to the endpoint */
    private function sent(string $endpoint): array
    {
        return Http::recorded(fn (Request $r) => str_contains($r->url(), $endpoint))
            ->map(fn (array $pair) => $pair[0]->data())
            ->values()
            ->all();
    }

    public function test_ready_label_is_downloaded_in_the_format_shopee_recommends(): void
    {
        $this->fakeShopee([$this->reply(['status' => 'READY'])]);

        $pdf = $this->shopee->downloadShippingDocument('A1');

        $this->assertStringStartsWith('%PDF-', $pdf);
        $this->assertSame('THERMAL_AIR_WAYBILL', $this->sent('download_shipping_document')[0]['shipping_document_type']);
        $this->assertSame([], $this->sent('create_shipping_document'));
    }

    public function test_missing_label_is_created_and_awaited_before_download(): void
    {
        $this->fakeShopee([
            $this->notCreated(),
            $this->reply(['status' => 'PROCESSING']),
            $this->reply(['status' => 'READY']),
        ]);

        $this->shopee->downloadShippingDocument('A1');

        $created = $this->sent('create_shipping_document');
        $this->assertCount(1, $created);
        $this->assertSame('THERMAL_AIR_WAYBILL', $created[0]['order_list'][0]['shipping_document_type']);
        $this->assertSame('RESI1', $created[0]['order_list'][0]['tracking_number']);
        $this->assertCount(3, $this->sent('get_shipping_document_result'));
        $this->assertCount(1, $this->sent('download_shipping_document'));
    }

    public function test_requested_format_is_used_for_creating_and_downloading(): void
    {
        $this->fakeShopee([$this->notCreated(), $this->reply(['status' => 'READY'])]);

        $this->shopee->downloadShippingDocument('A1', 'NORMAL_AIR_WAYBILL');

        $this->assertSame('NORMAL_AIR_WAYBILL', $this->sent('create_shipping_document')[0]['order_list'][0]['shipping_document_type']);
        $this->assertSame('NORMAL_AIR_WAYBILL', $this->sent('download_shipping_document')[0]['shipping_document_type']);
        $this->assertSame([], $this->sent('get_shipping_document_parameter'));
    }

    public function test_shopee_reason_is_reported_when_the_label_fails(): void
    {
        $this->fakeShopee([$this->notCreated(), $this->reply(['status' => 'FAILED', 'fail_message' => 'courier unavailable'])]);

        try {
            $this->shopee->downloadShippingDocument('A1');
            $this->fail('Expected an exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('courier unavailable', $e->getMessage());
        }

        $this->assertSame([], $this->sent('download_shipping_document'));
    }

    public function test_waiting_for_a_label_gives_up_after_a_while(): void
    {
        $this->fakeShopee(array_fill(0, 16, $this->reply(['status' => 'PROCESSING'])));

        try {
            $this->shopee->downloadShippingDocument('A1');
            $this->fail('Expected an exception');
        } catch (\Exception $e) {
            $this->assertStringContainsString('masih diproses', $e->getMessage());
        }

        $this->assertSame([], $this->sent('create_shipping_document'));
        $this->assertSame([], $this->sent('download_shipping_document'));
        Sleep::assertSleptTimes(15);
    }
}
