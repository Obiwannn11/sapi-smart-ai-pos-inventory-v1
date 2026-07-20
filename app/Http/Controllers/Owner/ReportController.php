<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
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

        // Summary transaksi.
        // whereEffectiveDate, bukan created_at: penjualan offline dibuat di server
        // saat sync (bisa esok harinya) — laporan harian harus memakai kapan
        // penjualannya benar-benar terjadi, bukan kapan barisnya masuk.
        $transactions = Transaction::with(['user:id,name', 'items', 'payments.paymentMethod'])
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveDate($date)
            ->latest()
            ->get();

        $totalRevenue = $transactions->sum('total_amount');
        $totalTransactions = $transactions->count();
        $voidedCount = Transaction::where('status', Transaction::STATUS_VOIDED)
            ->whereEffectiveDate($date)
            ->count();

        // Rekap per metode pembayaran
        $tenantId = auth()->user()->tenant_id;
        $paymentSummary = TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', function ($join) use ($tenantId) {
                $join->on('transaction_payments.payment_method_id', '=', 'payment_methods.id')
                    ->where('payment_methods.tenant_id', $tenantId);
            })
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveDate($date);
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        // Produk terlaris hari itu
        $topProducts = TransactionItem::query()
            ->selectRaw('variant_name, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveDate($date);
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

        // Filter by date range — pakai tanggal penjualan sebenarnya.
        if ($request->filled('from')) {
            $query->whereEffectiveFrom($request->from);
        }
        if ($request->filled('to')) {
            $query->whereRaw('DATE('.Transaction::effectiveDateSql().') <= ?', [$request->to]);
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
            'editor:id,name',
            'edits.user:id,name',
        ]);

        return Inertia::render('Owner/Transactions/Detail', [
            'transaction' => $transaction,
            // Katalog untuk modal edit — deferred agar payload awal ringan.
            'products' => Inertia::defer(fn () => $this->editCatalog()),
            'paymentMethods' => Inertia::defer(fn () => PaymentMethod::where('is_active', true)->get()),
        ]);
    }

    /**
     * Katalog produk (varian + modifier) untuk modal edit transaksi.
     * Bentuknya mengikuti POSController@index agar komponen frontend bisa dibagikan.
     */
    private function editCatalog()
    {
        return Product::where('is_active', true)
            ->with([
                'variants' => fn ($q) => $q->select('id', 'product_id', 'name', 'price', 'stock'),
                'modifierGroups.modifiers:id,modifier_group_id,name,extra_price',
                'category:id,name',
            ])
            ->get();
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
