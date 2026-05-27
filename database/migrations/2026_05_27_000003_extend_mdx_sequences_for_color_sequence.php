<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mdx_sequences', function (Blueprint $table) {
            $table->foreignUuid('color_id')->nullable()->after('id')->constrained('mdx_colors')->onDelete('restrict');
            $table->string('format')->default('{colorcode}.{sequence}')->after('color_id');
            $table->unsignedInteger('current')->default(0)->after('format');
            $table->unsignedInteger('limit')->default(999)->after('current');
            $table->unique('color_id', 'mdx_sequences_color_id_unique');
        });
    }

    public function down(): void
    {
        Schema::table('mdx_sequences', function (Blueprint $table) {
            $table->dropUnique('mdx_sequences_color_id_unique');
            $table->dropColumn(['color_id', 'format', 'current', 'limit']);
        });
    }
};
