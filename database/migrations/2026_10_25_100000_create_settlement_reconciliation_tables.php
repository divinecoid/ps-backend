<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_settlement_imports', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('online_store_id')->nullable()->index();
            $table->uuid('marketplace_id')->nullable()->index();
            $table->string('file_type', 20); // settlement | bank
            $table->string('file_name');
            $table->string('file_path')->nullable();
            $table->char('file_hash', 64);
            $table->string('status', 20)->default('queued'); // queued|processing|done|failed
            $table->unsignedInteger('row_count')->default(0);
            $table->unsignedInteger('imported_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('error_count')->default(0);
            $table->json('errors')->nullable();
            $table->json('unmapped_columns')->nullable();
            $table->uuid('user_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['file_hash', 'online_store_id', 'file_type']);
        });

        Schema::create('trx_settlement_entries', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('import_id')->index();
            $table->uuid('online_store_id')->index();
            $table->uuid('marketplace_id')->nullable()->index();
            $table->unsignedInteger('source_row_number');
            $table->char('fingerprint', 64);
            $table->string('order_sn')->index();
            $table->string('payout_ref')->nullable()->index();
            $table->date('order_date')->nullable();
            $table->date('settlement_date')->nullable()->index();

            $table->decimal('gross_amount', 18, 2)->default(0);
            $table->decimal('seller_discount', 18, 2)->default(0);
            $table->decimal('commission_fee', 18, 2)->default(0);
            $table->decimal('service_fee', 18, 2)->default(0);
            $table->decimal('event_fee', 18, 2)->default(0);
            $table->decimal('shipping_fee', 18, 2)->default(0);
            $table->decimal('refund_amount', 18, 2)->default(0);
            $table->decimal('other_fee', 18, 2)->default(0);
            $table->decimal('adjustment_amount', 18, 2)->default(0); // signed

            $table->decimal('reported_net', 18, 2)->nullable();
            $table->decimal('expected_net', 18, 2)->default(0);
            $table->decimal('difference', 18, 2)->nullable(); // reported - expected

            $table->string('status', 20)->index(); // matched|pending|discrepancy|unusual_fee
            $table->json('flags')->nullable();
            $table->json('fee_breakdown')->nullable(); // original column label => amount
            $table->json('raw_payload')->nullable();
            $table->timestamps();

            $table->unique(['online_store_id', 'fingerprint'], 'uq_settlement_store_fingerprint');
        });

        Schema::create('trx_bank_transactions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('import_id')->index();
            $table->unsignedInteger('source_row_number');
            $table->char('fingerprint', 64)->unique();
            $table->date('transaction_date')->index();
            $table->string('description', 500)->nullable();
            $table->string('reference')->nullable();
            $table->decimal('credit_amount', 18, 2);
            $table->uuid('matched_online_store_id')->nullable();
            $table->string('matched_payout_key')->nullable()->index();
            $table->string('match_method', 30)->nullable(); // reference|amount_date
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_bank_transactions');
        Schema::dropIfExists('trx_settlement_entries');
        Schema::dropIfExists('trx_settlement_imports');
    }
};
