<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            [
                'name' => 'Admin',
                'description' => 'Administrator role with full access to all features'
            ],
            [
                'name' => 'Preparist',
                'description' => 'Preparist role that prepares something'
            ],
            [
                'name' => 'Checker',
                'description' => 'Checker role that checks products (optional)'
            ],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}