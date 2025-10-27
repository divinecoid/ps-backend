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
            $table->id();
            $table->foreignId('inventories_id')->constrained('mdx_inventories')->onDelete('restrict');
            $table->string('format');
            $table->bigInteger('start');
            $table->bigInteger('end');
            $table->bigInteger('retrieved_qty');
            $table->timestamps('request_date');
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
