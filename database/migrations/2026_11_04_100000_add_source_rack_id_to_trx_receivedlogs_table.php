<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Mutasi Opsi 1: rak gudang besar asal barang. Null = Opsi 2 (rak tujuan saja) / bukan mutasi. */
    public function up(): void
    {
        Schema::table('trx_receivedlogs', function (Blueprint $table) {
            $table->string('source_rack_id', 36)->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('trx_receivedlogs', function (Blueprint $table) {
            $table->dropColumn('source_rack_id');
        });
    }
};
