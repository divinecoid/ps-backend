<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE trx_orders MODIFY COLUMN status ENUM(
            'pending',
            'read',
            'prepared',
            'ready_to_ship',
            'retry_ship',
            'ready_to_pickup',
            'shipped',
            'delivered',
            'cancelled',
            'returned'
        )");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE trx_orders MODIFY COLUMN status ENUM(
            'pending',
            'read',
            'prepared',
            'ready_to_ship',
            'ready_to_pickup',
            'shipped',
            'delivered',
            'cancelled',
            'returned'
        )");
    }
};
