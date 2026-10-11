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
        Schema::table('mdx_models', function (Blueprint $table) {
            $table->foreignUuid('cmt_rate_group_id')->nullable()->after('name')
                ->constrained('mdx_cmt_rate_groups')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdx_models', function (Blueprint $table) {
            $table->dropConstrainedForeignId('cmt_rate_group_id');
        });
    }
};
