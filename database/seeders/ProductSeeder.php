<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Seeder;

class ProductSeeder extends Seeder
{
    public function run(): void
    {
        $kantong = Category::where('slug', 'plastik-kantong')->first();
        $kemasan = Category::where('slug', 'plastik-kemasan')->first();
        $wrap    = Category::where('slug', 'plastik-wrap')->first();
        $gelas   = Category::where('slug', 'plastik-gelas-botol')->first();
        $kotak   = Category::where('slug', 'kotak-wadah-plastik')->first();

        $products = [
            // Plastik Kantong
            ['category_id' => $kantong?->id, 'name' => 'Kantong Plastik HD 30x40 (1kg)', 'sku' => 'KP-HD-3040', 'unit' => 'kg', 'buy_price' => 15000, 'sell_price' => 20000, 'stock' => 150, 'min_stock' => 20],
            ['category_id' => $kantong?->id, 'name' => 'Kantong Plastik HD 40x60 (1kg)', 'sku' => 'KP-HD-4060', 'unit' => 'kg', 'buy_price' => 18000, 'sell_price' => 25000, 'stock' => 120, 'min_stock' => 20],
            ['category_id' => $kantong?->id, 'name' => 'Kantong Plastik Kresek Putih Isi 100', 'sku' => 'KP-KW-100', 'unit' => 'pack', 'buy_price' => 3500, 'sell_price' => 5000, 'stock' => 200, 'min_stock' => 30],
            ['category_id' => $kantong?->id, 'name' => 'Kantong Plastik Kresek Hitam Isi 100', 'sku' => 'KP-KH-100', 'unit' => 'pack', 'buy_price' => 4000, 'sell_price' => 6000, 'stock' => 180, 'min_stock' => 30],
            // Plastik Kemasan
            ['category_id' => $kemasan?->id, 'name' => 'Plastik PP Bening 25x35 Isi 100', 'sku' => 'PP-2535-100', 'unit' => 'pack', 'buy_price' => 5000, 'sell_price' => 7500, 'stock' => 100, 'min_stock' => 15],
            ['category_id' => $kemasan?->id, 'name' => 'Plastik PP Bening 30x40 Isi 100', 'sku' => 'PP-3040-100', 'unit' => 'pack', 'buy_price' => 6500, 'sell_price' => 9000, 'stock' => 90, 'min_stock' => 15],
            ['category_id' => $kemasan?->id, 'name' => 'Plastik Zipper Lock 10x15 Isi 100', 'sku' => 'ZL-1015-100', 'unit' => 'pack', 'buy_price' => 8000, 'sell_price' => 12000, 'stock' => 80, 'min_stock' => 10],
            // Plastik Wrap
            ['category_id' => $wrap?->id, 'name' => 'Plastik Wrap Makanan 30cm x 100m', 'sku' => 'PW-30-100M', 'unit' => 'roll', 'buy_price' => 18000, 'sell_price' => 25000, 'stock' => 50, 'min_stock' => 8],
            ['category_id' => $wrap?->id, 'name' => 'Plastik Wrap Makanan 45cm x 100m', 'sku' => 'PW-45-100M', 'unit' => 'roll', 'buy_price' => 25000, 'sell_price' => 35000, 'stock' => 40, 'min_stock' => 5],
            // Gelas & Botol
            ['category_id' => $gelas?->id, 'name' => 'Gelas Plastik Bening 220ml Isi 50', 'sku' => 'GP-220-50', 'unit' => 'pack', 'buy_price' => 10000, 'sell_price' => 15000, 'stock' => 60, 'min_stock' => 10],
            ['category_id' => $gelas?->id, 'name' => 'Tutup Gelas Plastik Bening Isi 50', 'sku' => 'TGP-50', 'unit' => 'pack', 'buy_price' => 8000, 'sell_price' => 12000, 'stock' => 60, 'min_stock' => 10],
            // Kotak Wadah
            ['category_id' => $kotak?->id, 'name' => 'Kotak Makan Plastik 1000ml', 'sku' => 'KM-1000', 'unit' => 'pcs', 'buy_price' => 3000, 'sell_price' => 5000, 'stock' => 120, 'min_stock' => 20],
            ['category_id' => $kotak?->id, 'name' => 'Wadah Serbaguna Plastik 5L', 'sku' => 'WS-5L', 'unit' => 'pcs', 'buy_price' => 15000, 'sell_price' => 22000, 'stock' => 30, 'min_stock' => 5],
            ['category_id' => $kotak?->id, 'name' => 'Toples Bulat Plastik 1L', 'sku' => 'TB-1L', 'unit' => 'pcs', 'buy_price' => 8000, 'sell_price' => 13000, 'stock' => 45, 'min_stock' => 8],
        ];

        foreach ($products as $data) {
            Product::firstOrCreate(
                ['sku' => $data['sku']],
                array_merge($data, ['is_active' => true])
            );
        }
    }
}
