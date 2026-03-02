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
        Schema::create('mdx_inventories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('model_id')->constrained('mdx_models')->onDelete('restrict');
            $table->foreignUuid('color_id')->constrained('mdx_colors')->onDelete('restrict');
            $table->foreignUuid('size_id')->constrained('mdx_sizes')->onDelete('restrict');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_inventories');
    }
};
