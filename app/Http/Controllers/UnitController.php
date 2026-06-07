<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Unit;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UnitController extends Controller
{
    public function index()
    {
        $units = Unit::latest()->get();
        return Inertia::render('Units/Index', [
            'units' => $units
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:units,name',
        ], [
            'name.unique' => 'Nama satuan ini sudah terdaftar.',
            'name.required' => 'Nama satuan wajib diisi.'
        ]);

        Unit::create($validated);

        ActivityLog::log('Tambah Satuan', "Menambahkan satuan baru: {$validated['name']}");

        return redirect()->back()->with('success', 'Satuan berhasil ditambahkan.');
    }

    public function update(Request $request, Unit $unit)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:units,name,' . $unit->id,
        ], [
            'name.unique' => 'Nama satuan ini sudah terdaftar.',
            'name.required' => 'Nama satuan wajib diisi.'
        ]);

        $oldName = $unit->name;
        $unit->update($validated);

        // Update any existing products using this unit name to ensure consistency
        Product::where('unit', $oldName)->update(['unit' => $validated['name']]);

        ActivityLog::log('Edit Satuan', "Mengubah data satuan: {$oldName} -> {$validated['name']}");

        return redirect()->back()->with('success', 'Satuan berhasil diperbarui.');
    }

    public function destroy(Unit $unit)
    {
        // Check if any product is using this unit name
        if (Product::where('unit', $unit->name)->exists()) {
            return redirect()->back()->with('error', 'Satuan tidak dapat dihapus karena masih digunakan oleh produk aktif.');
        }

        $unitName = $unit->name;
        $unit->delete();

        ActivityLog::log('Hapus Satuan', "Menghapus satuan: {$unitName}");

        return redirect()->back()->with('success', 'Satuan berhasil dihapus.');
    }
}
