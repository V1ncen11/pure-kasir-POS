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

        $filename = 'laporan-penjualan-' . $dateFrom->format('Ymd') . '-' . $dateTo->format('Ymd') . '.xls';

        // Hitung ringkasan
        $totalRevenue = $transactions->sum('total');
        $totalDiscount = $transactions->sum('discount');
        $totalTax = $transactions->sum('tax');
        $totalTransactions = $transactions->count();

        \App\Models\ActivityLog::log('Export Laporan', "Export laporan penjualan: {$dateFrom->format('d/m/Y')} - {$dateTo->format('d/m/Y')} ({$totalTransactions} transaksi)");

        $html  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $html .= '<?mso-application progid="Excel.Sheet"?>' . "\n";
        $html .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet"';
        $html .= ' xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $html .= '<Worksheet ss:Name="Laporan Penjualan">';
        $html .= '<Table>';

        // Title row
        $html .= '<Row><Cell ss:MergeAcross="10"><Data ss:Type="String">LAPORAN PENJUALAN - ' . strtoupper($dateFrom->format('d M Y')) . ' s/d ' . strtoupper($dateTo->format('d M Y')) . '</Data></Cell></Row>';
        $html .= '<Row><Cell ss:MergeAcross="10"><Data ss:Type="String">Total Transaksi: ' . $totalTransactions . ' | Total Omset: Rp ' . number_format($totalRevenue, 0, ',', '.') . ' | Total Diskon: Rp ' . number_format($totalDiscount, 0, ',', '.') . '</Data></Cell></Row>';
        $html .= '<Row></Row>';

        // Header row
        $headers_row = ['No', 'No. Invoice', 'Tanggal', 'Kasir', 'Jumlah Item', 'Subtotal (Rp)', 'Diskon (Rp)', 'Pajak (Rp)', 'Total (Rp)', 'Dibayar (Rp)', 'Metode Bayar'];
        $html .= '<Row>';
        foreach ($headers_row as $h) {
            $html .= '<Cell><Data ss:Type="String">' . htmlspecialchars($h) . '</Data></Cell>';
        }
        $html .= '</Row>';

        // Data rows
        $no = 1;
        foreach ($transactions as $t) {
            $html .= '<Row>';
            $html .= '<Cell><Data ss:Type="Number">' . $no++ . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="String">' . htmlspecialchars($t->invoice_number) . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="String">' . $t->created_at->format('d/m/Y H:i') . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="String">' . htmlspecialchars($t->cashier->name) . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="Number">' . $t->items->count() . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="Number">' . $t->subtotal . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="Number">' . $t->discount . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="Number">' . $t->tax . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="Number">' . $t->total . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="Number">' . $t->paid_amount . '</Data></Cell>';
            $html .= '<Cell><Data ss:Type="String">' . strtoupper($t->payment_method) . '</Data></Cell>';
            $html .= '</Row>';
        }

        $html .= '</Table></Worksheet></Workbook>';

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-excel',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }
}
