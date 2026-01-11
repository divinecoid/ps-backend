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
        Schema::table('mdx_online_stores', function (Blueprint $table) {
            $table->text('auth_code')->nullable()->after('shop_id')->comment('Temporary Auth Code from Marketplace');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdx_online_stores', function (Blueprint $table) {
            $table->dropColumn('auth_code');
        });
    }
};
