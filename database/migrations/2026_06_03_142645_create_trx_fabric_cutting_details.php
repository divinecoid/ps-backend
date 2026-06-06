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
        Schema::create('trx_fabric_cutting_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid(('fabric_cutting_id'))->constrained('trx_fabric_cuttings')->onDelete('cascade');
            $table->foreignUuid('model_id')->constrained('mdx_models')->onDelete('restrict');
            $table->foreignUuid('size_id')->constrained('mdx_sizes')->onDelete('restrict');
            $table->integer('req_qty')->default(0);
            $table->integer('rec_qty')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_cutting_details');
    }
};
