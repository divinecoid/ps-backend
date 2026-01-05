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
        Schema::create('mdx_online_stores', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('store_code')->unique();
            $table->string('store_name');
            $table->foreignUuid('marketplace_id')->constrained('mdx_marketplaces')->onDelete('restrict');
            $table->string('api_key')->nullable();
            $table->string('client_id')->nullable();
            $table->string('client_secret')->nullable();
            $table->string('store_url');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_online_stores');
    }
};
