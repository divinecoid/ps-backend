<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->decimal('unit_fee', 15, 2)->nullable()->after('req_qty');
        });
    }

    public function down(): void
    {
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->dropColumn('unit_fee');
        });
    }
};
