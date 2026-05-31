<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            if (!Schema::hasColumn('trx_fabric_purchase_request_details', 'quantity')) {
                $table->unsignedInteger('quantity')->after('color_id');
            }
            if (!Schema::hasColumn('trx_fabric_purchase_request_details', 'sequence')) {
                $table->string('sequence')->nullable()->after('quantity');
            }

            if (Schema::hasColumn('trx_fabric_purchase_request_details', 'series_code') || Schema::hasColumn('trx_fabric_purchase_request_details', 'series_sequence') || Schema::hasColumn('trx_fabric_purchase_request_details', 'roll_qty')) {
                $table->dropForeign('tfprd_fpr_id_fk');
                $table->dropUnique('trx_fp_request_details_unique');

                if (Schema::hasColumn('trx_fabric_purchase_request_details', 'series_code')) {
                    $table->dropColumn('series_code');
                }
                if (Schema::hasColumn('trx_fabric_purchase_request_details', 'series_sequence')) {
                    $table->dropColumn('series_sequence');
                }
                if (Schema::hasColumn('trx_fabric_purchase_request_details', 'roll_qty')) {
                    $table->dropColumn('roll_qty');
                }
            }
        });

        if (Schema::hasColumn('trx_fabric_purchase_request_details', 'sequence')) {
            DB::statement('ALTER TABLE `trx_fabric_purchase_request_details` MODIFY COLUMN `sequence` VARCHAR(255) NULL');
        }

        $uniqueExists = count(DB::select(
            'SELECT 1 FROM information_schema.statistics WHERE table_schema = database() AND table_name = ? AND index_name = ?',
            ['trx_fabric_purchase_request_details', 'trx_fp_request_details_unique'],
        )) > 0;

        if (!$uniqueExists) {
            DB::statement('ALTER TABLE `trx_fabric_purchase_request_details` ADD UNIQUE `trx_fp_request_details_unique` (`fabric_purchase_request_id`, `sequence`)');
        }

        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->foreign('fabric_purchase_request_id', 'tfprd_fpr_id_fk')
                ->references('id')
                ->on('trx_fabric_purchase_requests')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->dropForeign('tfprd_fpr_id_fk');
            $table->dropUnique('trx_fp_request_details_unique');
            $table->dropColumn(['quantity', 'sequence']);
            $table->string('series_code', 100)->after('color_id');
            $table->unsignedSmallInteger('series_sequence')->after('series_code');
            $table->unsignedInteger('roll_qty')->after('series_sequence');
            $table->unique([
                'fabric_purchase_request_id',
                'color_id',
                'series_code',
                'series_sequence'
            ], 'trx_fp_request_details_unique');
        });

        Schema::table('trx_fabric_purchase_request_details', function (Blueprint $table) {
            $table->foreign('fabric_purchase_request_id', 'tfprd_fpr_id_fk')
                ->references('id')
                ->on('trx_fabric_purchase_requests')
                ->onDelete('cascade');
        });
    }
};
