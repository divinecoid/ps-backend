<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE trx_orders MODIFY COLUMN status ENUM(
            'pending',
            'read',
            'ready_to_ship',
            'retry_ship',
            'ready_to_pickup',
            'wait_courier',
            'shipped',
            'completed',
            'cancelled',
            'returned'
        )");
    }

    public function down(): void
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
};
