<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trx_receivedlogs', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->uuid('warehouse_id')->nullable()->change();
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('mdx_warehouses')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_receivedlogs', function (Blueprint $table) {
            $table->dropForeign(['warehouse_id']);
            $table->uuid('warehouse_id')->nullable(false)->change();
            $table->foreign('warehouse_id')
                ->references('id')
                ->on('mdx_warehouses')
                ->onDelete('restrict');
        });
    }
};
