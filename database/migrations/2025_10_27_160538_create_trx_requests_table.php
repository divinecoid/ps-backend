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
        Schema::create('trx_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid(('cmt_id'))->constrained('mdx_cmts')->onDelete('restrict');
            $table->enum('status',['OPEN','CLOSED'])->default('OPEN');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_requests');
    }
};
