<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marketplace addresses can be longer than 255 characters, which made the whole
     * order sync fail.
     */
    public function up(): void
    {
        Schema::table('trx_orders', function (Blueprint $table) {
            $table->text('customer_address')->nullable()->change();
        });
    }

    /**
     * Fails if a longer address has been stored in the meantime, rather than cutting it.
     */
    public function down(): void
    {
        Schema::table('trx_orders', function (Blueprint $table) {
            $table->string('customer_address')->nullable()->change();
        });
    }
};
