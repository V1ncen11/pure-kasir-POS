<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ActivityLog;
use App\Models\StockLog;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Customer;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class KasirController extends Controller
{
    public function index(): Response
    {
        $products = Product::where('is_active', true)
            ->with('category:id,name')
            ->get(['id', 'name', 'sku', 'barcode', 'sell_price', 'stock', 'unit', 'category_id', 'image']);

        $categories = \App\Models\Category::all(['id', 'name']);

        return Inertia::render('Kasir/Index', [
            'products'   => $products,
            'categories' => $categories,
        ]);
    }

    public function searchProduct(Request $request): JsonResponse
    {
        $keyword = $request->get('q', '');

        $products = Product::where('is_active', true)
            ->where('stock', '>', 0)
            ->where(function ($q) use ($keyword) {
                $q->where('name', 'like', "%{$keyword}%")
                  ->orWhere('sku', 'like', "%{$keyword}%")
                  ->orWhere('barcode', 'like', "%{$keyword}%");
            })
            ->with('category:id,name')
            ->take(20)
            ->get(['id', 'name', 'sku', 'barcode', 'sell_price', 'stock', 'unit', 'category_id', 'image']);

        return response()->json($products);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        $data = $request->validate([
            'items'          => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty'    => 'required|integer|min:1',
            'items.*.price'  => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0',
            'tax'            => 'nullable|numeric|min:0',
            'paid_amount'    => 'required|numeric|min:0',
            'payment_method' => 'required|in:cash,transfer,qris',
            'notes'          => 'nullable|string|max:500',
            'customer_id'    => 'nullable|exists:customers,id',
        ]);
        $transaction = DB::transaction(function () use ($data) {
            $quantities = collect($data['items'])->groupBy('product_id')
                ->map(fn ($items) => $items->sum('qty'));
            $products = Product::whereIn('id', $quantities->keys())
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');

            if ($products->count() !== $quantities->count()) {
                throw \Illuminate\Validation\ValidationException::withMessages(['items' => 'Salah satu produk sudah tidak tersedia.']);
            }

            foreach ($quantities as $productId => $qty) {
                $product = $products->get($productId);
                if (!$product->is_active) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Produk '{$product->name}' sudah tidak aktif."]);
                }
                if ($product->stock < $qty) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Stok produk '{$product->name}' tidak mencukupi (tersedia: {$product->stock})."]);
                }
            }

            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $price = (float) $product->sell_price;
                $itemDiscount = (float) ($item['discount'] ?? 0);
                if ($itemDiscount > $price * $item['qty']) {
                    throw \Illuminate\Validation\ValidationException::withMessages(['items' => "Diskon untuk '{$product->name}' melebihi nilai barang."]);
                }
                $itemSubtotal = ($price * $item['qty']) - $itemDiscount;
                $subtotal += $itemSubtotal;
            }

            $discount = min(max(0, $data['discount'] ?? 0), $subtotal);
            $taxRate  = max(0, min(100, (float) Setting::get('tax_percentage', 0)));
            $tax      = round(($subtotal - $discount) * $taxRate / 100, 2);
            $total    = $subtotal - $discount + $tax;
            $change   = $data['paid_amount'] - $total;

            if ($change < 0) {
                throw \Illuminate\Validation\ValidationException::withMessages(['paid_amount' => 'Jumlah pembayaran kurang dari total transaksi.']);
            }

            $transaction = Transaction::create([
                'invoice_number' => Transaction::generateInvoiceNumber(),
                'cashier_id'     => auth()->id(),
                'customer_id'    => $data['customer_id'] ?? null,
                'subtotal'       => $subtotal,
                'discount'       => $discount,
                'tax'            => $tax,
                'total'          => $total,
                'paid_amount'    => $data['paid_amount'],
                'change_amount'  => max(0, $change),
                'payment_method' => $data['payment_method'],
                'notes'          => $data['notes'] ?? null,
                'status'         => 'completed',
            ]);

            // Tambahkan poin jika ada pelanggan
            if ($transaction->customer_id) {
                $pointsEarned = floor($transaction->total / 10000);
                if ($pointsEarned > 0) {
                    $transaction->customer->increment('points', $pointsEarned);
                }
            }

            foreach ($data['items'] as $item) {
                $product = $products->get($item['product_id']);
                $price = (float) $product->sell_price;
                $itemDiscount = (float) ($item['discount'] ?? 0);
                $itemSubtotal = ($price * $item['qty']) - $itemDiscount;

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'product_sku'    => $product->sku,
                    'price'          => $price,
                    'qty'            => $item['qty'],
                    'discount'       => $itemDiscount,
                    'subtotal'       => $itemSubtotal,
                ]);

                // Kurangi stok
                $before = $product->stock;
                $product->decrement('stock', $item['qty']);
                $after  = $product->fresh()->stock;

                StockLog::create([
                    'product_id'     => $product->id,
                    'user_id'        => auth()->id(),
                    'transaction_id' => $transaction->id,
                    'type'           => 'out',
                    'qty_before'     => $before,
                    'qty_change'     => $item['qty'],
                    'qty_after'      => $after,
                    'notes'          => "Terjual via kasir #{$transaction->invoice_number}",
                ]);
            }

            return $transaction->load('items');
        });

        ActivityLog::log('Transaksi Penjualan', 'Transaksi kasir berhasil diproses.');

        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'transaction' => [
                    'invoice' => $transaction->invoice_number,
                    'subtotal' => $transaction->subtotal,
                    'discount' => $transaction->discount,
                    'tax' => $transaction->tax,
                    'total' => $transaction->total,
                    'paid' => $transaction->paid_amount,
                    'change' => $transaction->change_amount,
                    'method' => $transaction->payment_method,
                    'created_at' => $transaction->created_at->toIso8601String(),
                    'items' => $transaction->items->map(fn ($item) => [
                        'product_id' => $item->product_id,
                        'name' => $item->product_name,
                        'sku' => $item->product_sku,
                        'price' => $item->price,
                        'qty' => $item->qty,
                        'discount' => $item->discount,
                        'subtotal' => $item->subtotal,
                    ]),
                ],
            ]);
        }

        return back()->with('success', 'Transaksi berhasil disimpan.');
    }

    public function searchCustomer(Request $request): JsonResponse
    {
        $keyword = $request->get('q', '');

        $customers = Customer::where('name', 'like', "%{$keyword}%")
            ->orWhere('phone', 'like', "%{$keyword}%")
            ->take(15)
            ->get(['id', 'name', 'phone', 'points']);

        return response()->json($customers);
    }

    public function storeCustomer(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => 'required|string|max:255',
            'phone'   => 'nullable|string|max:20|unique:customers,phone',
            'address' => 'nullable|string|max:500',
        ]);

        $customer = Customer::create([
            'name'    => $data['name'],
            'phone'   => $data['phone'] ?? null,
            'address' => $data['address'] ?? null,
            'points'  => 0,
        ]);

        return response()->json([
            'success'  => true,
            'message'  => 'Member baru berhasil ditambahkan.',
            'customer' => $customer,
        ]);
    }
}
