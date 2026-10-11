<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mdx_products', function (Blueprint $table) {
            $table->uuid('stock_batch_id')->nullable()->after('series');
            $table->index('stock_batch_id');
        });
    }

    public function down(): void
    {
        Schema::table('mdx_products', function (Blueprint $table) {
            $table->dropIndex(['stock_batch_id']);
            $table->dropColumn('stock_batch_id');
        });
    }
};
