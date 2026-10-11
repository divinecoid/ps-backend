<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('trx_other_costs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->enum('category', ['shipping_fabric', 'operational', 'other'])->default('other');
            $table->string('reference_type')->nullable();
            $table->uuid('reference_id')->nullable();
            $table->decimal('amount', 15, 2);
            $table->text('notes')->nullable();
            $table->date('date');
            $table->timestamps();

            $table->index(['reference_type', 'reference_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('trx_other_costs');
    }
};
