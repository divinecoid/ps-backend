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
        Schema::create('trx_fabric_cutting_clothes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('fabric_cutting_id')->constrained('trx_fabric_cuttings')->onDelete('cascade');
            $table->foreignUuid('fabric_id')->constrained('mdx_clothes')->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_cutting_clothes');
    }
};
