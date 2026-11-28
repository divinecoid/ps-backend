<?php

namespace Tests\Feature;

use App\Services\OnlineOutboundReport;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OnlineOutboundReportTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $table = function (string $name, array $columns) {
            Schema::create($name, function ($t) use ($columns) {
                $t->string('id')->primary();
                foreach ($columns as $column) {
                    $t->string($column)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        };
        $table('mdx_marketplaces', ['name']);
        $table('mdx_online_stores', ['store_name', 'marketplace_id']);
        $table('mdx_models', ['sku', 'name']);
        $table('mdx_colors', ['code', 'name']);
        $table('mdx_sizes', ['code', 'name']);
        $table('trx_orders', ['online_store_id', 'marketplace_id', 'status', 'is_outbounded', 'readytoship_at']);
        $table('trx_order_items', ['order_id', 'sku', 'color', 'size', 'item_name', 'quantity_purchased']);

        DB::table('mdx_marketplaces')->insert([['id' => 'sp', 'name' => 'Shopee'], ['id' => 'tt', 'name' => 'TikTok Shop']]);
        DB::table('mdx_online_stores')->insert([
            ['id' => 'a', 'store_name' => 'Toko A', 'marketplace_id' => 'sp'],
            ['id' => 'b', 'store_name' => 'Toko B', 'marketplace_id' => 'tt'],
        ]);
        DB::table('mdx_models')->insert(['id' => 'm1', 'sku' => 'POLOS', 'name' => 'Kaos Polos']);
        DB::table('mdx_colors')->insert(['id' => 'c1', 'code' => 'RED', 'name' => 'Merah']);
        DB::table('mdx_sizes')->insert(['id' => 's1', 'code' => 'M', 'name' => 'Sedang']);

        // 18:00 UTC on 31 Oct is already 1 Nov in Jakarta.
        $this->order('1', 'a', 'shipped', true, '2026-10-31 18:00:00', ['PAKET2-POLOS', 'merah=RED', 'sedang', 3]);
        $this->order('2', 'b', 'ready_to_pickup', true, '2026-11-05 03:00:00', ['polos', 'RED', 'M', 1]);
        $this->order('3', 'a', 'cancelled', true, '2026-11-05 03:00:00', ['POLOS', 'RED', 'M', 9]);
        $this->order('4', 'a', 'ready_to_pickup', false, null, ['POLOS', 'RED', 'M', 9]);
    }

    private function order(string $id, string $store, string $status, bool $out, ?string $at, array $item): void
    {
        DB::table('trx_orders')->insert(['id' => $id, 'online_store_id' => $store, 'status' => $status, 'is_outbounded' => $out, 'readytoship_at' => $at]);
        DB::table('trx_order_items')->insert(['id' => "i$id", 'order_id' => $id, 'sku' => $item[0], 'color' => $item[1], 'size' => $item[2], 'quantity_purchased' => $item[3]]);
    }

    public function test_counts_pieces_that_left_per_store_model_colour_and_size(): void
    {
        $report = app(OnlineOutboundReport::class)->build(['date_from' => '2026-11-01', 'date_to' => '2026-11-30']);

        $this->assertSame(['qty' => 7, 'orders' => 2], $report['summary']);
        $this->assertSame(
            ['Shopee/Toko A/Kaos Polos/Merah/M/6', 'TikTok Shop/Toko B/Kaos Polos/Merah/M/1'],
            array_map(fn($r) => implode('/', [$r['marketplace'], $r['store'], $r['model'], $r['color'], $r['size'], $r['qty']]), $report['rows'])
        );
    }

    public function test_filters_by_marketplace_store_and_date(): void
    {
        $service = app(OnlineOutboundReport::class);

        $this->assertSame(1, $service->build(['marketplace_id' => 'tt'])['summary']['qty']);
        $this->assertSame(6, $service->build(['online_store_id' => 'a'])['summary']['qty']);
        $this->assertSame(0, $service->build(['date_to' => '2026-10-31'])['summary']['qty']);
    }
}
