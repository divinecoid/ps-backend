<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Widen the enum first so both old and new values are valid while we migrate data.
        DB::statement("ALTER TABLE mdx_warehouses MODIFY type ENUM('BIG','SMALL','BESAR','KECIL') DEFAULT 'SMALL'");

        DB::table('mdx_warehouses')->where('type', 'BIG')->update(['type' => 'BESAR']);
        DB::table('mdx_warehouses')->where('type', 'SMALL')->update(['type' => 'KECIL']);

        // Narrow the enum down to the final values.
        DB::statement("ALTER TABLE mdx_warehouses MODIFY type ENUM('BESAR','KECIL') DEFAULT 'KECIL'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE mdx_warehouses MODIFY type ENUM('BIG','SMALL','BESAR','KECIL') DEFAULT 'KECIL'");

        DB::table('mdx_warehouses')->where('type', 'BESAR')->update(['type' => 'BIG']);
        DB::table('mdx_warehouses')->where('type', 'KECIL')->update(['type' => 'SMALL']);

        DB::statement("ALTER TABLE mdx_warehouses MODIFY type ENUM('BIG','SMALL') DEFAULT 'SMALL'");
    }
};
