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
            $table->id();
            $table->string('serial_number')->unique();
            $table->foreignId('product_id')->constrained('mdx_products')->onDelete('restrict');
            $table->foreignId('factory_id')->constrained('mdx_factories')->onDelete('restrict');
            $table->foreignId('rack_id')->constrained('mdx_racks')->onDelete('restrict');
            $table->foreignId('cmt_id')->constrained('mdx_cmts')->onDelete('restrict');
            $table->string('barcode_group')->unique();
            $table->integer('quantity');
            $table->timestamps();
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
