<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request): Response
    {
        $categories = Category::withCount('products')
            ->when($request->search, fn($q, $s) => $q->where('name', 'like', "%$s%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return Inertia::render('Categories/Index', [
            'categories' => $categories,
            'filters'    => $request->only('search'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:categories',
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);

        Category::create($data);

        ActivityLog::log('Tambah Kategori', "Menambahkan kategori baru: {$data['name']}");

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:100|unique:categories,name,' . $category->id,
            'description' => 'nullable|string|max:500',
            'is_active'   => 'boolean',
        ]);

        $data['slug'] = Str::slug($data['name']);

        $category->update($data);

        ActivityLog::log('Edit Kategori', "Mengubah data kategori: {$category->name}");

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        if ($category->products()->exists()) {
            return back()->with('error', 'Kategori tidak bisa dihapus karena masih memiliki produk.');
        }

        $categoryName = $category->name;
        $category->delete();

        ActivityLog::log('Hapus Kategori', "Menghapus kategori: {$categoryName}");

        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
