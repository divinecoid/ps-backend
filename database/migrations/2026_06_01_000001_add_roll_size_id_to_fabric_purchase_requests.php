<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('trx_fabric_purchase_requests', function (Blueprint $table) {
            $table->uuid('roll_size_id')->nullable()->after('ukuran');
            $table->foreign('roll_size_id')
                ->references('id')
                ->on('mdx_roll_sizes')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::table('trx_fabric_purchase_requests', function (Blueprint $table) {
            $table->dropForeign(['roll_size_id']);
            $table->dropColumn('roll_size_id');
        });
    }
};
