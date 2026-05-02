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
        Schema::create('trx_rejectedlog_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('receivedlog_id')->constrained('trx_receivedlogs')->onDelete('cascade');
            $table->foreignUuid('request_detail_id')->constrained('trx_request_details')->onDelete('restrict');
            $table->foreignUuid('model_id')->constrained('mdx_models')->onDelete('restrict');
            $table->foreignUuid('color_id')->constrained('mdx_colors')->onDelete('restrict');
            $table->foreignUuid('size_id')->constrained('mdx_sizes')->onDelete('restrict');
            $table->integer('qty')->default(0);
            $table->string('barcode');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_rejectedlog_details');
    }
};
