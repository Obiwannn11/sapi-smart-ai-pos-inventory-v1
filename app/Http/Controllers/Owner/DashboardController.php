<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\BadgeHelperService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private BadgeHelperService $badgeHelper
    ) {}

    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        $today = now()->toDateString();

        // --- Metrics Hari Ini ---
        $todayTransactions = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', $today);

        $todayRevenue = (clone $todayTransactions)->sum('total_amount');
        $todayCount = (clone $todayTransactions)->count();
        $todayAverage = $todayCount > 0 ? $todayRevenue / $todayCount : 0;

        // Pendapatan per metode pembayaran hari ini
        $tenantId = auth()->user()->tenant_id;
        $todayByPaymentMethod = TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', function ($join) use ($tenantId) {
                $join->on('transaction_payments.payment_method_id', '=', 'payment_methods.id')
                    ->where('payment_methods.tenant_id', $tenantId);
            })
            ->whereHas('transaction', function ($q) use ($today) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereDate('created_at', $today);
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        // --- Metrics Minggu Ini ---
        $weekStart = now()->startOfWeek()->toDateString();
        $weekRevenue = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', $weekStart)
            ->sum('total_amount');

        // --- Trend 7 hari ---
        $dailyTrend = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereDate('created_at', '>=', now()->subDays(6)->toDateString())
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(total_amount) as revenue')
            ->groupByRaw('DATE(created_at)')
            ->orderBy('date')
            ->get();

        // --- Badges ---
        $badges = $this->badgeHelper->generate($tenant);

        // --- Transaksi Terbaru ---
        $recentTransactions = Transaction::with('user:id,name')
            ->where('status', Transaction::STATUS_COMPLETED)
            ->latest()
            ->take(5)
            ->get(['id', 'code', 'total_amount', 'user_id', 'created_at', 'source']);

        return Inertia::render('Owner/Dashboard', [
            'metrics' => [
                'today_revenue' => $todayRevenue,
                'today_count' => $todayCount,
                'today_average' => round($todayAverage),
                'week_revenue' => $weekRevenue,
                'today_by_payment_method' => $todayByPaymentMethod,
            ],
            'dailyTrend' => $dailyTrend,
            'badges' => $badges,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
