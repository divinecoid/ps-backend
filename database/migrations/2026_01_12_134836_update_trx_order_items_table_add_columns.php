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
        Schema::table('trx_order_items', function (Blueprint $table) {
            // Rename columns to match "Left" side of the request image
            $table->renameColumn('model_quantity_purchased', 'quantity_purchased');
            $table->renameColumn('model_discounted_price', 'discounted_price');
            $table->renameColumn('model_original_price', 'price');
            
            // Add new columns
            $table->string('color')->nullable()->after('item_name');
            $table->string('size')->nullable()->after('color');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_order_items', function (Blueprint $table) {
             $table->renameColumn('quantity_purchased', 'model_quantity_purchased');
             $table->renameColumn('discounted_price', 'model_discounted_price');
             $table->renameColumn('price', 'model_original_price');
             $table->dropColumn(['color', 'size']);
        });
    }
};
