<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_orders', function (Blueprint $table) {
            // instant | sameday | nextday | express | reguler | hemat | kargo | ambil_sendiri
            $table->string('service_type', 20)->nullable()->index();
            $table->string('shipping_courier', 60)->nullable()->index();
            // Service name exactly as the marketplace shows it, e.g. "SPX Instant".
            $table->string('delivery_option', 120)->nullable();
            // Latest time the seller has to arrange shipment.
            $table->timestamp('ship_by_at')->nullable();
            // When the buyer ordered (created_at is when we pulled the order).
            $table->timestamp('ordered_at')->nullable()->index();
        });

        // Existing orders sort and filter by when they were pulled until the
        // next sync fills in the real order time.
        DB::table('trx_orders')->whereNull('ordered_at')->update(['ordered_at' => DB::raw('created_at')]);
    }

    public function down(): void
    {
        Schema::table('trx_orders', function (Blueprint $table) {
            $table->dropIndex(['service_type']);
            $table->dropIndex(['shipping_courier']);
            $table->dropIndex(['ordered_at']);
            $table->dropColumn(['service_type', 'shipping_courier', 'delivery_option', 'ship_by_at', 'ordered_at']);
        });
    }
};
