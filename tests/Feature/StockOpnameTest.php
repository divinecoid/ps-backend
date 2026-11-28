<?php

namespace Tests\Feature;

use App\Models\MasterData\Rack;
use App\Services\StockOpnameService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockOpnameTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $table = function (string $name, array $columns) {
            Schema::create($name, function ($t) use ($columns) {
                $t->string('id')->primary();
                foreach ($columns as $column) {
                    $column === 'quantity' ? $t->integer($column) : $t->string($column)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        };
        $table('mdx_warehouses', ['name', 'type']);
        $table('mdx_racks', ['code', 'name', 'warehouse_id']);
        $table('mdx_models', ['sku', 'name']);
        $table('mdx_colors', ['code', 'name']);
        $table('mdx_sizes', ['code', 'name']);
        $table('mdx_products', ['model_id', 'rack_id', 'color_id', 'size_id', 'series', 'barcode', 'stock_batch_id']);
        $table('mdx_inventories', ['model_id', 'color_id', 'size_id', 'rack_id']);
        $table('mdx_inventory_details', ['inventory_id', 'series', 'quantity']);

        DB::table('mdx_warehouses')->insert([['id' => 'big', 'type' => 'BESAR'], ['id' => 'small', 'type' => 'KECIL']]);
        DB::table('mdx_racks')->insert([
            ['id' => 'B1', 'code' => 'B1', 'warehouse_id' => 'big'],
            ['id' => 'K1', 'code' => 'K1', 'warehouse_id' => 'small'],
            ['id' => 'K2', 'code' => 'K2', 'warehouse_id' => 'small'],
        ]);
        DB::table('mdx_models')->insert(['id' => 'm', 'sku' => 'LP', 'name' => 'Long Pants']);
        DB::table('mdx_colors')->insert(['id' => 'c', 'code' => 'RED', 'name' => 'Merah']);
        DB::table('mdx_sizes')->insert(['id' => 's', 'code' => 'M', 'name' => 'Sedang']);

        foreach (['1' => 'K1', '2' => 'K1', '3' => 'K2'] as $n => $rack) {
            $this->product("C|1|LP|RED|M|P|$n", $rack);
        }
        DB::table('mdx_inventories')->insert(['id' => 'inv', 'model_id' => 'm', 'color_id' => 'c', 'size_id' => 's', 'rack_id' => 'B1']);
        DB::table('mdx_inventory_details')->insert(['id' => 'd', 'inventory_id' => 'inv', 'series' => '9', 'quantity' => 24]);
    }

    private function product(string $barcode, ?string $rack): void
    {
        DB::table('mdx_products')->insert(['id' => md5($barcode), 'model_id' => 'm', 'color_id' => 'c', 'size_id' => 's', 'rack_id' => $rack, 'barcode' => $barcode]);
    }

    private function compare(string $rack, array $barcodes): array
    {
        $result = app(StockOpnameService::class)->compare(Rack::with('warehouse')->find($rack), $barcodes);
        $result['results'] = array_column($result['barcodes'], 'result', 'barcode');

        return $result;
    }

    public function test_small_rack_reports_missing_misplaced_and_unknown_barcodes(): void
    {
        $result = $this->compare('K1', ['C|1|LP|RED|M|P|1', 'C|1|LP|RED|M|P|1', 'C|1|LP|RED|M|P|3', 'ngawur']);

        $this->assertSame([2, 2], [$result['system_qty'], $result['counted_qty']]);
        $this->assertSame('found', $result['results']['C|1|LP|RED|M|P|1']);
        $this->assertSame('wrong_rack', $result['results']['C|1|LP|RED|M|P|3']);
        $this->assertSame('missing', $result['results']['C|1|LP|RED|M|P|2']);
        $this->assertSame('unknown', $result['results']['ngawur']);
    }

    public function test_big_rack_counts_dozens_without_counting_their_pieces_twice(): void
    {
        $result = $this->compare('B1', ['C|9|LP|RED|M|D|1', 'C|9|LP|RED|M|P|5', 'C|9|LP|RED|M|P|13']);

        $this->assertSame([24, 13], [$result['system_qty'], $result['counted_qty']]);
        $this->assertSame('found', $result['results']['C|9|LP|RED|M|D|1']);
        $this->assertSame('duplicate', $result['results']['C|9|LP|RED|M|P|5']);
        $this->assertSame('found', $result['results']['C|9|LP|RED|M|P|13']);
    }

    public function test_a_dozen_barcode_placed_on_a_rack_counts_as_the_system_counts_it(): void
    {
        $this->product('C|1|LP|RED|M|D|9', 'K2');

        $result = $this->compare('K2', ['C|1|LP|RED|M|P|3', 'C|1|LP|RED|M|D|9']);

        $this->assertSame([2, 2], [$result['system_qty'], $result['counted_qty']]);
    }

    public function test_counting_never_changes_stock(): void
    {
        $this->compare('B1', []);

        $this->assertSame(24, (int) DB::table('mdx_inventory_details')->sum('quantity'));
        $this->assertSame(3, DB::table('mdx_products')->whereNull('deleted_at')->count());
    }
}
