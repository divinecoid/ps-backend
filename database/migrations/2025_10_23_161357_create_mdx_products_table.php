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
        Schema::create('mdx_products', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->foreignId('color_id')->constrained('mdx_colors')->onDelete('restrict');
            $table->foreignId('model_id')->constrained('mdx_models')->onDelete('restrict');
            $table->foreignId('size_id')->constrained('mdx_sizes')->onDelete('restrict');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_products');
    }
};
