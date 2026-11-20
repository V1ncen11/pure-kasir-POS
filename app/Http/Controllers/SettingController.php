<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Setting;
use App\Support\XlsxWorkbook;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class SettingController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Settings/Index', [
            'settings' => Setting::getAllAsArray(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'store_name'     => 'required|string|max:100',
            'store_address'  => 'nullable|string|max:300',
            'store_phone'    => 'nullable|string|max:30',
            'store_email'    => 'nullable|email|max:100',
            'instagram'      => 'nullable|string|max:120',
            'website'        => 'nullable|url:http,https|max:255',
            'tax_percentage' => 'nullable|numeric|min:0|max:100',
            'receipt_header' => 'nullable|string|max:200',
            'receipt_footer' => 'nullable|string|max:200',
            'low_stock_alert'=> 'boolean',
            'receipt_paper_width' => 'required|string|in:58mm,80mm',
        ]);

        foreach ($data as $key => $value) {
            Setting::set($key, $value ?? '');
        }

        ActivityLog::log('Edit Pengaturan', 'Mengubah pengaturan toko.');

        return back()->with('success', 'Pengaturan berhasil disimpan.');
    }

    public function uploadLogo(Request $request): RedirectResponse
    {
        $request->validate(['logo' => 'required|image|max:2048']);

        $old = Setting::get('store_logo');
        if ($old) Storage::disk('public')->delete($old);

        $path = $request->file('logo')->store('settings', 'public');
        Setting::set('store_logo', $path);

        ActivityLog::log('Upload Logo', 'Mengganti logo toko.');

        return back()->with('success', 'Logo berhasil diupload.');
    }

    public function exportData(): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $filePath = null;

        try {
            $settings = Setting::getAllAsArray();
            $storeName = $settings['store_name'] ?? 'Kasir POS';
            $exportedAt = now();
            $today = Carbon::today();
            $todayString = $today->toDateString();
            $subtitle = $storeName . '  |  Diekspor ' . $exportedAt->format('d/m/Y H:i');

            $todayTransactions = DB::table('transactions')->whereDate('created_at', $todayString);
            $todayCompleted = (clone $todayTransactions)->where('status', 'completed');
            $todayPurchases = DB::table('purchases')->whereDate('date', $todayString);
            $lowStockCount = DB::table('products')->where('is_active', true)->whereColumn('stock', '<=', 'min_stock')->count();
            $outOfStockCount = DB::table('products')->where('is_active', true)->where('stock', '<=', 0)->count();
            $todayPaymentTotals = (clone $todayCompleted)->select('payment_method', DB::raw('SUM(total) as amount'))->groupBy('payment_method')->pluck('amount', 'payment_method');
            $todayTopProduct = DB::table('transaction_items as i')->join('transactions as t', 't.id', '=', 'i.transaction_id')
                ->where('t.status', 'completed')->whereDate('t.created_at', $todayString)
                ->select('i.product_name', DB::raw('SUM(i.qty) as qty'))->groupBy('i.product_name')->orderByDesc('qty')->first();

            $todayRevenue = (clone $todayCompleted)->sum('total');
            $todayTransactionCount = (clone $todayCompleted)->count();
            $todayItemsSold = DB::table('transaction_items as i')->join('transactions as t', 't.id', '=', 'i.transaction_id')
                ->where('t.status', 'completed')->whereDate('t.created_at', $todayString)->sum('i.qty');
            $todaySummaryRows = [
                ['Tanggal Laporan', ['value' => $today, 'type' => 'date']],
                ['Transaksi selesai hari ini', ['value' => $todayTransactionCount, 'type' => 'number']],
                ['Transaksi dibatalkan hari ini', ['value' => (clone $todayTransactions)->where('status', 'voided')->count(), 'type' => 'number']],
                ['Omzet hari ini', ['value' => $todayRevenue, 'type' => 'currency']],
                ['Diskon transaksi selesai', ['value' => (clone $todayCompleted)->sum('discount'), 'type' => 'currency']],
                ['Rata-rata nilai transaksi', ['value' => $todayTransactionCount ? $todayRevenue / $todayTransactionCount : 0, 'type' => 'currency']],
                ['Jumlah item terjual', ['value' => $todayItemsSold, 'type' => 'number']],
                ['Produk terlaris hari ini', $todayTopProduct ? $todayTopProduct->product_name . ' (' . $todayTopProduct->qty . ' terjual)' : 'Belum ada transaksi selesai'],
                ['Pembelian stok hari ini', ['value' => (clone $todayPurchases)->count(), 'type' => 'number']],
                ['Nilai pembelian stok hari ini', ['value' => (clone $todayPurchases)->sum('total_amount'), 'type' => 'currency']],
                ['Produk aktif stok menipis', ['value' => $lowStockCount, 'type' => 'number']],
                ['Produk aktif stok habis', ['value' => $outOfStockCount, 'type' => 'number']],
                ['Omzet tunai', ['value' => $todayPaymentTotals['cash'] ?? 0, 'type' => 'currency']],
                ['Omzet transfer', ['value' => $todayPaymentTotals['transfer'] ?? 0, 'type' => 'currency']],
                ['Omzet QRIS', ['value' => $todayPaymentTotals['qris'] ?? 0, 'type' => 'currency']],
            ];

            $completedTransactions = DB::table('transactions')->where('status', 'completed');
            $summaryRows = [
                ['Nama Toko', $storeName],
                ['Waktu Ekspor', ['value' => $exportedAt, 'type' => 'date']],
                ['Jumlah Produk', ['value' => DB::table('products')->count(), 'type' => 'number']],
                ['Jumlah Pelanggan', ['value' => DB::table('customers')->count(), 'type' => 'number']],
                ['Jumlah Supplier', ['value' => DB::table('suppliers')->count(), 'type' => 'number']],
                ['Jumlah Transaksi', ['value' => DB::table('transactions')->count(), 'type' => 'number']],
                ['Transaksi Selesai', ['value' => (clone $completedTransactions)->count(), 'type' => 'number']],
                ['Omzet Transaksi Selesai', ['value' => (clone $completedTransactions)->sum('total'), 'type' => 'currency']],
                ['Total Pembelian Stok', ['value' => DB::table('purchases')->sum('total_amount'), 'type' => 'currency']],
            ];

            $sheets = [
                [
                    'name' => 'Ringkasan Hari Ini',
                    'title' => 'RINGKASAN OPERASIONAL HARI INI',
                    'subtitle' => $storeName . '  |  ' . $today->format('d/m/Y') . '  |  Diekspor ' . $exportedAt->format('d/m/Y H:i'),
                    'columns' => [
                        ['header' => 'Informasi', 'width' => 38, 'type' => 'text'],
                        ['header' => 'Nilai', 'width' => 34, 'type' => 'text'],
                    ],
                    'rows' => static function () use ($todaySummaryRows): \Generator {
                        yield from $todaySummaryRows;
                    },
                ],
                [
                    'name' => 'Ringkasan Umum',
                    'title' => 'RINGKASAN DATA TOKO',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'Informasi', 'width' => 34, 'type' => 'text'],
                        ['header' => 'Nilai', 'width' => 30, 'type' => 'text'],
                    ],
                    'rows' => static function () use ($summaryRows): \Generator {
                        yield from $summaryRows;
                    },
                ],
                [
                    'name' => 'Produk',
                    'title' => 'DAFTAR PRODUK',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'ID', 'width' => 10, 'type' => 'number'],
                        ['header' => 'SKU', 'width' => 18],
                        ['header' => 'Barcode', 'width' => 20],
                        ['header' => 'Nama Produk', 'width' => 32],
                        ['header' => 'Kategori', 'width' => 22],
                        ['header' => 'Satuan', 'width' => 14],
                        ['header' => 'Harga Beli (Rp)', 'width' => 20, 'type' => 'currency'],
                        ['header' => 'Harga Jual (Rp)', 'width' => 20, 'type' => 'currency'],
                        ['header' => 'Stok', 'width' => 12, 'type' => 'number'],
                        ['header' => 'Stok Minimum', 'width' => 16, 'type' => 'number'],
                        ['header' => 'Status', 'width' => 14],
                        ['header' => 'Deskripsi', 'width' => 36],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                            ->select('p.id', 'p.sku', 'p.barcode', 'p.name', 'c.name as category_name', 'p.unit', 'p.buy_price', 'p.sell_price', 'p.stock', 'p.min_stock', 'p.is_active', 'p.description')
                            ->orderBy('p.name')->cursor() as $product) {
                            yield [$product->id, $product->sku, $product->barcode, $product->name, $product->category_name, $product->unit, $product->buy_price, $product->sell_price, $product->stock, $product->min_stock, $product->is_active ? 'Aktif' : 'Nonaktif', $product->description];
                        }
                    },
                ],
                [
                    'name' => 'Kategori',
                    'title' => 'DAFTAR KATEGORI',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'ID', 'width' => 10, 'type' => 'number'],
                        ['header' => 'Nama Kategori', 'width' => 28],
                        ['header' => 'Slug', 'width' => 26],
                        ['header' => 'Status', 'width' => 14],
                        ['header' => 'Deskripsi', 'width' => 38],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('categories')->select('id', 'name', 'slug', 'is_active', 'description')->orderBy('name')->cursor() as $category) {
                            yield [$category->id, $category->name, $category->slug, $category->is_active ? 'Aktif' : 'Nonaktif', $category->description];
                        }
                    },
                ],
                [
                    'name' => 'Satuan',
                    'title' => 'DAFTAR SATUAN',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'ID', 'width' => 12, 'type' => 'number'],
                        ['header' => 'Nama Satuan', 'width' => 28],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('units')->select('id', 'name')->orderBy('name')->cursor() as $unit) {
                            yield [$unit->id, $unit->name];
                        }
                    },
                ],
                [
                    'name' => 'Pelanggan',
                    'title' => 'DAFTAR PELANGGAN',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'ID', 'width' => 10, 'type' => 'number'],
                        ['header' => 'Nama Pelanggan', 'width' => 30],
                        ['header' => 'Telepon', 'width' => 20],
                        ['header' => 'Email', 'width' => 32],
                        ['header' => 'Alamat', 'width' => 42],
                        ['header' => 'Poin', 'width' => 12, 'type' => 'number'],
                        ['header' => 'Terdaftar', 'width' => 22, 'type' => 'date'],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('customers')->select('id', 'name', 'phone', 'email', 'address', 'points', 'created_at')->orderBy('name')->cursor() as $customer) {
                            yield [$customer->id, $customer->name, $customer->phone, $customer->email, $customer->address, $customer->points, ['value' => $customer->created_at, 'type' => 'date']];
                        }
                    },
                ],
                [
                    'name' => 'Supplier',
                    'title' => 'DAFTAR SUPPLIER',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'ID', 'width' => 10, 'type' => 'number'],
                        ['header' => 'Nama Supplier', 'width' => 30],
                        ['header' => 'Telepon', 'width' => 20],
                        ['header' => 'Alamat', 'width' => 42],
                        ['header' => 'Terdaftar', 'width' => 22, 'type' => 'date'],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('suppliers')->select('id', 'name', 'phone', 'address', 'created_at')->orderBy('name')->cursor() as $supplier) {
                            yield [$supplier->id, $supplier->name, $supplier->phone, $supplier->address, ['value' => $supplier->created_at, 'type' => 'date']];
                        }
                    },
                ],
                [
                    'name' => 'Transaksi Hari Ini',
                    'title' => 'TRANSAKSI HARI INI',
                    'subtitle' => $storeName . '  |  ' . $today->format('d/m/Y') . '  |  Termasuk transaksi selesai dan dibatalkan',
                    'columns' => [
                        ['header' => 'No. Invoice', 'width' => 24],
                        ['header' => 'Tanggal & Waktu', 'width' => 22, 'type' => 'date'],
                        ['header' => 'Kasir', 'width' => 24],
                        ['header' => 'Pelanggan', 'width' => 26],
                        ['header' => 'Total (Rp)', 'width' => 19, 'type' => 'currency'],
                        ['header' => 'Diskon (Rp)', 'width' => 18, 'type' => 'currency'],
                        ['header' => 'Metode Bayar', 'width' => 18],
                        ['header' => 'Status', 'width' => 18],
                    ],
                    'rows' => static function () use ($todayString): \Generator {
                        foreach (DB::table('transactions as t')->leftJoin('users as u', 'u.id', '=', 't.cashier_id')
                            ->leftJoin('customers as c', 'c.id', '=', 't.customer_id')
                            ->whereDate('t.created_at', $todayString)
                            ->select('t.invoice_number', 't.created_at', 'u.name as cashier_name', 'c.name as customer_name', 't.total', 't.discount', 't.payment_method', 't.status')
                            ->orderByDesc('t.created_at')->orderByDesc('t.id')->cursor() as $transaction) {
                            $payment = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS'][$transaction->payment_method] ?? $transaction->payment_method;
                            $status = ['completed' => 'Selesai', 'voided' => 'Dibatalkan'][$transaction->status] ?? $transaction->status;
                            yield [$transaction->invoice_number, ['value' => $transaction->created_at, 'type' => 'date'], $transaction->cashier_name, $transaction->customer_name ?? 'Umum', $transaction->total, $transaction->discount, $payment, $status];
                        }
                    },
                ],
                [
                    'name' => 'Stok Menipis',
                    'title' => 'DAFTAR STOK MENIPIS DAN HABIS',
                    'subtitle' => $storeName . '  |  Produk aktif dengan stok sama dengan atau di bawah batas minimum',
                    'columns' => [
                        ['header' => 'SKU', 'width' => 20],
                        ['header' => 'Barcode', 'width' => 22],
                        ['header' => 'Nama Produk', 'width' => 34],
                        ['header' => 'Kategori', 'width' => 24],
                        ['header' => 'Stok Saat Ini', 'width' => 17, 'type' => 'number'],
                        ['header' => 'Stok Minimum', 'width' => 17, 'type' => 'number'],
                        ['header' => 'Perlu Ditambah', 'width' => 18, 'type' => 'number'],
                        ['header' => 'Satuan', 'width' => 14],
                        ['header' => 'Kondisi', 'width' => 16],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('products as p')->leftJoin('categories as c', 'c.id', '=', 'p.category_id')
                            ->where('p.is_active', true)->whereColumn('p.stock', '<=', 'p.min_stock')
                            ->select('p.sku', 'p.barcode', 'p.name', 'c.name as category_name', 'p.stock', 'p.min_stock', 'p.unit')
                            ->orderBy('p.stock')->orderBy('p.name')->cursor() as $product) {
                            yield [$product->sku, $product->barcode, $product->name, $product->category_name, $product->stock, $product->min_stock, max(0, $product->min_stock - $product->stock), $product->unit, $product->stock <= 0 ? 'Habis' : 'Menipis'];
                        }
                    },
                ],
                [
                    'name' => 'Transaksi',
                    'title' => 'RIWAYAT TRANSAKSI PENJUALAN',
                    'subtitle' => $subtitle . '  |  Termasuk transaksi selesai dan dibatalkan',
                    'columns' => [
                        ['header' => 'No. Invoice', 'width' => 24],
                        ['header' => 'Tanggal & Waktu', 'width' => 22, 'type' => 'date'],
                        ['header' => 'Kasir', 'width' => 24],
                        ['header' => 'Pelanggan', 'width' => 26],
                        ['header' => 'Subtotal (Rp)', 'width' => 19, 'type' => 'currency'],
                        ['header' => 'Diskon (Rp)', 'width' => 18, 'type' => 'currency'],
                        ['header' => 'Pajak (Rp)', 'width' => 18, 'type' => 'currency'],
                        ['header' => 'Total (Rp)', 'width' => 19, 'type' => 'currency'],
                        ['header' => 'Dibayar (Rp)', 'width' => 19, 'type' => 'currency'],
                        ['header' => 'Kembalian (Rp)', 'width' => 20, 'type' => 'currency'],
                        ['header' => 'Metode Bayar', 'width' => 18],
                        ['header' => 'Status', 'width' => 18],
                        ['header' => 'Catatan', 'width' => 36],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('transactions as t')->leftJoin('users as u', 'u.id', '=', 't.cashier_id')
                            ->leftJoin('customers as c', 'c.id', '=', 't.customer_id')
                            ->select('t.invoice_number', 't.created_at', 'u.name as cashier_name', 'c.name as customer_name', 't.subtotal', 't.discount', 't.tax', 't.total', 't.paid_amount', 't.change_amount', 't.payment_method', 't.status', 't.notes')
                            ->orderByDesc('t.created_at')->orderByDesc('t.id')->cursor() as $transaction) {
                            $payment = ['cash' => 'Tunai', 'transfer' => 'Transfer', 'qris' => 'QRIS'][$transaction->payment_method] ?? $transaction->payment_method;
                            $status = ['completed' => 'Selesai', 'voided' => 'Dibatalkan'][$transaction->status] ?? $transaction->status;
                            yield [$transaction->invoice_number, ['value' => $transaction->created_at, 'type' => 'date'], $transaction->cashier_name, $transaction->customer_name ?? 'Umum', $transaction->subtotal, $transaction->discount, $transaction->tax, $transaction->total, $transaction->paid_amount, $transaction->change_amount, $payment, $status, $transaction->notes];
                        }
                    },
                ],
                [
                    'name' => 'Detail Transaksi',
                    'title' => 'RINCIAN ITEM TRANSAKSI',
                    'subtitle' => $subtitle . '  |  Satu baris untuk setiap item pada struk',
                    'columns' => [
                        ['header' => 'No. Invoice', 'width' => 24],
                        ['header' => 'Tanggal & Waktu', 'width' => 22, 'type' => 'date'],
                        ['header' => 'Nama Produk', 'width' => 32],
                        ['header' => 'SKU', 'width' => 20],
                        ['header' => 'Harga (Rp)', 'width' => 18, 'type' => 'currency'],
                        ['header' => 'Jumlah', 'width' => 14, 'type' => 'number'],
                        ['header' => 'Diskon (Rp)', 'width' => 18, 'type' => 'currency'],
                        ['header' => 'Subtotal (Rp)', 'width' => 20, 'type' => 'currency'],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('transaction_items as i')->join('transactions as t', 't.id', '=', 'i.transaction_id')
                            ->select('t.invoice_number', 't.created_at', 'i.product_name', 'i.product_sku', 'i.price', 'i.qty', 'i.discount', 'i.subtotal')
                            ->orderByDesc('t.created_at')->orderByDesc('t.id')->orderBy('i.id')->cursor() as $item) {
                            yield [$item->invoice_number, ['value' => $item->created_at, 'type' => 'date'], $item->product_name, $item->product_sku, $item->price, $item->qty, $item->discount, $item->subtotal];
                        }
                    },
                ],
                [
                    'name' => 'Stok Masuk',
                    'title' => 'RIWAYAT PEMBELIAN STOK',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'Referensi', 'width' => 24],
                        ['header' => 'Tanggal', 'width' => 18, 'type' => 'date'],
                        ['header' => 'Supplier', 'width' => 30],
                        ['header' => 'Dicatat Oleh', 'width' => 26],
                        ['header' => 'Total (Rp)', 'width' => 20, 'type' => 'currency'],
                        ['header' => 'Catatan', 'width' => 40],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('purchases as p')->join('suppliers as s', 's.id', '=', 'p.supplier_id')
                            ->leftJoin('users as u', 'u.id', '=', 'p.user_id')
                            ->select('p.reference_no', 'p.date', 's.name as supplier_name', 'u.name as user_name', 'p.total_amount', 'p.notes')
                            ->orderByDesc('p.date')->orderByDesc('p.id')->cursor() as $purchase) {
                            yield [$purchase->reference_no ?: '-', ['value' => $purchase->date, 'type' => 'date'], $purchase->supplier_name, $purchase->user_name, $purchase->total_amount, $purchase->notes];
                        }
                    },
                ],
                [
                    'name' => 'Detail Stok Masuk',
                    'title' => 'RINCIAN ITEM STOK MASUK',
                    'subtitle' => $subtitle . '  |  Satu baris untuk setiap produk pada pembelian',
                    'columns' => [
                        ['header' => 'Referensi', 'width' => 24],
                        ['header' => 'Tanggal', 'width' => 18, 'type' => 'date'],
                        ['header' => 'Supplier', 'width' => 30],
                        ['header' => 'Produk', 'width' => 32],
                        ['header' => 'SKU', 'width' => 20],
                        ['header' => 'Jumlah', 'width' => 14, 'type' => 'number'],
                        ['header' => 'Harga Beli (Rp)', 'width' => 20, 'type' => 'currency'],
                        ['header' => 'Subtotal (Rp)', 'width' => 20, 'type' => 'currency'],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('purchase_items as i')->join('purchases as p', 'p.id', '=', 'i.purchase_id')
                            ->join('suppliers as s', 's.id', '=', 'p.supplier_id')->join('products as pr', 'pr.id', '=', 'i.product_id')
                            ->select('p.reference_no', 'p.date', 's.name as supplier_name', 'pr.name as product_name', 'pr.sku', 'i.qty', 'i.purchase_price', 'i.subtotal')
                            ->orderByDesc('p.date')->orderByDesc('p.id')->orderBy('i.id')->cursor() as $item) {
                            yield [$item->reference_no ?: '-', ['value' => $item->date, 'type' => 'date'], $item->supplier_name, $item->product_name, $item->sku, $item->qty, $item->purchase_price, $item->subtotal];
                        }
                    },
                ],
                [
                    'name' => 'Riwayat Stok',
                    'title' => 'RIWAYAT PERUBAHAN STOK',
                    'subtitle' => $subtitle,
                    'columns' => [
                        ['header' => 'Tanggal & Waktu', 'width' => 22, 'type' => 'date'],
                        ['header' => 'Produk', 'width' => 32],
                        ['header' => 'SKU', 'width' => 20],
                        ['header' => 'Jenis Perubahan', 'width' => 22],
                        ['header' => 'Stok Sebelum', 'width' => 16, 'type' => 'number'],
                        ['header' => 'Perubahan', 'width' => 14, 'type' => 'number'],
                        ['header' => 'Stok Sesudah', 'width' => 16, 'type' => 'number'],
                        ['header' => 'Petugas', 'width' => 24],
                        ['header' => 'No. Invoice', 'width' => 24],
                        ['header' => 'Catatan', 'width' => 38],
                    ],
                    'rows' => static function (): \Generator {
                        foreach (DB::table('stock_logs as l')->join('products as p', 'p.id', '=', 'l.product_id')
                            ->leftJoin('users as u', 'u.id', '=', 'l.user_id')->leftJoin('transactions as t', 't.id', '=', 'l.transaction_id')
                            ->select('l.created_at', 'p.name as product_name', 'p.sku', 'l.type', 'l.qty_before', 'l.qty_change', 'l.qty_after', 'u.name as user_name', 't.invoice_number', 'l.notes')
                            ->orderByDesc('l.created_at')->orderByDesc('l.id')->cursor() as $log) {
                            $type = ['in' => 'Stok Masuk', 'out' => 'Penjualan', 'adjustment' => 'Penyesuaian'][$log->type] ?? $log->type;
                            yield [['value' => $log->created_at, 'type' => 'date'], $log->product_name, $log->sku, $type, $log->qty_before, $log->qty_change, $log->qty_after, $log->user_name, $log->invoice_number, $log->notes];
                        }
                    },
                ],
            ];

            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir) && !mkdir($tempDir, 0750, true) && !is_dir($tempDir)) {
                throw new \RuntimeException('Direktori sementara ekspor tidak dapat dibuat.');
            }

            $filename = 'data-toko_' . $exportedAt->format('Y-m-d_H-i-s') . '.xlsx';
            $filePath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            (new XlsxWorkbook())->write($sheets, $filePath);

            ActivityLog::log('Ekspor Data Excel', 'Mengunduh data operasional toko dalam format Excel.');

            return response()->download($filePath, $filename)->deleteFileAfterSend(true);
        } catch (\Throwable $e) {
            if ($filePath && is_file($filePath)) {
                @unlink($filePath);
            }
            report($e);
            return back()->with('error', 'Gagal menyiapkan file Excel. Periksa log aplikasi.');
        }
    }

    public function backup(): \Symfony\Component\HttpFoundation\BinaryFileResponse|RedirectResponse
    {
        $filePath = null;
        $handle = null;
        try {
            $connection = config('database.default');
            $dbName = config("database.connections.{$connection}.database");

            if ($connection !== 'mysql') {
                return back()->with('error', 'Fitur backup saat ini hanya mendukung database MySQL.');
            }

            $tablesResult = DB::select('SHOW TABLES');
            $tablesKey = "Tables_in_{$dbName}";

            $tempDir = storage_path('app/temp');
            if (!is_dir($tempDir) && !mkdir($tempDir, 0750, true) && !is_dir($tempDir)) {
                throw new \RuntimeException('Direktori sementara backup tidak dapat dibuat.');
            }
            $filename = 'backup_' . now()->format('Y-m-d_H-i-s') . '_' . Str::random(8) . '.sql';
            $filePath = $tempDir . DIRECTORY_SEPARATOR . $filename;
            $handle = fopen($filePath, 'wb');
            if ($handle === false) {
                throw new \RuntimeException('File backup tidak dapat dibuat.');
            }

            $write = static function (string $content) use ($handle): void {
                if (fwrite($handle, $content) !== strlen($content)) {
                    throw new \RuntimeException('Penulisan file backup gagal.');
                }
            };
            $write("-- Kasir POS Database Backup\n-- Generated on " . now()->toDateTimeString() . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n");
            $pdo = DB::connection()->getPdo();

            foreach ($tablesResult as $tableRow) {
                $tableName = $tableRow->$tablesKey;
                $quotedTable = '`' . str_replace('`', '``', $tableName) . '`';

                $createTableResult = DB::select("SHOW CREATE TABLE {$quotedTable}");
                $createTableSql = $createTableResult[0]->{'Create Table'};
                $write("-- Table structure for {$quotedTable}\nDROP TABLE IF EXISTS {$quotedTable};\n{$createTableSql};\n\n");

                $columns = DB::select("SHOW FULL COLUMNS FROM {$quotedTable}");
                $columnNames = array_map(fn ($column) => '`' . str_replace('`', '``', $column->Field) . '`', $columns);
                $columnTypes = array_map(fn ($column) => strtolower($column->Type), $columns);

                foreach (DB::table($tableName)->cursor() as $row) {
                    $values = [];
                    foreach (array_values((array) $row) as $index => $value) {
                        if ($value === null) {
                            $values[] = 'NULL';
                        } elseif (preg_match('/^(tinyint|smallint|mediumint|int|bigint|decimal|numeric|float|double|real|bit)/', $columnTypes[$index])) {
                            $values[] = is_numeric($value) ? (string) $value : '0';
                        } else {
                            $quotedValue = $pdo->quote((string) $value);
                            if ($quotedValue === false) {
                                throw new \RuntimeException('Nilai pada tabel tidak dapat dienkode ke file backup.');
                            }
                            $values[] = $quotedValue;
                        }
                    }
                    $write('INSERT INTO ' . $quotedTable . ' (' . implode(', ', $columnNames) . ') VALUES (' . implode(', ', $values) . ");\n");
                }
                $write("\n");
            }

            $write("SET FOREIGN_KEY_CHECKS=1;\n");
            fclose($handle);

            ActivityLog::log('Backup Data', 'Melakukan backup database sistem.');

            return response()->download($filePath, $filename)->deleteFileAfterSend(true);

        } catch (\Throwable $e) {
            if (is_resource($handle)) {
                fclose($handle);
            }
            if ($filePath && is_file($filePath)) {
                @unlink($filePath);
            }
            report($e);
            return back()->with('error', 'Gagal membuat backup database. Periksa log aplikasi.');
        }
    }
}
