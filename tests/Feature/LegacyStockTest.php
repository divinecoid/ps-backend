<?php

namespace Tests\Feature;

use App\Models\MasterData\InventoryDetail;
use App\Models\MasterData\Product;
use App\Services\LegacyStockService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

class LegacyStockTest extends TestCase
{
    private array $ids = [];

    protected function setUp(): void
    {
        parent::setUp();

        // Only the tables this feature touches; the full migration set is MySQL-specific.
        $table = function (string $name, array $columns) {
            Schema::create($name, function ($t) use ($columns) {
                $t->uuid('id')->primary();
                foreach ($columns as $column) {
                    $column === 'quantity' ? $t->integer($column)->default(0) : $t->string($column)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        };
        $table('mdx_warehouses', ['name', 'type']);
        $table('mdx_racks', ['code', 'name', 'warehouse_id', 'model_id', 'color_id', 'size_id']);
        $table('mdx_models', ['sku', 'name']);
        $table('mdx_colors', ['code', 'name']);
        $table('mdx_sizes', ['code', 'name']);
        $table('mdx_products', ['model_id', 'rack_id', 'color_id', 'size_id', 'series', 'barcode', 'stock_batch_id']);
        $table('mdx_inventories', ['model_id', 'color_id', 'size_id', 'rack_id']);
        $table('mdx_inventory_details', ['inventory_id', 'series', 'quantity']);

        foreach (['big' => 'BESAR', 'small' => 'KECIL'] as $key => $type) {
            $warehouse = $this->insert('mdx_warehouses', ['name' => $key, 'type' => $type]);
            $this->ids["{$key}Rack"] = $this->insert('mdx_racks', ['code' => $key, 'name' => $key, 'warehouse_id' => $warehouse]);
        }
        $this->ids['model'] = $this->insert('mdx_models', ['sku' => 'LP', 'name' => 'Long Pants']);
        $this->ids['red'] = $this->insert('mdx_colors', ['code' => 'RED', 'name' => 'Red']);
        $this->ids['blue'] = $this->insert('mdx_colors', ['code' => 'BLU', 'name' => 'Blue']);
        $this->ids['size'] = $this->insert('mdx_sizes', ['code' => 'M', 'name' => 'M']);
    }

    private function insert(string $table, array $values): string
    {
        $id = (string) Str::uuid();
        DB::table($table)->insert(['id' => $id] + $values);

        return $id;
    }

    private function service(): LegacyStockService
    {
        return app(LegacyStockService::class);
    }

    /** Dozens that share one series typed by the user. */
    private function inputDozens(int $count, string $series = 'LAMA', ?string $rack = null): string
    {
        $batch = (string) Str::uuid();
        $this->service()->registerMany(array_map(fn($n) => [
            'model_id' => $this->ids['model'],
            'color_id' => $this->ids['red'],
            'size_id' => $this->ids['size'],
            'rack_id' => $rack,
            'series' => $series,
            'barcode' => "CMT|{$series}|LP|RED|M|D|{$n}",
            'type' => 'D',
            'batch_id' => $batch,
        ], range(1, $count)));

        return $batch;
    }

    private function stock(?string $colorId = null): int
    {
        return (int) InventoryDetail::whereHas('inventory', fn($q) => $q->where('color_id', $colorId ?? $this->ids['red']))->sum('quantity');
    }

    public function test_input_goes_to_the_big_warehouse(): void
    {
        $batch = $this->inputDozens(2, rack: $this->ids['bigRack']);

        $this->assertSame(24, $this->stock());
        $this->assertSame(26, Product::where('stock_batch_id', $batch)->count());
        $this->assertSame(0, Product::whereNotNull('rack_id')->count());
    }

    public function test_small_warehouse_rack_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->inputDozens(1, rack: $this->ids['smallRack']);
    }

    public function test_shrinking_a_batch_with_a_shared_series_keeps_the_other_dozens(): void
    {
        $batch = $this->inputDozens(3);

        $this->service()->resizeBatch($batch, 2);

        $this->assertSame(24, $this->stock());
        $this->assertSame(26, Product::where('stock_batch_id', $batch)->count());
    }

    public function test_growing_again_keeps_the_series_and_never_reissues_a_barcode(): void
    {
        $batch = $this->inputDozens(3);
        $this->service()->resizeBatch($batch, 2);

        $this->service()->resizeBatch($batch, 4);

        $this->assertSame(48, $this->stock());
        $all = Product::withTrashed()->where('stock_batch_id', $batch)->pluck('barcode');
        $this->assertSame($all->count(), $all->unique()->count());
        $this->assertSame(['LAMA'], Product::where('stock_batch_id', $batch)->pluck('series')->unique()->values()->all());
    }

    public function test_one_dozen_family_does_not_pull_in_the_rest_of_the_series(): void
    {
        $batch = $this->inputDozens(3);
        $piece = Product::where('stock_batch_id', $batch)->where('barcode', 'CMT|LAMA|LP|RED|M|P|14')->first();

        $family = $this->service()->dozenFamily($piece);

        $this->assertCount(13, $family);
        $this->service()->release($family);
        $this->assertSame(24, $this->stock());
        $this->service()->reclaim($family);
        $this->assertSame(36, $this->stock());
    }

    public function test_changing_the_colour_moves_stock_without_creating_or_losing_it(): void
    {
        $batch = $this->inputDozens(2);
        InventoryDetail::query()->update(['quantity' => 20]); // 4 pcs already left the warehouse

        $this->service()->updateRows(Product::where('stock_batch_id', $batch)->get(), ['color_id' => $this->ids['blue']]);

        $this->assertSame(0, $this->stock());
        $this->assertSame(20, $this->stock($this->ids['blue']));
        $this->assertSame(26, Product::where('barcode', 'like', 'CMT|LAMA|LP|BLU|M|%')->count());
        $this->assertSame(26, Product::count());
    }

    public function test_editing_a_large_batch_needs_few_queries(): void
    {
        $batch = $this->inputDozens(200);
        $rows = Product::where('stock_batch_id', $batch)->get();
        DB::enableQueryLog();

        $this->service()->updateRows($rows, ['color_id' => $this->ids['blue']]);

        $this->assertLessThan(40, count(DB::getQueryLog()));
        $this->assertSame(2400, $this->stock($this->ids['blue']));
    }
}
