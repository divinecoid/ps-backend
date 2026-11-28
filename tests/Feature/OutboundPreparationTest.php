<?php

namespace Tests\Feature;

use App\Http\Controllers\Transaction\OrderController;
use App\Models\MasterData\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class OutboundPreparationTest extends TestCase
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
        $table('trx_orders', ['status', 'preparist_user_id', 'prepared_at', 'readytoship_at', 'prepare_duration', 'is_outbounded']);
        $table('trx_order_items', ['order_id', 'product_id', 'item_prepared_at']);
        $table('mdx_products', ['barcode', 'model_id', 'color_id', 'size_id', 'rack_id', 'series', 'stock_batch_id']);

        DB::table('trx_orders')->insert(['id' => 'o', 'status' => 'ready_to_pickup']);
        DB::table('trx_order_items')->insert([['id' => 'i1', 'order_id' => 'o'], ['id' => 'i2', 'order_id' => 'o']]);
        foreach (['A', 'B', 'C'] as $barcode) {
            DB::table('mdx_products')->insert(['id' => "p$barcode", 'barcode' => $barcode]);
        }

        $user = new User();
        $user->id = 'u1';
        auth()->setUser($user);
    }

    /** @param array<string, string[]> $items barcodes by order item id */
    private function submit(array $items): array
    {
        $response = app(OrderController::class)->submitPreparation(Request::create('/', 'POST', [
            'order_id' => 'o',
            'prepared_at' => now()->subMinute()->toISOString(),
            'order_items' => array_map(fn($id) => ['id' => $id, 'scanned_barcodes' => $items[$id]], array_keys($items)),
        ]));

        return [$response->getStatusCode(), $response->getData(true)['message']];
    }

    private function stock(): array
    {
        return DB::table('mdx_products')->whereNull('deleted_at')->orderBy('barcode')->pluck('barcode')->all();
    }

    public function test_preparing_takes_the_pieces_and_records_who_prepared(): void
    {
        $this->assertSame(200, $this->submit(['i1' => ['A'], 'i2' => ['B']])[0]);

        $this->assertSame(['C'], $this->stock());
        $this->assertSame('u1', DB::table('trx_orders')->value('preparist_user_id'));
    }

    public function test_an_assigned_preparist_is_kept(): void
    {
        DB::table('trx_orders')->update(['preparist_user_id' => 'someone']);

        $this->submit(['i1' => ['A']]);

        $this->assertSame('someone', DB::table('trx_orders')->value('preparist_user_id'));
    }

    public function test_an_item_cannot_be_prepared_twice(): void
    {
        $this->submit(['i1' => ['A']]);

        $this->assertSame([409, 'Order ini sudah disiapkan sebelumnya'], $this->submit(['i1' => ['B']]));
        $this->assertSame(['B', 'C'], $this->stock());
    }

    public function test_the_items_left_over_can_still_be_prepared(): void
    {
        $this->submit(['i1' => ['A']]);

        $this->assertSame(200, $this->submit(['i2' => ['B']])[0]);
    }

    public function test_one_barcode_cannot_fill_two_pieces(): void
    {
        $this->assertSame(422, $this->submit(['i1' => ['A'], 'i2' => ['A']])[0]);
        $this->assertSame(['A', 'B', 'C'], $this->stock());
    }

    public function test_a_bad_barcode_leaves_the_stock_alone(): void
    {
        $this->assertSame(404, $this->submit(['i1' => ['A'], 'i2' => ['ngawur']])[0]);
        $this->assertSame(['A', 'B', 'C'], $this->stock());
        $this->assertNull(DB::table('trx_orders')->value('is_outbounded'));
    }
}
