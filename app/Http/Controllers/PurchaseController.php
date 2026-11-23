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

    public function edit(Purchase $purchase)
    {
        $purchase->load(['items.product']);
        $purchasedProductIds = $purchase->items->pluck('product_id');
        $products = Product::where('is_active', true)
            ->orWhereIn('id', $purchasedProductIds)
            ->orderBy('name')
            ->get();

        return Inertia::render('Purchases/Create', [
            'purchase' => $purchase,
            'suppliers' => Supplier::orderBy('name')->get(),
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
                $this->refreshLatestPurchasePrice($product);
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

    public function update(Request $request, Purchase $purchase)
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

        $updateResult = DB::transaction(function () use ($validated, $purchase) {
            $lockedPurchase = Purchase::whereKey($purchase->id)->lockForUpdate()->firstOrFail();
            $oldItems = PurchaseItem::where('purchase_id', $lockedPurchase->id)
                ->lockForUpdate()
                ->get();

            $oldQuantities = $oldItems->groupBy('product_id')
                ->map(fn ($items) => $items->sum('qty'));
            $newItems = collect($validated['items']);
            $newQuantities = $newItems->groupBy('product_id')
                ->map(fn ($items) => $items->sum('qty'));

            // Keep the product set fixed during correction so master costs can be recalculated safely.
            if ($oldQuantities->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()
                !== $newQuantities->keys()->map(fn ($id) => (int) $id)->sort()->values()->all()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Produk pada stok masuk tidak dapat ditambah atau dihapus saat edit. Ubah qty atau harga pada produk yang tercatat.',
                ]);
            }

            $products = Product::whereIn('id', $oldQuantities->keys())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            if ($products->count() !== $oldQuantities->count()) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'items' => 'Salah satu produk pada stok masuk sudah tidak tersedia.',
                ]);
            }

            $stockChanges = [];
            foreach ($oldQuantities as $productId => $oldQty) {
                $product = $products->get($productId);
                $newQty = $newQuantities->get($productId, 0);
                $delta = $newQty - $oldQty;
                $after = $product->stock + $delta;

                if ($after < 0) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'items' => "Qty {$product->name} tidak bisa dikurangi sebanyak itu karena sebagian stok sudah terjual atau terpakai. Stok saat ini: {$product->stock}.",
                    ]);
                }

                $stockChanges[$productId] = [
                    'before' => $product->stock,
                    'delta' => $delta,
                    'after' => $after,
                ];
            }

            $oldSupplierName = $lockedPurchase->supplier()->value('name') ?? 'Supplier terhapus';
            $oldTotal = $lockedPurchase->total_amount;
            $oldDate = $lockedPurchase->date->format('d/m/Y');
            $newTotal = $newItems->sum(fn ($item) => $item['qty'] * $item['purchase_price']);
            $itemChanges = [];
            foreach ($oldQuantities as $productId => $oldQty) {
                $oldPrice = $oldItems->where('product_id', $productId)->last()->purchase_price;
                $newProductItems = $newItems->where('product_id', $productId);
                $newQty = $newQuantities->get($productId, 0);
                $newPrice = $newProductItems->last()['purchase_price'];
                $itemChanges[] = sprintf(
                    '%s: qty %s → %s, harga beli Rp %s → Rp %s',
                    $products->get($productId)->name,
                    number_format($oldQty, 0, ',', '.'),
                    number_format($newQty, 0, ',', '.'),
                    number_format($oldPrice, 0, ',', '.'),
                    number_format($newPrice, 0, ',', '.')
                );
            }

            $lockedPurchase->update([
                'supplier_id' => $validated['supplier_id'],
                'date' => $validated['date'],
                'reference_no' => $validated['reference_no'] ?? null,
                'total_amount' => $newTotal,
                'notes' => $validated['notes'] ?? null,
            ]);

            $lockedPurchase->items()->delete();
            foreach ($validated['items'] as $item) {
                $lockedPurchase->items()->create([
                    'product_id' => $item['product_id'],
                    'qty' => $item['qty'],
                    'purchase_price' => $item['purchase_price'],
                    'subtotal' => $item['qty'] * $item['purchase_price'],
                ]);
            }

            foreach ($stockChanges as $productId => $change) {
                $product = $products->get($productId);
                if ($change['delta'] !== 0) {
                    $product->update(['stock' => $change['after']]);
                    $deltaLabel = ($change['delta'] > 0 ? '+' : '') . $change['delta'];
                    StockLog::create([
                        'product_id' => $product->id,
                        'user_id' => Auth::id(),
                        'type' => 'adjustment',
                        'qty_before' => $change['before'],
                        'qty_change' => $change['delta'],
                        'qty_after' => $change['after'],
                        'notes' => 'Koreksi edit stok masuk #' . $lockedPurchase->id . ' (' . $deltaLabel . ')',
                    ]);
                }

                $this->refreshLatestPurchasePrice($product);
            }

            return [
                'purchase' => $lockedPurchase->fresh('supplier'),
                'old_supplier_name' => $oldSupplierName,
                'old_date' => $oldDate,
                'old_total' => $oldTotal,
                'new_total' => $newTotal,
                'item_changes' => $itemChanges,
            ];
        });

        $updatedPurchaseId = $updateResult['purchase']->id;
        $updatedSupplierName = $updateResult['purchase']->supplier?->name ?? 'Supplier terhapus';

        ActivityLog::log(
            'Edit Stok Masuk',
            "Mengubah stok masuk #{$updatedPurchaseId}. Supplier: {$updateResult['old_supplier_name']} → {$updatedSupplierName}; tanggal: {$updateResult['old_date']} → "
                . \Illuminate\Support\Carbon::parse($updateResult['purchase']->date)->format('d/m/Y')
                . '; total: Rp ' . number_format($updateResult['old_total'], 0, ',', '.') . ' → Rp '
                . number_format($updateResult['new_total'], 0, ',', '.') . '. Detail: '
                . implode('; ', $updateResult['item_changes'])
        );

        return redirect()->route('purchases.show', $purchase)->with('success', 'Data stok masuk berhasil diperbarui.');
    }

    private function refreshLatestPurchasePrice(Product $product): void
    {
        $latestPurchasePrice = PurchaseItem::query()
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchase_items.product_id', $product->id)
            ->orderByDesc('purchases.date')
            ->orderByDesc('purchases.id')
            ->value('purchase_items.purchase_price');

        if ($latestPurchasePrice !== null && (string) $latestPurchasePrice !== (string) $product->buy_price) {
            $product->update(['buy_price' => $latestPurchasePrice]);
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
