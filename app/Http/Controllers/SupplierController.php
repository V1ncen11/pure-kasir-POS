<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SupplierController extends Controller
{
    public function index()
    {
        $suppliers = Supplier::latest()->get();
        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:suppliers,phone',
            'address' => 'nullable|string',
        ], [
            'phone.unique' => 'Nomor HP ini sudah terdaftar di supplier lain.'
        ]);

        Supplier::create($validated);

        ActivityLog::log('Tambah Supplier', "Menambahkan supplier baru: {$validated['name']}");

        return redirect()->back()->with('success', 'Supplier berhasil ditambahkan.');
    }

    public function update(Request $request, Supplier $supplier)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'nullable|string|max:20|unique:suppliers,phone,' . $supplier->id,
            'address' => 'nullable|string',
        ], [
            'phone.unique' => 'Nomor HP ini sudah terdaftar di supplier lain.'
        ]);

        $supplier->update($validated);

        ActivityLog::log('Edit Supplier', "Mengubah data supplier: {$supplier->name}");

        return redirect()->back()->with('success', 'Supplier berhasil diperbarui.');
    }

    public function destroy(Supplier $supplier)
    {
        if ($supplier->purchases()->exists()) {
            return redirect()->back()->with('error', 'Supplier tidak dapat dihapus karena memiliki riwayat pembelian.');
        }

        $supplierName = $supplier->name;
        $supplier->delete();

        ActivityLog::log('Hapus Supplier', "Menghapus supplier: {$supplierName}");

        return redirect()->back()->with('success', 'Supplier berhasil dihapus.');
    }
}
