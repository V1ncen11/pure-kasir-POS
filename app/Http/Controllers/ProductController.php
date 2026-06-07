<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Product;
use App\Models\StockLog;
use App\Models\Unit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request): Response
    {
        $products = Product::with('category')
            ->when($request->search, fn($q, $s) =>
                $q->where('name', 'like', "%$s%")
                  ->orWhere('sku', 'like', "%$s%")
                  ->orWhere('barcode', 'like', "%$s%")
            )
            ->when($request->category, fn($q, $c) => $q->where('category_id', $c))
            ->when($request->low_stock, fn($q) => $q->whereColumn('stock', '<=', 'min_stock'))
            ->when($request->status !== null, fn($q) => $q->where('is_active', $request->status))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);

        return Inertia::render('Products/Index', [
            'products'   => $products,
            'categories' => $categories,
            'filters'    => $request->only(['search', 'category', 'low_stock', 'status']),
        ]);
    }

    public function create(): Response
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $units = Unit::orderBy('name')->pluck('name');
        return Inertia::render('Products/Form', [
            'categories' => $categories,
            'units'      => $units,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'sku'         => 'required|string|max:100|unique:products',
            'barcode'     => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'unit'        => 'required|string|max:20',
            'buy_price'   => 'required|numeric|min:0',
            'sell_price'  => 'required|numeric|min:0',
            'stock'       => 'required|integer|min:0',
            'min_stock'   => 'required|integer|min:0',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
            'image'       => 'nullable|image|max:2048',
        ]);

        // Cek duplikasi barcode secara manual untuk memunculkan nama produk yang bentrok
        if (!empty($data['barcode'])) {
            $existing = Product::where('barcode', $data['barcode'])->first();
            if ($existing) {
                return back()->withErrors([
                    'barcode' => "Nomor ini sudah dipakai di produk '{$existing->name}'. Cek kembali nomor barcode yang ada di kemasan."
                ])->withInput();
            }
        }

        // Pastikan sell_price tidak lebih rendah dari buy_price (opsional warning)
        if ((float) $data['sell_price'] < (float) $data['buy_price']) {
            return back()->withErrors(['sell_price' => 'Harga jual tidak boleh lebih rendah dari harga beli.'])->withInput();
        }

        if ($request->hasFile('image')) {
            $data['image'] = $request->file('image')->store('products', 'public');
        }

        $product = Product::create($data);

        // Log stok awal
        if ($product->stock > 0) {
            StockLog::create([
                'product_id' => $product->id,
                'user_id'    => auth()->id(),
                'type'       => 'in',
                'qty_before' => 0,
                'qty_change' => $product->stock,
                'qty_after'  => $product->stock,
                'notes'      => 'Stok awal saat produk ditambahkan',
            ]);
        }

        ActivityLog::log('Tambah Produk', "Menambahkan produk baru: {$product->name} (SKU: {$product->sku})");

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): Response
    {
        $categories = Category::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $units = Unit::orderBy('name')->pluck('name');
        return Inertia::render('Products/Form', [
            'product'    => $product,
            'categories' => $categories,
            'units'      => $units,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'name'        => 'required|string|max:200',
            'sku'         => 'required|string|max:100|unique:products,sku,' . $product->id,
            'barcode'     => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:categories,id',
            'unit'        => 'required|string|max:20',
            'buy_price'   => 'required|numeric|min:0',
            'sell_price'  => 'required|numeric|min:0',
            'min_stock'   => 'required|integer|min:0',
            'description' => 'nullable|string|max:1000',
            'is_active'   => 'boolean',
            'image'       => 'nullable|image|max:2048',
        ]);

        // Cek duplikasi barcode secara manual untuk memunculkan nama produk yang bentrok
        if (!empty($data['barcode'])) {
            $existing = Product::where('barcode', $data['barcode'])
                ->where('id', '!=', $product->id)
                ->first();
            if ($existing) {
                return back()->withErrors([
                    'barcode' => "Nomor ini sudah dipakai di produk '{$existing->name}'. Cek kembali nomor barcode yang ada di kemasan."
                ])->withInput();
            }
        }

        if ($request->hasFile('image')) {
            if ($product->image) Storage::disk('public')->delete($product->image);
            $data['image'] = $request->file('image')->store('products', 'public');
        } else {
            unset($data['image']);
        }

        $product->update($data);

        ActivityLog::log('Edit Produk', "Mengubah data produk: {$product->name} (SKU: {$product->sku})");

        return redirect()->route('products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Proteksi: jangan hapus produk yang sudah pernah dijual
        if ($product->transactionItems()->exists()) {
            return back()->with('error', "Produk '{$product->name}' tidak dapat dihapus karena sudah pernah digunakan dalam transaksi. Nonaktifkan produk ini jika tidak ingin ditampilkan.");
        }

        $productName = $product->name;
        if ($product->image) Storage::disk('public')->delete($product->image);
        $product->delete();

        ActivityLog::log('Hapus Produk', "Menghapus produk: {$productName}");

        return back()->with('success', 'Produk berhasil dihapus.');
    }

    public function adjustStock(Request $request, Product $product): RedirectResponse
    {
        $data = $request->validate([
            'type'  => 'required|in:in,adjustment',
            'qty'   => 'required|integer|min:0',
            'notes' => 'required|string|max:500',
        ]);

        $before = $product->stock;

        if ($data['type'] === 'in') {
            // Tambah stok: after = before + qty
            $after  = $before + $data['qty'];
            $change = $data['qty'];
        } else {
            // Koreksi stok (adjustment): qty adalah nilai stok fisik baru
            $after  = $data['qty'];
            $change = $data['qty'] - $before; // bisa negatif jika koreksi turun
        }

        $product->update(['stock' => $after]);

        StockLog::create([
            'product_id' => $product->id,
            'user_id'    => auth()->id(),
            'type'       => $data['type'],
            'qty_before' => $before,
            'qty_change' => $change,
            'qty_after'  => $after,
            'notes'      => $data['notes'],
        ]);

        $typeLabel = $data['type'] === 'in' ? 'Stok Masuk' : 'Koreksi Stok';
        ActivityLog::log('Penyesuaian Stok', "{$typeLabel} produk '{$product->name}': {$before} → {$after} ({$data['notes']})");

        return back()->with('success', 'Stok berhasil disesuaikan.');
    }
}
