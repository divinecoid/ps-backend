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
        Schema::create('mdx_cmt_model_rates', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_id')->constrained('mdx_models')->onDelete('restrict');
            $table->enum('kategori', ['DALAM_KOTA', 'LUAR_KOTA']);
            $table->decimal('rate', 15, 2)->default(0);
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['model_id', 'kategori']);
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
