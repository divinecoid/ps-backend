<?php

namespace Database\Seeders\MasterData;

use App\Models\MasterData\Role;
use App\Models\MasterData\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;


class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::create([
            'username' => 'superadmin',
            'name' => 'Administrator',
            'email' => 'administrator@example.com',
            'password' => Hash::make('password'),
        ]);
        $adminRole = Role::where('name', 'admin')->first();
        if ($adminRole) {
            $admin->roles()->attach($adminRole);
        }

        $preparist = User::create([
            'username' => 'preparist',
            'name' => 'Preparist',
            'email' => 'preparist@example.com',
            'password' => Hash::make('password'),
        ]);
        $preparistRole = Role::where('name', 'preparist')->first();
        if ($preparistRole) {
            $preparist->roles()->attach($preparistRole);
        }

        $checker = User::create([
            'username' => 'checker',
            'name' => 'Checker',
            'email' => 'checker@example.com',
            'password' => Hash::make('password'),
        ]);

        $checkerRole = Role::where('name', 'checker')->first();
        if ($checkerRole) {
            $checker->roles()->attach($checkerRole);
        }


        
    }
}