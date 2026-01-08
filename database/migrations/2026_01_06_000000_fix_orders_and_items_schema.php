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
        Schema::table('trx_orders', function (Blueprint $table) {
            if (!Schema::hasColumn('trx_orders', 'order_sn')) {
                $table->string('order_sn')->unique()->after('id');
            }
            // Make preparist_user_id nullable
            $table->uuid('preparist_user_id')->nullable()->change();
        });

        if (!Schema::hasTable('trx_order_items')) {
            Schema::create('trx_order_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->foreignUuid('order_id')->constrained('trx_orders')->onDelete('cascade');
                $table->string('order_item_id')->nullable(); // Shopee item ID
                $table->string('sku')->nullable();
                $table->string('item_name')->nullable();
                $table->foreignUuid('product_id')->nullable()->constrained('mdx_products')->onDelete('set null');
                $table->decimal('model_original_price', 18, 2)->nullable();
                $table->decimal('model_discounted_price', 18, 2)->nullable();
                $table->integer('model_quantity_purchased')->default(0);
                $table->timestamp('item_prepared_at')->nullable();
                $table->text('notes')->nullable();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_order_items');
        
        Schema::table('trx_orders', function (Blueprint $table) {
            $table->dropColumn('order_sn');
            // Cannot easily revert nullable change without knowing original state or data constraints
        });
    }
};
