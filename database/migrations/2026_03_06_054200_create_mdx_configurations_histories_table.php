<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('mdx_configurations_histories', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('configuration_id');
            $table->text('old_value')->nullable();
            $table->text('new_value')->nullable();
            $table->uuid('changed_by')->nullable();
            $table->timestamp('changed_at');

            $table->foreign('configuration_id')->references('id')->on('mdx_configurations')->cascadeOnDelete();
            $table->foreign('changed_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('mdx_configurations_histories');
    }
};
