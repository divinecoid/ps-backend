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
        Schema::create('trx_fabric_cuttings', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid(('fabric_id'))->constrained('mdx_clothes')->onDelete('cascade');
            $table->integer('quantity')->default(0);
            $table->string('serial_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_cuttings');
    }
};
