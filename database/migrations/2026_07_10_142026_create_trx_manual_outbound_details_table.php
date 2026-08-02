<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_manual_outbound_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('manual_outbound_id')->constrained('trx_manual_outbounds')->cascadeOnDelete();
            $table->foreignUuid('product_id')->nullable()->constrained('mdx_products')->nullOnDelete();
            $table->string('barcode'); // Simpan barcode sebelum product di-soft-delete
            $table->foreignUuid('model_id')->nullable()->constrained('mdx_models')->nullOnDelete();
            $table->foreignUuid('color_id')->nullable()->constrained('mdx_colors')->nullOnDelete();
            $table->foreignUuid('size_id')->nullable()->constrained('mdx_sizes')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_manual_outbound_details');
    }
};
