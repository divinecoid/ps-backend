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
        Schema::table('acm_permissions', function (Blueprint $table) {
            $table->boolean('can_force_delete')->default(false)->after('can_delete');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('acm_permissions', function (Blueprint $table) {
            $table->dropColumn('can_force_delete');
        });
    }
};
