<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\StockLog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PurchaseController extends Controller
{
    public function index()
    {
        $purchases = Purchase::with(['supplier', 'user'])
            ->withCount('items')
            ->latest('date')
            ->latest('id')
            ->paginate(10);

        return Inertia::render('Purchases/Index', [
            'purchases' => $purchases
        ]);
    }

    public function create()
    {
        $suppliers = Supplier::orderBy('name')->get();
        $products = Product::where('is_active', true)->orderBy('name')->get();

        return Inertia::render('Purchases/Create', [
            'suppliers' => $suppliers,
            'products' => $products,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'supplier_id' => 'required|exists:suppliers,id',
            'date' => 'required|date',
            'reference_no' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.purchase_price' => 'required|numeric|min:0',
        ]);

        $totalAmount = DB::transaction(function () use ($validated) {
            $quantities = collect($validated['items'])->groupBy('product_id')
                ->map(fn ($items) => $items->sum('qty'));
            $products = Product::whereIn('id', $quantities->keys())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($products->count() !== $quantities->count()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Salah satu produk sudah tidak tersedia.']);
            }

            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += ($item['qty'] * $item['purchase_price']);
            }

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'user_id' => Auth::id(),
                'date' => $validated['date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($validated['items'] as $item) {
                $subtotal = $item['qty'] * $item['purchase_price'];

                PurchaseItem::create([
                    'purchase_id' => $purchase->id,
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'purchase_price' => $item['purchase_price'],
                    'subtotal' => $subtotal,
                ]);

            }

            foreach ($quantities as $productId => $qty) {
                $product = $products->get($productId);
                $qtyBefore = $product->stock;
                $qtyAfter = $qtyBefore + $qty;
                $product->update(['stock' => $qtyAfter]);
                StockLog::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'type' => 'in',
                    'qty_before' => $qtyBefore,
                    'qty_change' => $qty,
                    'qty_after' => $qtyAfter,
                    'notes' => 'Stok Masuk dari Pembelian #' . $purchase->id,
                ]);
            }

            return $totalAmount;
        });

        ActivityLog::log('Stok Masuk', "Memasukkan stok barang dari supplier ID {$validated['supplier_id']} senilai Rp " . number_format($totalAmount, 0, ',', '.'));

        return redirect()->route('purchases.index')->with('success', 'Data Stok Masuk berhasil disimpan.');
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'user', 'items.product']);
        
        return Inertia::render('Purchases/Show', [
            'purchase' => $purchase
        ]);
    }
}
