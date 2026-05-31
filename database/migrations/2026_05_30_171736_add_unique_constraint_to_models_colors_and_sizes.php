<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('models_colors', function (Blueprint $table) {
            $table->unique(
                ['model_id', 'color_id'],
                'models_colors_model_color_unique'
            );
        });

        Schema::table('models_sizes', function (Blueprint $table) {
            $table->unique(
                ['model_id', 'size_id'],
                'models_sizes_model_size_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::table('models_colors', function (Blueprint $table) {
            $table->dropUnique('models_colors_model_color_unique');
        });

        Schema::table('models_sizes', function (Blueprint $table) {
            $table->dropUnique('models_sizes_model_size_unique');
        });
    }
};