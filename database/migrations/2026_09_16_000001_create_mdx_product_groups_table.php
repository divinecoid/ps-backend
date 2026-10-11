<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mdx_product_groups', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_id')->constrained('mdx_models');
            $table->foreignUuid('color_id')->nullable()->constrained('mdx_colors');
            $table->foreignUuid('size_id')->nullable()->constrained('mdx_sizes');
            $table->string('series')->nullable();
            $table->integer('qty')->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['model_id', 'color_id', 'size_id', 'series'], 'mdx_product_groups_unique_key');
        });

        // Backfill product_groups from the existing mdx_products rows so historical
        // "Input Stok" batches are grouped too, without touching mdx_products itself.
        $rows = DB::table('mdx_products')
            ->select(
                'model_id',
                'color_id',
                'size_id',
                'series',
                DB::raw('COUNT(*) as qty'),
                DB::raw('MIN(created_at) as created_at'),
                DB::raw('MAX(updated_at) as updated_at')
            )
            ->whereNull('deleted_at')
            ->groupBy('model_id', 'color_id', 'size_id', 'series')
            ->get();

        $now = now();
        foreach ($rows as $row) {
            DB::table('mdx_product_groups')->insert([
                'id' => (string) Str::uuid(),
                'model_id' => $row->model_id,
                'color_id' => $row->color_id,
                'size_id' => $row->size_id,
                'series' => $row->series,
                'qty' => $row->qty,
                'created_at' => $row->created_at ?? $now,
                'updated_at' => $row->updated_at ?? $now,
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_product_groups');
    }
};
