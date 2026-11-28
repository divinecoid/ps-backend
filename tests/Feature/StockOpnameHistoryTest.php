<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class StockOpnameHistoryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $table = function (string $name, array $columns) {
            Schema::create($name, function ($t) use ($columns) {
                $t->string('id')->primary();
                foreach ($columns as $column) {
                    str_ends_with($column, '_qty') ? $t->integer($column) : $t->string($column)->nullable();
                }
                $t->timestamps();
                $t->softDeletes();
            });
        };
        $table('mdx_warehouses', ['name']);
        $table('mdx_racks', ['code', 'name', 'warehouse_id']);
        $table('users', ['name']);
        $table('trx_stock_opnames', ['rack_id', 'user_id', 'notes', 'system_qty', 'counted_qty']);
        Schema::create('trx_stock_opname_barcodes', function ($t) {
            $t->string('id')->primary();
            $t->string('stock_opname_id');
            $t->string('barcode');
            $t->string('result');
            $t->string('note')->nullable();
        });

        DB::table('mdx_racks')->insert([['id' => 'r1', 'code' => 'RA1', 'name' => 'Rak Merah'], ['id' => 'r2', 'code' => 'RB1', 'name' => 'Rak Biru']]);
        DB::table('users')->insert([['id' => 'u1', 'name' => 'Budi'], ['id' => 'u2', 'name' => 'Sari']]);
        // 18:00 UTC on 31 Oct is already 1 Nov in Jakarta.
        $this->opname('o1', 'r1', 'u1', '2026-10-31 18:00:00');
        $this->opname('o2', 'r2', 'u2', '2026-11-10 03:00:00');
        foreach (['found', 'missing', 'wrong_rack'] as $i => $result) {
            DB::table('trx_stock_opname_barcodes')->insert(['id' => "b$i", 'stock_opname_id' => 'o1', 'barcode' => "x$i", 'result' => $result]);
        }
    }

    private function opname(string $id, string $rack, string $user, string $at): void
    {
        DB::table('trx_stock_opnames')->insert(['id' => $id, 'rack_id' => $rack, 'user_id' => $user, 'system_qty' => 2, 'counted_qty' => 2, 'created_at' => $at]);
    }

    private function ids(string $query = ''): array
    {
        return array_column($this->withoutMiddleware()->getJson("/api/stock-opname{$query}")->assertOk()->json('data'), 'id');
    }

    public function test_history_is_newest_first_and_counts_barcodes_to_check(): void
    {
        $rows = $this->withoutMiddleware()->getJson('/api/stock-opname')->assertOk()->json('data');

        $this->assertSame(['o2', 'o1'], array_column($rows, 'id'));
        $this->assertSame([0, 2], array_column($rows, 'problem_count'));
    }

    public function test_history_filters_by_jakarta_date_rack_and_counter(): void
    {
        $this->assertSame(['o1'], $this->ids('?date_from=2026-11-01&date_to=2026-11-01'));
        $this->assertSame(['o2'], $this->ids('?search=biru'));
        $this->assertSame(['o1'], $this->ids('?search=RA1'));
        $this->assertSame(['o2'], $this->ids('?search=sari'));
        $this->assertSame([], $this->ids('?search=sari&date_to=2026-11-01'));
    }
}
