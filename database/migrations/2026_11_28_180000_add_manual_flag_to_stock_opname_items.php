<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Marks the items of a count whose quantity was typed instead of scanned. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_stock_opname_items', function (Blueprint $table) {
            $table->boolean('is_manual')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('trx_stock_opname_items', function (Blueprint $table) {
            $table->dropColumn('is_manual');
        });
    }
};
