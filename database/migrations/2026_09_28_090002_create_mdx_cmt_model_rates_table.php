<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // On environments where the table already exists from the earlier
        // model_id-based version of this migration, the follow-up
        // "migrate model_id to group_id" migration handles the conversion
        // instead of recreating the table here.
        if (Schema::hasTable('mdx_cmt_model_rates')) {
            return;
        }

        Schema::create('mdx_cmt_model_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('group_id')->constrained('mdx_cmt_rate_groups')->onDelete('restrict');
            $table->enum('kategori', ['DALAM_KOTA', 'LUAR_KOTA']);
            $table->decimal('rate', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['group_id', 'kategori']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_cmt_model_rates');
    }
};
