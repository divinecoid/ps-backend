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
        Schema::create('mdx_shipping_logistics', function (Illuminate\Database\Schema\Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('marketplace_id');
            $table->string('logistic_name');
            $table->string('logistic_id'); // ID unik dari marketplace (misal: 10001)
            $table->string('logistic_type')->nullable(); // misal: Regular, Next Day, etc
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('marketplace_id')->references('id')->on('mdx_marketplaces')->onDelete('cascade');
            $table->unique(['marketplace_id', 'logistic_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('shipping_logistics');
    }
};
