<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Stock opname records what was counted on a rack; it never changes stock. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_stock_opnames', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('rack_id')->index();
            $table->uuid('user_id')->nullable()->index();
            $table->text('notes')->nullable();
            $table->integer('system_qty');
            $table->integer('counted_qty');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trx_stock_opname_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_opname_id')->constrained('trx_stock_opnames')->cascadeOnDelete();
            // Names are copied so the record still reads correctly after master data changes.
            $table->string('model');
            $table->string('color');
            $table->string('size');
            $table->integer('system_qty');
            $table->integer('counted_qty');
        });

        Schema::create('trx_stock_opname_barcodes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('stock_opname_id')->constrained('trx_stock_opnames')->cascadeOnDelete();
            $table->string('barcode');
            // found | wrong_rack | not_in_system | missing | duplicate | unknown
            $table->string('result', 20);
            $table->string('note')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_stock_opname_barcodes');
        Schema::dropIfExists('trx_stock_opname_items');
        Schema::dropIfExists('trx_stock_opnames');
    }
};
