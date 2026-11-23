<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Transaction;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $user  = $request->user();
        $today = Carbon::today();
        $isAdmin = $user->role === 'admin';

        // Today's summary
        $todayQuery = Transaction::where('status', 'completed')
            ->whereDate('created_at', $today)
            ->when(!$isAdmin, fn($q) => $q->where('cashier_id', $user->id));

        $todaySales  = (clone $todayQuery)->sum('total');
        $todayCount  = (clone $todayQuery)->count();
        $todayItems  = (clone $todayQuery)
            ->with('items')
            ->get()
            ->sum(fn($t) => $t->items->sum('qty'));

        $stats = [
            'today_sales'       => $todaySales,
            'today_transactions' => $todayCount,
            'today_items'       => $todayItems,
            'low_stock_count'   => Product::where('is_active', true)
                ->whereColumn('stock', '<=', 'min_stock')
                ->count(),
        ];

        // Admin-only: chart data (last 7 days)
        $chartData = null;
        if ($isAdmin) {
            $labels = [];
            $salesData = [];
            for ($i = 6; $i >= 0; $i--) {
                $date = Carbon::today()->subDays($i);
                $labels[]    = $date->format('d M');
                $salesData[] = Transaction::where('status', 'completed')
                    ->whereDate('created_at', $date)
                    ->sum('total');
            }

            // Top 5 products this month (Optimized to database-level query)
            $topProducts = \App\Models\TransactionItem::whereHas('transaction', fn($q) =>
                    $q->where('status', 'completed')
                      ->whereMonth('created_at', $today->month)
                      ->whereYear('created_at', $today->year)
                )
                ->selectRaw('product_name as name, SUM(qty) as qty, SUM(subtotal) as total')
                ->groupBy('product_name')
                ->orderByDesc('qty')
                ->take(5)
                ->get();

            $chartData = [
                'labels'     => $labels,
                'sales'      => $salesData,
                'topProducts' => $topProducts,
            ];

            // Monthly revenue
            $stats['month_sales'] = Transaction::where('status', 'completed')
                ->whereMonth('created_at', $today->month)
                ->whereYear('created_at', $today->year)
                ->sum('total');
            $stats['total_products'] = Product::where('is_active', true)->count();
        }

        // Recent transactions
        $recentTransactions = Transaction::with(['cashier', 'items'])
            ->where('status', 'completed')
            ->when(!$isAdmin, fn($q) => $q
                ->where('cashier_id', $user->id)
                ->whereDate('created_at', $today)
            )
            ->latest()
            ->take(8)
            ->get()
            ->map(fn($t) => [
                'id'             => $t->id,
                'invoice_number' => $t->invoice_number,
                'total'          => $t->total,
                'payment_method' => $t->payment_method,
                'items_count'    => $t->items->count(),
                'cashier_name'   => $t->cashier->name,
                'created_at'     => $t->created_at->format('H:i'),
            ]);

        return Inertia::render('Dashboard', [
            'stats'              => $stats,
            'chartData'          => $chartData,
            'recentTransactions' => $recentTransactions,
            'isAdmin'            => $isAdmin,
        ]);
    }
}
