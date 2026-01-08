<?php

namespace Database\Seeders;

use Database\Seeders\MasterData\CMTSeeder;
use Database\Seeders\MasterData\ColorSeeder;
use Database\Seeders\MasterData\RoleSeeder;
use Database\Seeders\MasterData\SizeSeeder;
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
            CMTSeeder::class,
            ColorSeeder::class,
            RoleSeeder::class,
            UserSeeder::class,
            ShopeeSeeder::class,
        ]);
    }
}
