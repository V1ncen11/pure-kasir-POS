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

        try {
            DB::beginTransaction();

            $totalAmount = 0;
            foreach ($validated['items'] as $item) {
                $totalAmount += ($item['qty'] * $item['purchase_price']);
            }

            $purchase = Purchase::create([
                'supplier_id' => $validated['supplier_id'],
                'user_id' => Auth::id(),
                'date' => $validated['date'],
                'reference_no' => $validated['reference_no'],
                'total_amount' => $totalAmount,
                'notes' => $validated['notes'],
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

                // Update product stock
                $product = Product::findOrFail($item['product_id']);
                $qtyBefore = $product->stock;
                $product->stock += $item['qty'];
                $product->save();

                // Create stock log
                StockLog::create([
                    'product_id' => $product->id,
                    'user_id' => Auth::id(),
                    'type' => 'in',
                    'qty_before' => $qtyBefore,
                    'qty_change' => $item['qty'],
                    'qty_after' => $product->stock,
                    'notes' => 'Stok Masuk dari Pembelian #' . $purchase->id,
                ]);
            }

            DB::commit();

            ActivityLog::log('Stok Masuk', "Memasukkan stok barang dari supplier ID {$validated['supplier_id']} senilai Rp " . number_format($totalAmount, 0, ',', '.'));

            return redirect()->route('purchases.index')->with('success', 'Data Stok Masuk berhasil disimpan.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->back()->with('error', 'Terjadi kesalahan: ' . $e->getMessage());
        }
    }

    public function show(Purchase $purchase)
    {
        $purchase->load(['supplier', 'user', 'items.product']);
        
        return Inertia::render('Purchases/Show', [
            'purchase' => $purchase
        ]);
    }
}
