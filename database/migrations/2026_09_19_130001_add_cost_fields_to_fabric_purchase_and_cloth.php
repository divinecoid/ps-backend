<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->nullable()->after('quantity');
            $table->decimal('shipping_cost', 15, 2)->nullable()->after('unit_price');
        });

        Schema::table('mdx_clothes', function (Blueprint $table) {
            $table->decimal('unit_price', 15, 2)->nullable()->after('quantity');
            $table->decimal('shipping_cost_allocated', 15, 2)->nullable()->after('unit_price');
            $table->decimal('remaining_quantity', 15, 2)->nullable()->after('shipping_cost_allocated');
        });
    }

    public function down(): void
    {
        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'shipping_cost']);
        });

        Schema::table('mdx_clothes', function (Blueprint $table) {
            $table->dropColumn(['unit_price', 'shipping_cost_allocated', 'remaining_quantity']);
        });
    }
};
