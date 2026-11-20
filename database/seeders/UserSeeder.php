<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.initial_admin.email');
        $password = config('app.initial_admin.password');

        if (!$email || !$password) {
            $this->command?->warn('Admin awal tidak dibuat. Atur INITIAL_ADMIN_EMAIL dan INITIAL_ADMIN_PASSWORD untuk bootstrap akun admin.');
            return;
        }

        if (strlen($password) < 12) {
            throw new \RuntimeException('INITIAL_ADMIN_PASSWORD harus memiliki minimal 12 karakter.');
        }

        $admin = User::where('email', $email)->first();
        if ($admin && $admin->role !== 'admin') {
            throw new \RuntimeException('INITIAL_ADMIN_EMAIL sudah digunakan akun non-admin. Gunakan email admin yang berbeda.');
        }

        if (!$admin) {
            $admin = User::create([
                'name' => 'Administrator',
                'email' => $email,
                'password' => Hash::make($password),
                'role' => 'admin',
                'is_active' => true,
            ]);
            $admin->forceFill(['email_verified_at' => now()])->save();
        }

        $admin->assignRole('admin');
    }
}
