<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_manual_outbounds', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('marketplace_id')->nullable()->constrained('mdx_marketplaces')->nullOnDelete();
            $table->foreignUuid('user_id')->constrained('users');
            $table->dateTime('outbound_date');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_manual_outbounds');
    }
};
