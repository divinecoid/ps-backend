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
            $table->foreignUuid('fabric_purchase_request_id')
                ->constrained('trx_fabric_purchase_requests')
                ->onDelete('cascade');
            $table->foreignUuid('color_id')->constrained('mdx_colors')->onDelete('restrict');
            $table->string('series_code', 100);
            $table->unsignedSmallInteger('series_sequence');
            $table->unsignedInteger('roll_qty');
            $table->timestamps();

            $table->unique([
                'fabric_purchase_request_id',
                'sequence'
            ], 'trx_fp_request_details_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_purchase_request_details');
    }
};
