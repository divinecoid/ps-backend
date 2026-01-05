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

            // Rename expires_at → access_token_expires_at
            $table->renameColumn('expires_at', 'access_token_expires_at');

            // Add refresh_token_expires_at
            $table->dateTime('refresh_token_expires_at')->nullable()->after('access_token_expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('online_stores', function (Blueprint $table) {
            // Revert back to original name
            $table->renameColumn('access_token_expires_at', 'expires_at');

            // Drop the refresh column
            $table->dropColumn('refresh_token_expires_at');
        });
    }
};
