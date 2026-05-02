<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('trx_orders', function (Blueprint $table) {
            $table->boolean('is_need_checker')->default(false);
            $table->foreignUuid('checked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_approved')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_orders', function (Blueprint $table) {
            $table->dropForeign(['checked_by']);
            $table->dropColumn(['is_need_checker', 'checked_by', 'is_approved']);
        });
    }
};
