<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A payroll detail row is now ONE row per seri + model (it groups many
     * received-log scans, e.g. 12 + 12 + 4 pcs => 28 pcs), so the single
     * trx_cmt_payroll_details.received_log_detail_id column can no longer
     * record which scans were paid. This table does: one row per scan,
     * unique so a scan can never be paid twice.
     */
    public function up(): void
    {
        Schema::create('trx_cmt_payroll_detail_items', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cmt_payroll_detail_id');
            $table->uuid('received_log_detail_id')->unique();
            $table->timestamps();

            $table->foreign('cmt_payroll_detail_id')->references('id')->on('trx_cmt_payroll_details')->cascadeOnDelete();
            $table->foreign('received_log_detail_id')->references('id')->on('trx_receivedlog_details')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_cmt_payroll_detail_items');
    }
};
