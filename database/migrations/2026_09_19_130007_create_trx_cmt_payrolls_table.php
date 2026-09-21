<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_cmt_payrolls', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cmt_id');
            $table->date('period_start');
            $table->date('period_end');
            $table->integer('total_pcs')->default(0);
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->enum('status', ['draft', 'approved', 'paid'])->default('draft');
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('cmt_id')->references('id')->on('mdx_cmts');
        });

        Schema::create('trx_cmt_payroll_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('cmt_payroll_id');
            $table->uuid('request_detail_id')->nullable();
            $table->uuid('received_log_detail_id')->nullable();
            $table->uuid('model_id')->nullable();
            $table->integer('qty')->default(0);
            $table->decimal('unit_fee', 15, 2)->default(0);
            $table->decimal('amount', 15, 2)->default(0);
            $table->timestamps();

            $table->foreign('cmt_payroll_id')->references('id')->on('trx_cmt_payrolls')->cascadeOnDelete();
            $table->foreign('request_detail_id')->references('id')->on('trx_request_details')->nullOnDelete();
            $table->foreign('received_log_detail_id')->references('id')->on('trx_receivedlog_details')->nullOnDelete();
            $table->foreign('model_id')->references('id')->on('mdx_models')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_cmt_payroll_details');
        Schema::dropIfExists('trx_cmt_payrolls');
    }
};
