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
        Schema::create('trx_orders', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('awb_code')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('prepared_at')->nullable();
            $table->integer('prepare_duration')->nullable();
            $table->timestamp('readytoship_at')->nullable();
            $table->string('readytoship_marketplace')->nullable();
            $table->foreignUuid('online_store_id')->constrained('mdx_online_stores')->onDelete('restrict');
            $table->integer('item_count');
            $table->integer('unique_item_count');
            $table->enum('status', [
                'pending',
                'read',
                'prepared',
                'ready_to_ship',
                'shipped',
                'delivered',
                'cancelled',
                'returned'
            ]);
            $table->decimal('total_weight', 18, 2)->nullable();
            $table->decimal('total_price', 18, 2);
            $table->decimal('total_shipping', 18, 2);
            $table->decimal('total_amount', 18, 2);
            $table->foreignUuid('preparist_user_id')->constrained('users')->onDelete('restrict');
            $table->string('customer_name');
            $table->string('customer_phone')->nullable();
            $table->string('customer_address')->nullable();
            // $table->boolean('checked')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('trx_orders');
    }
};
