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
        Schema::table('trx_fabric_cutting_details', function (Blueprint $table) {
            $table->renameColumn('rec_qty', 'avl_qty');
        });
        Schema::table('trx_fabric_cuttings', function (Blueprint $table) {
            $table->enum('status', ['OPEN', 'CLOSED'])->default('OPEN');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_fabric_cutting_details', function (Blueprint $table) {
            $table->renameColumn('avl_qty', 'rec_qty');
        });
         Schema::table('trx_fabric_cuttings', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
