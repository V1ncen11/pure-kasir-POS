<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Customer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $customers = Customer::when($request->search, function ($q, $s) {
                $q->where(fn ($search) => $search
                    ->where('name', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%"));
            })
            ->withCount('transactions')
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters'   => $request->only(['search']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'phone'   => 'nullable|string|max:20|unique:customers,phone',
            'email'   => 'nullable|email|max:100',
            'address' => 'nullable|string|max:500',
        ]);

        $customer = Customer::create($data);

        ActivityLog::log('Tambah Pelanggan', "Menambahkan pelanggan/member baru: {$customer->name}");

        return back()->with('success', 'Pelanggan berhasil ditambahkan.');
    }

    public function update(Request $request, Customer $customer): RedirectResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:100',
            'phone'   => 'nullable|string|max:20|unique:customers,phone,' . $customer->id,
            'email'   => 'nullable|email|max:100',
            'address' => 'nullable|string|max:500',
            'points'  => 'required|integer|min:0',
        ]);

        $customer->update($data);

        ActivityLog::log('Edit Pelanggan', "Mengubah data pelanggan/member: {$customer->name}");

        return back()->with('success', 'Pelanggan berhasil diperbarui.');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        // Proteksi: jangan hapus pelanggan yang sudah pernah bertransaksi
        if ($customer->transactions()->exists()) {
            return back()->with('error', "Pelanggan '{$customer->name}' tidak dapat dihapus karena sudah memiliki riwayat transaksi. Data pelanggan perlu dipertahankan untuk integritas laporan.");
        }

        $customerName = $customer->name;
        $customer->delete();

        ActivityLog::log('Hapus Pelanggan', "Menghapus pelanggan/member: {$customerName}");

        return back()->with('success', 'Pelanggan berhasil dihapus.');
    }
}
