<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Step 1: Add new columns
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->integer('req_qty')->default(0)->after('size_id');
            $table->integer('rec_qty')->default(0)->after('req_qty');
        });

        // Step 2: Migrate data (convert dozen to pieces: dozen * 12 + pieces)
        DB::statement('
            UPDATE trx_request_details 
            SET req_qty = (req_dozen_qty * 12) + req_piece_qty,
                rec_qty = (rec_dozen_qty * 12) + rec_piece_qty
        ');

        // Step 3: Drop old columns
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->dropColumn([
                'req_dozen_qty',
                'req_piece_qty',
                'rec_dozen_qty',
                'rec_piece_qty'
            ]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Step 1: Re-add old columns
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->integer('req_dozen_qty')->default(0)->after('size_id');
            $table->integer('req_piece_qty')->default(0)->after('req_dozen_qty');
            $table->integer('rec_dozen_qty')->default(0)->after('req_piece_qty');
            $table->integer('rec_piece_qty')->default(0)->after('rec_dozen_qty');
        });

        // Step 2: Migrate data back (convert pieces to dozen and remainder)
        DB::statement('
            UPDATE trx_request_details 
            SET req_dozen_qty = FLOOR(req_qty / 12),
                req_piece_qty = MOD(req_qty, 12),
                rec_dozen_qty = FLOOR(rec_qty / 12),
                rec_piece_qty = MOD(rec_qty, 12)
        ');

        // Step 3: Drop new columns
        Schema::table('trx_request_details', function (Blueprint $table) {
            $table->dropColumn(['req_qty', 'rec_qty']);
        });
    }
};
