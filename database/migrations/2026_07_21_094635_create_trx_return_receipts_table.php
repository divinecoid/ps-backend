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
        Schema::create('trx_return_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('order_id')->constrained('trx_orders')->onDelete('restrict');
            $table->string('awb_code');
            $table->timestamp('received_at')->nullable();
            $table->enum('return_status', ['pending', 'partial', 'received'])->default('pending');
            $table->foreignUuid('received_by')->nullable()->constrained('users')->onDelete('restrict');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('trx_return_receipt_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('return_receipt_id')->constrained('trx_return_receipts')->onDelete('cascade');
            $table->foreignUuid('order_item_id')->constrained('trx_order_items')->onDelete('restrict');
            $table->string('barcode_scanned')->nullable();
            $table->boolean('is_received')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_return_receipt_details');
        Schema::dropIfExists('trx_return_receipts');
    }
};
