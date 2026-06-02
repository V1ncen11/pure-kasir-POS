<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TransactionController extends Controller
{
    public function index(Request $request): Response
    {
        $user    = $request->user();
        $isAdmin = $user->role === 'admin';

        $transactions = Transaction::with(['cashier:id,name', 'items'])
            ->when(!$isAdmin, fn($q) => $q->whereDate('created_at', Carbon::today()))
            ->when($request->search, fn($q, $s) =>
                $q->where('invoice_number', 'like', "%$s%")
            )
            ->when($request->date_from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($request->date_to,   fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($request->payment,   fn($q, $p) => $q->where('payment_method', $p))
            ->when($request->status,    fn($q, $s) => $q->where('status', $s))
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn($t) => [
                'id'             => $t->id,
                'invoice_number' => $t->invoice_number,
                'cashier_name'   => $t->cashier->name,
                'subtotal'       => $t->subtotal,
                'discount'       => $t->discount,
                'total'          => $t->total,
                'payment_method' => $t->payment_method,
                'status'         => $t->status,
                'items_count'    => $t->items->count(),
                'created_at'     => $t->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Transactions/Index', [
            'transactions' => $transactions,
            'filters'      => $request->only(['search', 'date_from', 'date_to', 'payment', 'status']),
            'isAdmin'      => $isAdmin,
        ]);
    }

    public function show(Transaction $transaction): Response
    {
        $transaction->load(['cashier:id,name', 'items.product:id,name,sku']);

        return Inertia::render('Transactions/Show', [
            'transaction' => [
                'id'             => $transaction->id,
                'invoice_number' => $transaction->invoice_number,
                'cashier_name'   => $transaction->cashier->name,
                'subtotal'       => $transaction->subtotal,
                'discount'       => $transaction->discount,
                'tax'            => $transaction->tax,
                'total'          => $transaction->total,
                'paid_amount'    => $transaction->paid_amount,
                'change_amount'  => $transaction->change_amount,
                'payment_method' => $transaction->payment_method,
                'status'         => $transaction->status,
                'notes'          => $transaction->notes,
                'created_at'     => $transaction->created_at->format('d/m/Y H:i:s'),
                'items'          => $transaction->items->map(fn($item) => [
                    'id'           => $item->id,
                    'product_name' => $item->product_name,
                    'product_sku'  => $item->product_sku,
                    'price'        => $item->price,
                    'qty'          => $item->qty,
                    'discount'     => $item->discount,
                    'subtotal'     => $item->subtotal,
                ]),
            ],
        ]);
    }

    public function void(Transaction $transaction): RedirectResponse
    {
        if ($transaction->status === 'voided') {
            return back()->with('error', 'Transaksi sudah di-void sebelumnya.');
        }

        $transaction->update(['status' => 'voided']);

        // Kembalikan stok
        foreach ($transaction->items as $item) {
            if ($item->product_id) {
                $product = $item->product;
                $before  = $product->stock;
                $product->increment('stock', $item->qty);

                \App\Models\StockLog::create([
                    'product_id'     => $product->id,
                    'user_id'        => auth()->id(),
                    'transaction_id' => $transaction->id,
                    'type'           => 'in',
                    'qty_before'     => $before,
                    'qty_change'     => $item->qty,
                    'qty_after'      => $before + $item->qty,
                    'notes'          => "Void transaksi #{$transaction->invoice_number}",
                ]);
            }
        }

        ActivityLog::log('Void Transaksi', "Membatalkan transaksi {$transaction->invoice_number}.");

        return back()->with('success', 'Transaksi berhasil di-void dan stok dikembalikan.');
    }
}
