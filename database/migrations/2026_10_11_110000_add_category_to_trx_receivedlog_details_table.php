<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Terima Barang now also records items that have no QR code, entered by
     * quantity (see InboundController::store `manual_items`):
     *   sewn           - scanned, sewn, good (all existing rows; default)
     *   returned_good  - returned UNSEWN, good  -> counts in rec_bs_qty, no stock, no payroll
     *   returned_defect- returned UNSEWN, defective -> same, flagged is_rejected
     *   extra          - "barang lebih": sewn but without QR -> stock + payroll
     * Manual rows have no barcode, so the column becomes nullable.
     */
    public function up(): void
    {
        Schema::table('trx_receivedlog_details', function (Blueprint $table) {
            $table->string('category', 20)->default('sewn')->after('is_rejected');
            $table->string('barcode')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('trx_receivedlog_details', function (Blueprint $table) {
            $table->dropColumn('category');
            // barcode is left nullable on purpose: rolling back to NOT NULL would
            // fail as soon as any manual row exists.
        });
    }
};
