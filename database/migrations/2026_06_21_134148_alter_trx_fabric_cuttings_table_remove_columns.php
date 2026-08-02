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
        Schema::table('trx_fabric_cuttings', function (Blueprint $table) {
            $table->dropForeign(['fabric_id']);
            $table->dropColumn('fabric_id');
            $table->dropColumn('quantity');
        });
    }

    public function down(): void
    {
        Schema::table('trx_fabric_cuttings', function (Blueprint $table) {
            $table->foreignUuid('fabric_id')->nullable()->constrained('mdx_clothes')->onDelete('cascade');
            $table->integer('quantity')->default(0);
        });
    }
};
