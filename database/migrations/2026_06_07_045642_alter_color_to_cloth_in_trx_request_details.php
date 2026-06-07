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
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->dropForeign(['color_id']);
            $table->dropColumn('color_id');
            $table->foreignUuid('cloth_id')->after('model_id')->nullable()->constrained('mdx_clothes')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->dropForeign(['cloth_id']);
            $table->dropColumn('cloth_id');
            $table->foreignUuid('color_id')->nullable()->constrained('mdx_colors')->onDelete('restrict');
        });
    }
};
