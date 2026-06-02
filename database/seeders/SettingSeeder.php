<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'store_name'       => 'Toko Plastik Sejahtera',
            'store_address'    => 'Jl. Raya Contoh No. 123, Kota',
            'store_phone'      => '08123456789',
            'store_email'      => '',
            'store_logo'       => '',
            'tax_percentage'   => '0',
            'receipt_header'   => 'Terima kasih telah berbelanja!',
            'receipt_footer'   => 'Barang yang sudah dibeli tidak dapat dikembalikan.',
            'currency_symbol'  => 'Rp',
            'currency_locale'  => 'id-ID',
            'low_stock_alert'  => '1',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
