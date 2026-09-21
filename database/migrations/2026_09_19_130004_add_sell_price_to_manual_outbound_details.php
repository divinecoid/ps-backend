<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_manual_outbound_details', function (Blueprint $table) {
            $table->decimal('sell_price', 15, 2)->nullable()->after('size_id');
        });
    }

    public function down(): void
    {
        Schema::table('trx_manual_outbound_details', function (Blueprint $table) {
            $table->dropColumn('sell_price');
        });
    }
};
