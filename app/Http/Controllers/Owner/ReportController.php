<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    /**
     * Laporan penjualan harian.
     */
    public function daily(Request $request): Response
    {
        $date = $request->input('date', now()->toDateString());

        // Summary transaksi
        $transactions = Transaction::with(['user:id,name', 'items', 'payments.paymentMethod'])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', $date)
            ->latest()
            ->get();

        $totalRevenue = $transactions->sum('total_amount');
        $totalTransactions = $transactions->count();
        $voidedCount = Transaction::where('status', Transaction::STATUS_VOIDED)
            ->whereDate('created_at', $date)
            ->count();

        // Rekap per metode pembayaran
        $paymentSummary = TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', 'transaction_payments.payment_method_id', '=', 'payment_methods.id')
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereDate('created_at', $date);
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        // Produk terlaris hari itu
        $topProducts = TransactionItem::query()
            ->selectRaw('variant_name, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereDate('created_at', $date);
            })
            ->groupBy('variant_name')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get();

        return Inertia::render('Owner/Reports/Daily', [
            'date' => $date,
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'voided_count' => $voidedCount,
            ],
            'transactions' => $transactions,
            'paymentSummary' => $paymentSummary,
            'topProducts' => $topProducts,
        ]);
    }

    /**
     * Riwayat transaksi (semua).
     */
    public function transactions(Request $request): Response
    {
        $query = Transaction::with(['user:id,name', 'payments.paymentMethod'])
            ->latest();

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by date range
        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', $request->from);
        }
        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', $request->to);
        }

        $transactions = $query->paginate(25);

        return Inertia::render('Owner/Transactions/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'from', 'to']),
        ]);
    }

    /**
     * Detail transaksi.
     */
    public function transactionDetail(Transaction $transaction): Response
    {
        $transaction->load([
            'user:id,name',
            'items.modifiers',
            'items.variant:id,name',
            'payments.paymentMethod',
        ]);

        return Inertia::render('Owner/Transactions/Detail', [
            'transaction' => $transaction,
        ]);
    }

    /**
     * Riwayat sesi kas.
     */
    public function cashDrawers(Request $request): Response
    {
        $cashDrawers = CashDrawer::with('user:id,name')
            ->latest('opened_at')
            ->paginate(25);

        return Inertia::render('Owner/CashDrawers/Index', [
            'cashDrawers' => $cashDrawers,
        ]);
    }
}
