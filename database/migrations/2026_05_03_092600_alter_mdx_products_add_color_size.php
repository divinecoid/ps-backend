<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('mdx_products', function (Blueprint $table) {
            $table->foreignUuid('color_id')->constrained('mdx_colors')->onDelete('restrict');
            $table->foreignUuid('size_id')->constrained('mdx_sizes')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdx_products', function (Blueprint $table) {
            $table->dropForeign('color_id');
            $table->dropForeign('size_id');
        });
    }
};
