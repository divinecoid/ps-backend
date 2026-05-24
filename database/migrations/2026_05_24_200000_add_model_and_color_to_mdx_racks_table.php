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
        Schema::table('mdx_racks', function (Blueprint $table) {
            $table->foreignUuid('model_id')->nullable()->after('warehouse_id')->constrained('mdx_models')->onDelete('restrict');
            $table->foreignUuid('color_id')->nullable()->after('model_id')->constrained('mdx_colors')->onDelete('restrict');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdx_racks', function (Blueprint $table) {
            $table->dropForeign(['mdx_racks_model_id_foreign']);
            $table->dropColumn('model_id');
            $table->dropForeign(['mdx_racks_color_id_foreign']);
            $table->dropColumn('color_id');
        });
    }
};
