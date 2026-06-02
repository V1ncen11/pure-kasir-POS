<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            'Plastik Kemasan',
            'Plastik Kantong',
            'Plastik Wrap',
            'Plastik Klip',
            'Plastik Gelas & Botol',
            'Kotak & Wadah Plastik',
            'Peralatan Rumah Tangga',
            'Lain-lain',
        ];

        foreach ($categories as $name) {
            Category::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'is_active' => true]
            );
        }
    }
}
