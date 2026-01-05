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
        // Schema::create('trx_scanned_items', function (Blueprint $table) {
        //     $table->uuid('id')->primary();
        //     $table->foreignUuid('inventory_id')->constrained('mdx_inventories')->onDelete('restrict');
        //     $table->foreignUuid('rack_id')->constrained('mdx_racks')->onDelete('restrict');
        //     $table->string('barcode')->unique();
        //     $table->foreignUuid('order_item_id')->constrained('trx_order_items')->onDelete('restrict');
        //     $table->softDeletes();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('trx_scanned_items');
    }
};
