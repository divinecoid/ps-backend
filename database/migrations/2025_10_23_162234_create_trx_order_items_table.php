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
        Schema::create('trx_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('trx_orders')->onDelete('restrict');
            $table->unsignedBigInteger('order_item_id')->nullable();
            $table->string('sku');
            $table->foreignId('product_id')->nullable()->constrained('mdx_products')->onDelete('restrict');
            $table->timestamp('item_prepared_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_order_items');
    }
};
