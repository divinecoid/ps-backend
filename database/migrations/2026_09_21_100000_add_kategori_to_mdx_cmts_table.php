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
        Schema::table('mdx_cmts', function (Blueprint $table) {
            $table->enum('kategori', ['DALAM_KOTA', 'LUAR_KOTA'])->default('DALAM_KOTA')->after('address');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdx_cmts', function (Blueprint $table) {
            $table->dropColumn('kategori');
        });
    }
};
