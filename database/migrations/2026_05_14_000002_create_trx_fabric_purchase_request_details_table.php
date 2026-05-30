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
        Schema::create('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fabric_purchase_request_id');
            $table->foreign('fabric_purchase_request_id', 'tfprd_fpr_id_fk')
                ->references('id')
                ->on('trx_fabric_purchase_requests')
                ->onDelete('cascade');
            $table->uuid('color_id');
            $table->foreign('color_id', 'tfprd_color_id_fk')
                ->references('id')
                ->on('mdx_colors')
                ->onDelete('restrict');
            $table->string('series_code', 100);
            $table->unsignedSmallInteger('series_sequence');
            $table->unsignedInteger('roll_qty');
            $table->timestamps();

            $table->unique([
                'fabric_purchase_request_id',
                'color_id',
                'series_code',
                'series_sequence'
            ], 'trx_fp_request_details_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_purchase_request_details');
    }
};
