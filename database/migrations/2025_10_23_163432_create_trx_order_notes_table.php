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
        Schema::create('trx_order_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('trx_orders')->onDelete('restrict');
            $table->foreignId('author_user_id')->constrained('users')->onDelete('restrict');
            $table->text('notes');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_order_notes');
    }
};
