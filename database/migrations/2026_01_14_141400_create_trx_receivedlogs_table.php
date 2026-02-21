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
        Schema::create('trx_receivedlogs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_id')->constrained('trx_requests')->onDelete('cascade');
            $table->foreignUuid('warehouse_id')->constrained('mdx_warehouses')->onDelete('restrict');
            $table->foreignUuid('user_id')->constrained('users')->onDelete('restrict');
            $table->timestamp('received_date');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_receivedlogs');
    }
};
