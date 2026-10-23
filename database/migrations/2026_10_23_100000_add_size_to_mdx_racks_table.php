<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Small-warehouse (KECIL) racks are per size, so inbound pieces must land
     * in a rack of the same size. Null = rack is not tied to a size.
     */
    public function up(): void
    {
        Schema::table('mdx_racks', function (Blueprint $table) {
            $table->foreignUuid('size_id')->nullable()->after('color_id')->constrained('mdx_sizes')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::table('mdx_racks', function (Blueprint $table) {
            $table->dropForeign(['size_id']);
            $table->dropColumn('size_id');
        });
    }
};
