<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            UserSeeder::class,
            SettingSeeder::class,
        ]);

        if (app()->environment(['local', 'testing'])) {
            $this->call([
                CategorySeeder::class,
                ProductSeeder::class,
            ]);
        }
    }
}
