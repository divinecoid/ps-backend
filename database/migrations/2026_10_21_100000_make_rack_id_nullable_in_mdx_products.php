<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Old-stock products that sit in the big warehouse carry no rack.
    public function up(): void
    {
        Schema::table('mdx_products', function (Blueprint $table) {
            $table->foreignUuid('rack_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('mdx_products', function (Blueprint $table) {
            $table->foreignUuid('rack_id')->nullable(false)->change();
        });
    }
};
