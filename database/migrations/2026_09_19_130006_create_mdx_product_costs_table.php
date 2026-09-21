<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mdx_product_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('product_id')->unique();
            $table->decimal('fabric_cost', 15, 2)->default(0);
            $table->decimal('shipping_cost', 15, 2)->default(0);
            $table->decimal('cmt_fee', 15, 2)->default(0);
            $table->decimal('total_hpp', 15, 2)->default(0);
            $table->boolean('is_estimated')->default(false);
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->foreign('product_id')->references('id')->on('mdx_products')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mdx_product_costs');
    }
};
