<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_fabric_returns', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // SUPPLIER = retur ke pabrik (stok kain berkurang)
            // CMT      = retur dari CMT ke Gudang Kain (stok kain bertambah)
            $table->enum('type', ['SUPPLIER', 'CMT']);
            $table->foreignUuid('cloth_id')->constrained('mdx_clothes')->onDelete('restrict');
            $table->foreignUuid('cmt_id')->nullable()->constrained('mdx_cmts')->onDelete('restrict');
            $table->unsignedInteger('quantity');
            $table->string('reason')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUuid('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_returns');
    }
};
