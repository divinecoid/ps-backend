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
        Schema::create('trx_fabric_cutting_receives', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fabric_cutting_id')->constrained('trx_fabric_cuttings')->onDelete('cascade');
            $table->foreignUuid('model_id')->constrained('mdx_models')->onDelete('cascade');
            $table->foreignUuid('cloth_id')->constrained('mdx_clothes')->onDelete('cascade');
            $table->foreignUuid('size_id')->constrained('mdx_sizes')->onDelete('cascade');
            $table->integer('qty')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_cutting_receives');
    }
};
