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
        Schema::create('trx_request_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid(('request_id'))->constrained('trx_requests')->onDelete('cascade');
            $table->foreignUuid('model_id')->constrained('mdx_models')->onDelete('restrict');
            $table->foreignUuid('color_id')->constrained('mdx_colors')->onDelete('restrict');
            $table->foreignUuid('size_id')->constrained('mdx_sizes')->onDelete('restrict');
            $table->integer('req_dozen_qty');
            $table->integer('req_piece_qty');
            $table->integer('rec_dozen_qty');
            $table->integer('rec_piece_qty');
            $table->integer('rec_bs_qty');
            $table->softDeletes();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_request_details');
    }
};
