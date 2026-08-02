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
        $admin = User::firstOrCreate(
            ['username' => 'superadmin'],
            [
                'name' => 'Administrator',
                'email' => 'administrator@example.com',
                'password' => Hash::make('password'),
            ]
        );
        $adminRole = Role::where('name', 'Admin')->first();
        if ($adminRole && !$admin->roles()->where('role_id', $adminRole->id)->exists()) {
            $admin->roles()->attach($adminRole);
        }

        $preparist = User::firstOrCreate(
            ['username' => 'preparist'],
            [
                'name' => 'Preparist',
                'email' => 'preparist@example.com',
                'password' => Hash::make('password'),
            ]
        );
        $preparistRole = Role::where('name', 'Preparist')->first();
        if ($preparistRole && !$preparist->roles()->where('role_id', $preparistRole->id)->exists()) {
            $preparist->roles()->attach($preparistRole);
        }

        $checker = User::firstOrCreate(
            ['username' => 'checker'],
            [
                'name' => 'Checker',
                'email' => 'checker@example.com',
                'password' => Hash::make('password'),
            ]
        );

        $checkerRole = Role::where('name', 'Checker')->first();
        if ($checkerRole && !$checker->roles()->where('role_id', $checkerRole->id)->exists()) {
            $checker->roles()->attach($checkerRole);
        }


        
    }
}