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
            $table->string('shop_cipher')->nullable()->after('auth_code')->comment('Marketplace Shop Cipher (e.g. TikTok Shop Cipher)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdx_online_stores', function (Blueprint $table) {
            $table->dropColumn('shop_cipher');
        });
    }
};

