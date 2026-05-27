<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $uniqueExists = count(DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = database() AND table_name = ? AND index_name = ?',
            ['trx_fabric_purchase_request_details', 'trx_fp_request_details_unique'],
        )) > 0;

        if ($uniqueExists) {
            Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
                $table->dropUnique('trx_fp_request_details_unique');
            });
        }

        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->unique([
                'fabric_purchase_request_id',
                'sequence',
            ], 'trx_fp_request_details_unique');
        });
    }

    public function down(): void
    {
        $uniqueExists = count(DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = database() AND table_name = ? AND index_name = ?',
            ['trx_fabric_purchase_request_details', 'trx_fp_request_details_unique'],
        )) > 0;

        if ($uniqueExists) {
            Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
                $table->dropUnique('trx_fp_request_details_unique');
            });
        }

        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->unique([
                'fabric_purchase_request_id',
                'color_id',
            ], 'trx_fp_request_details_unique');
        });
    }
};
