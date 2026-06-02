<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@toko.com'],
            [
                'name'     => 'Administrator',
                'password' => Hash::make('password'),
                'role'     => 'admin',
                'is_active' => true,
            ]
        );
        $admin->assignRole('admin');

        $kasir = User::firstOrCreate(
            ['email' => 'kasir@toko.com'],
            [
                'name'     => 'Kasir Demo',
                'password' => Hash::make('password'),
                'role'     => 'kasir',
                'is_active' => true,
            ]
        );
        $kasir->assignRole('kasir');
    }
}
