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
        // Schema::create('trx_received_logs', function (Blueprint $table) {
        //     $table->id();
        //     $table->foreignUuid('request_id')->constrained('trx_requests')->onDelete('restrict');
        //     $table->bigInteger('quantity');
        //     $table->timestamp('received_date');
        //     $table->softDeletes();
        //     $table->timestamps();
        // });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Schema::dropIfExists('trx_received_logs');
    }
};
