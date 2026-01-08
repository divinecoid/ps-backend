<?php

namespace Database\Seeders;

use Database\Seeders\MasterData\RoleSeeder;
use Database\Seeders\MasterData\UserSeeder;
use Database\Seeders\ShopeeSeeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            ShopeeSeeder::class,
        ]);
    }
}
