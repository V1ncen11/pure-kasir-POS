<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\KasirController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\CustomerController;
use Illuminate\Support\Facades\Route;

// Redirect root ke dashboard
Route::get('/', fn () => redirect()->route('dashboard'));

// Auth routes (Breeze)
require __DIR__ . '/auth.php';

// Protected routes (semua user yang login)
Route::middleware(['auth', 'verified'])->group(function () {

    // Profile (semua role)
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::post('/profile/avatar', [ProfileController::class, 'uploadAvatar'])->name('profile.avatar');

    // Dashboard (semua role, tapi konten berbeda)
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Kasir (admin & kasir)
    Route::middleware('role:admin,kasir')->group(function () {
        Route::get('/kasir', [KasirController::class, 'index'])->name('kasir.index');
        Route::post('/kasir/transaksi', [KasirController::class, 'store'])->name('kasir.store');
        Route::get('/kasir/produk/cari', [KasirController::class, 'searchProduct'])->name('kasir.product.search');
        Route::get('/kasir/pelanggan/cari', [KasirController::class, 'searchCustomer'])->name('kasir.customer.search');
        Route::post('/kasir/pelanggan/tambah', [KasirController::class, 'storeCustomer'])->name('kasir.customer.store');
    });

    // Transaksi history (semua role, tapi kasir hanya lihat hari ini)
    Route::middleware('role:admin,kasir')->group(function () {
        Route::get('/transaksi', [TransactionController::class, 'index'])->name('transactions.index');
        Route::get('/transaksi/{transaction}', [TransactionController::class, 'show'])->name('transactions.show');
    });

    // Admin only routes
    Route::middleware('role:admin')->group(function () {

        // Produk
        Route::resource('produk', ProductController::class)->names([
            'index'   => 'products.index',
            'create'  => 'products.create',
            'store'   => 'products.store',
            'show'    => 'products.show',
            'edit'    => 'products.edit',
            'update'  => 'products.update',
            'destroy' => 'products.destroy',
        ])->parameters(['produk' => 'product']);
        Route::post('/produk/{product}/adjust-stok', [ProductController::class, 'adjustStock'])
            ->name('products.adjustStock');

        // Kategori
        Route::resource('kategori', CategoryController::class)->names([
            'index'   => 'categories.index',
            'create'  => 'categories.create',
            'store'   => 'categories.store',
            'show'    => 'categories.show',
            'edit'    => 'categories.edit',
            'update'  => 'categories.update',
            'destroy' => 'categories.destroy',
        ])->parameters(['kategori' => 'category']);

        // Laporan
        Route::get('/laporan', [ReportController::class, 'index'])->name('reports.index');
        Route::get('/laporan/export', [ReportController::class, 'export'])->name('reports.export');

        // Void transaksi (admin only)
        Route::post('/transaksi/{transaction}/void', [TransactionController::class, 'void'])
            ->name('transactions.void');

        // User management
        Route::resource('pengguna', UserController::class)->names([
            'index'   => 'users.index',
            'create'  => 'users.create',
            'store'   => 'users.store',
            'show'    => 'users.show',
            'edit'    => 'users.edit',
            'update'  => 'users.update',
            'destroy' => 'users.destroy',
        ])->parameters(['pengguna' => 'user']);

        // Pelanggan CRUD
        Route::resource('pelanggan', CustomerController::class)->names([
            'index'   => 'customers.index',
            'create'  => 'customers.create',
            'store'   => 'customers.store',
            'show'    => 'customers.show',
            'edit'    => 'customers.edit',
            'update'  => 'customers.update',
            'destroy' => 'customers.destroy',
        ])->parameters(['pelanggan' => 'customer']);

        // Suppliers
        Route::resource('suppliers', \App\Http\Controllers\SupplierController::class)->except(['create', 'show', 'edit']);

        // Satuan
        Route::resource('satuan', \App\Http\Controllers\UnitController::class)->except(['create', 'show', 'edit']);


        // Purchases (Stok Masuk)
        Route::resource('purchases', \App\Http\Controllers\PurchaseController::class)->only(['index', 'create', 'store', 'show', 'edit', 'update']);

        // Settings
        Route::get('/pengaturan', [SettingController::class, 'index'])->name('settings.index');
        Route::post('/pengaturan', [SettingController::class, 'update'])->name('settings.update');
        Route::post('/pengaturan/logo', [SettingController::class, 'uploadLogo'])->name('settings.logo');
        Route::get('/pengaturan/backup', [SettingController::class, 'backup'])->name('settings.backup');
        Route::get('/pengaturan/ekspor-data', [SettingController::class, 'exportData'])->name('settings.export-data');

        // Log Aktivitas
        Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])->name('activity-logs.index');
    });
});
