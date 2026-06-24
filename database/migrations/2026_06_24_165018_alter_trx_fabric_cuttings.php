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
        Schema::table('trx_fabric_cuttings', function (Blueprint $table) {
            $table->dropForeign(['fabric_id']);
            $table->dropColumn(['fabric_id', 'quantity']);
        });
        Schema::create('trx_fabric_cutting_fabrics', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('fabric_cutting_id');
            $table->uuid('fabric_id');
            $table->integer('quantity');
            $table->timestamps();
            $table->foreign('fabric_cutting_id')->references('id')->on('trx_fabric_cuttings')->cascadeOnDelete();
            $table->foreign('fabric_id')->references('id')->on('mdx_clothes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_fabric_cutting_fabrics');

        Schema::table('trx_fabric_cuttings', function (Blueprint $table) {
            $table->uuid('fabric_id')->nullable()->after('id');
            $table->integer('quantity')->default(0)->after('fabric_id');
            $table->foreign('fabric_id')->references('id')->on('mdx_clothes');
        });
    }
};
