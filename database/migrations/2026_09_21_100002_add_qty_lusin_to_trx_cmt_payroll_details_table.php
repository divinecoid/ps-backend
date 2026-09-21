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
        Schema::table('trx_cmt_payroll_details', function (Blueprint $table) {
            $table->decimal('qty_lusin', 10, 2)->nullable()->after('qty');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_cmt_payroll_details', function (Blueprint $table) {
            $table->dropColumn('qty_lusin');
        });
    }
};
