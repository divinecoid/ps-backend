<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Riwayat pergerakan barang per barcode lewat Mutasi (antar rak / antar gudang). */
    public function up(): void
    {
        Schema::create('trx_mutation_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('receivedlog_id', 36)->index();
            $table->string('barcode')->index();
            $table->string('model_id', 36)->nullable();
            $table->string('color_id', 36)->nullable();
            $table->string('size_id', 36)->nullable();
            $table->string('from_rack_id', 36)->nullable()->index();
            $table->string('to_rack_id', 36)->index();
            $table->string('from_warehouse_id', 36)->nullable();
            $table->string('to_warehouse_id', 36)->nullable();
            $table->string('user_id', 36)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_mutation_histories');
    }
};
