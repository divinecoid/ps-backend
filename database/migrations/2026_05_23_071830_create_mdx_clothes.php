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
        Schema::create('mdx_roll_sizes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('size');
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('mdx_clothes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('factory_id')->references('id')->on('mdx_factories')->onDelete('cascade');
            $table->string('gram');
            $table->foreignUuid('roll_size_id')->references('id')->on('mdx_roll_sizes')->onDelete('cascade');
            $table->foreignUuid('color_id')->references('id')->on('mdx_colors')->onDelete('cascade');
            $table->integer('quantity');
            $table->string('sequence');
            $table->timestamps();
            $table->softDeletes();
        });

    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_roll_sizes');
        Schema::dropIfExists('mdx_clothes');
    }
};
