<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function index(Request $request): Response
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay()
                                        : Carbon::today()->startOfMonth();
        $dateTo   = $request->date_to   ? Carbon::parse($request->date_to)->endOfDay()
                                        : Carbon::today()->endOfDay();

        // Summary
        $baseQuery = Transaction::where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo]);

        $totalRevenue = (clone $baseQuery)->sum('total');
        // Calculate total purchase expenses in the same date range
        $totalExpense = \App\Models\Purchase::whereBetween('date', [$dateFrom->format('Y-m-d'), $dateTo->format('Y-m-d')])
            ->sum('total_amount');

        $summary = [
            'total_revenue'      => $totalRevenue,
            'total_transactions' => (clone $baseQuery)->count(),
            'total_discount'     => (clone $baseQuery)->sum('discount'),
            'avg_transaction'    => (clone $baseQuery)->avg('total') ?? 0,
            'total_expense'      => $totalExpense,
            'net_profit'         => $totalRevenue - $totalExpense,
        ];

        // Chart — daily sales in range (Optimized: 1 query instead of N queries)
        $dailySales = (clone $baseQuery)
            ->selectRaw('DATE(created_at) as date_only, SUM(total) as total')
            ->groupBy('date_only')
            ->pluck('total', 'date_only');

        $days    = [];
        $current = $dateFrom->copy();
        while ($current <= $dateTo) {
            $dateStr = $current->format('Y-m-d');
            $days[] = [
                'date'  => $current->format('d M'),
                'total' => $dailySales->get($dateStr) ?? 0,
            ];
            $current->addDay();
        }
        // Limit chart to 31 days max
        if (count($days) > 31) {
            $days = array_slice($days, 0, 31);
        }

        // Payment method breakdown
        $paymentBreakdown = (clone $baseQuery)
            ->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total')
            ->groupBy('payment_method')
            ->get();

        // Top products
        $topProducts = TransactionItem::whereHas('transaction', fn($q) =>
                $q->where('status', 'completed')
                  ->whereBetween('created_at', [$dateFrom, $dateTo])
            )
            ->selectRaw('product_name, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
            ->groupBy('product_name')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get();

        // Daily transactions list (paginated)
        $transactions = Transaction::with('cashier:id,name')
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->paginate(15)
            ->withQueryString()
            ->through(fn($t) => [
                'id'             => $t->id,
                'invoice_number' => $t->invoice_number,
                'cashier_name'   => $t->cashier->name,
                'total'          => $t->total,
                'payment_method' => $t->payment_method,
                'created_at'     => $t->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Reports/Index', [
            'summary'          => $summary,
            'chartDays'        => $days,
            'paymentBreakdown' => $paymentBreakdown,
            'topProducts'      => $topProducts,
            'transactions'     => $transactions,
            'filters'          => [
                'date_from' => $dateFrom->format('Y-m-d'),
                'date_to'   => $dateTo->format('Y-m-d'),
            ],
        ]);
    }

    public function export(Request $request)
    {
        $dateFrom = $request->date_from ? Carbon::parse($request->date_from)->startOfDay()
                                        : Carbon::today()->startOfMonth();
        $dateTo   = $request->date_to   ? Carbon::parse($request->date_to)->endOfDay()
                                        : Carbon::today()->endOfDay();

        $transactions = Transaction::with(['cashier:id,name', 'items'])
            ->where('status', 'completed')
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->get();

        $filename = 'laporan-penjualan-' . $dateFrom->format('Ymd') . '-' . $dateTo->format('Ymd') . '.csv';

        $headers = [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ];

        $callback = function () use ($transactions) {
            $handle = fopen('php://output', 'w');
            fprintf($handle, chr(0xEF) . chr(0xBB) . chr(0xBF)); // BOM UTF-8
            fputcsv($handle, ['Invoice', 'Tanggal', 'Kasir', 'Item', 'Subtotal', 'Diskon', 'Pajak', 'Total', 'Bayar', 'Kembali', 'Metode']);
            foreach ($transactions as $t) {
                fputcsv($handle, [
                    $t->invoice_number,
                    $t->created_at->format('d/m/Y H:i'),
                    $t->cashier->name,
                    $t->items->count(),
                    $t->subtotal,
                    $t->discount,
                    $t->tax,
                    $t->total,
                    $t->paid_amount,
                    $t->change_amount,
                    $t->payment_method,
                ]);
            }
            fclose($handle);
        };

        return response()->stream($callback, 200, $headers);
    }
}
