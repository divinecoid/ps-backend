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
        Schema::create('models_sizes', function (Blueprint $table) {
            $table->foreignUuid('model_id')->constrained('mdx_models')->onDelete('cascade');
            $table->foreignUuid('size_id')->constrained('mdx_sizes')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('models_sizes');
    }
};
