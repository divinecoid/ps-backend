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
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->dropForeign(['cloth_id']);

            $table->foreign('cloth_id')
                ->references('id')
                ->on('trx_fabric_cuttings')
                ->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->dropForeign(['cloth_id']);

            $table->foreign('cloth_id')
                ->references('id')
                ->on('mdx_clothes')
                ->onDelete('restrict');
        });
    }
};
