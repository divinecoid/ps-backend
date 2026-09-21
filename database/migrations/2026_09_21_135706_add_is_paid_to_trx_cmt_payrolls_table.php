<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_cmt_payrolls', function (Blueprint $table) {
            $table->boolean('is_paid')->default(false)->after('status');
        });

        // Backfill: payrolls already marked 'paid' via the old status workflow
        // should keep showing as paid under the new explicit flag.
        \Illuminate\Support\Facades\DB::table('trx_cmt_payrolls')
            ->where('status', 'paid')
            ->update(['is_paid' => true]);
    }

    public function down(): void
    {
        Schema::table('trx_cmt_payrolls', function (Blueprint $table) {
            $table->dropColumn('is_paid');
        });
    }
};
