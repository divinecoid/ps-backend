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
        Schema::create('trx_fabric_series', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('configuration_id')->constrained('mdx_configurations')->onDelete('restrict');
            $table->foreignUuid('color_id')->constrained('mdx_colors')->onDelete('restrict');
            $table->string('series_code', 100);
            $table->unsignedSmallInteger('series_sequence');
            $table->unsignedInteger('roll_available')->default(0);
            $table->timestamps();

            $table->unique([
                'configuration_id',
                'color_id',
                'series_code',
                'series_sequence'
            ], 'trx_fabric_series_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_series');
    }
};
