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
            $table->string('redirect_uri')->nullable();
            $table->text('access_token')->nullable()->after('redirect_uri');
            $table->text('refresh_token')->nullable()->after('access_token');
            $table->timestamp('expires_at')->nullable()->after('refresh_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('mdx_online_stores', function (Blueprint $table) {
            $table->dropColumn(['redirect_uri', 'access_token', 'refresh_token', 'expires_at']);
        });
    }
};
