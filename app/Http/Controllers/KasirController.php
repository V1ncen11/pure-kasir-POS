<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ActivityLog;
use App\Models\StockLog;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\Customer;
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

    public function store(Request $request): RedirectResponse
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
        // Backend verification for product activity status and stock sufficiency
        foreach ($data['items'] as $index => $item) {
            $product = Product::find($item['product_id']);
            if (!$product->is_active) {
                return back()->with('error', "Produk '{$product->name}' sudah tidak aktif.");
            }
            if ($product->stock < $item['qty']) {
                return back()->with('error', "Stok untuk produk '{$product->name}' tidak mencukupi (Tersedia: {$product->stock}).");
            }
            // Validasi diskon per item tidak boleh melebihi harga total item
            $maxItemDiscount = $item['price'] * $item['qty'];
            if (($item['discount'] ?? 0) > $maxItemDiscount) {
                return back()->with('error', "Diskon untuk produk '{$product->name}' tidak boleh melebihi harga totalnya (Rp " . number_format($maxItemDiscount, 0, ',', '.') . ").");
            }
        }

        DB::transaction(function () use ($data) {
            $subtotal = 0;
            foreach ($data['items'] as $item) {
                $itemSubtotal = ($item['price'] * $item['qty']) - ($item['discount'] ?? 0);
                $subtotal += $itemSubtotal;
            }

            $discount = min(max(0, $data['discount'] ?? 0), $subtotal);
            $tax      = $data['tax'] ?? 0;
            $total    = $subtotal - $discount + $tax;
            $change   = $data['paid_amount'] - $total;

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
                $product = Product::find($item['product_id']);
                $itemSubtotal = ($item['price'] * $item['qty']) - ($item['discount'] ?? 0);

                TransactionItem::create([
                    'transaction_id' => $transaction->id,
                    'product_id'     => $product->id,
                    'product_name'   => $product->name,
                    'product_sku'    => $product->sku,
                    'price'          => $item['price'],
                    'qty'            => $item['qty'],
                    'discount'       => $item['discount'] ?? 0,
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

            return $transaction;
        });

        ActivityLog::log('Transaksi Penjualan', 'Transaksi kasir berhasil diproses.');

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
