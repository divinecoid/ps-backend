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
        Schema::create('mdx_scanneditems', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inventories_id')->constrained('mdx_inventories')->onDelete('restrict');
            $table->foreignId('rack_id')->constrained('mdx_racks')->onDelete('restrict');
            $table->string('barcode')->unique();
            $table->foreignId('order_items')->constrained('trx_order_items')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_scanneditems');
    }
};
