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
        Schema::table('trx_fabric_purchase_requests', function (Blueprint $table) {
            // Drop foreign keys
            $table->dropForeign(['supplier_id']);
            $table->dropForeign(['configuration_id']);
            
            // Drop old columns
            $table->dropColumn(['supplier_id', 'configuration_id']);
            
            // Add new columns for manual entry
            $table->string('supplier_name')->after('id');
            $table->string('configuration_name')->after('supplier_name');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('trx_fabric_purchase_requests', function (Blueprint $table) {
            $table->dropColumn(['supplier_name', 'configuration_name']);
            $table->uuid('supplier_id')->after('id');
            $table->uuid('configuration_id')->after('supplier_id');
            $table->foreign('supplier_id')->references('id')->on('mdx_factories')->onDelete('restrict');
            $table->foreign('configuration_id')->references('id')->on('mdx_configurations')->onDelete('restrict');
        });
    }
};
