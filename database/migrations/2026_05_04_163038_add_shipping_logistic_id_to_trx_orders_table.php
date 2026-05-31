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
            $table->uuid('shipping_logistic_id')->nullable()->after('awb_code');
            $table->foreign('shipping_logistic_id')
                  ->references('id')
                  ->on('mdx_shipping_logistics')
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_orders', function (Blueprint $table) {
            $table->dropForeign(['shipping_logistic_id']);
            $table->dropColumn('shipping_logistic_id');
        });
    }
};
