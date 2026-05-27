<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_fabric_purchase_requests', function (Blueprint $table) {
            if (!Schema::hasColumn('trx_fabric_purchase_requests', 'factory_id')) {
                $table->foreignUuid('factory_id')->nullable()->after('id');
            }
            if (!Schema::hasColumn('trx_fabric_purchase_requests', 'gram')) {
                $table->string('gram')->nullable()->after('factory_id');
            }
            if (!Schema::hasColumn('trx_fabric_purchase_requests', 'ukuran')) {
                $table->unsignedInteger('ukuran')->nullable()->after('gram');
            }
            if (Schema::hasColumn('trx_fabric_purchase_requests', 'supplier_name') || Schema::hasColumn('trx_fabric_purchase_requests', 'configuration_name')) {
                $table->dropColumn(['supplier_name', 'configuration_name']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('trx_fabric_purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['factory_id']);
            $table->dropColumn(['factory_id', 'gram', 'ukuran']);
            $table->string('supplier_name')->after('id');
            $table->string('configuration_name')->after('supplier_name');
        });
    }
};
