<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\BadgeHelperService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private BadgeHelperService $badgeHelper,
        private SubscriptionService $subscriptions,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        $today = now()->toDateString();

        // --- Metrics Hari Ini ---
        // Tanggal efektif, bukan created_at: penjualan offline kemarin yang baru
        // tersinkron pagi ini milik KEMARIN, dan tidak boleh menggelembungkan
        // angka hari ini.
        $todayTransactions = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveDate($today);

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
                    ->whereEffectiveDate($today);
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->get();

        // --- Metrics Minggu Ini ---
        $weekStart = now()->startOfWeek()->toDateString();
        $weekRevenue = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveFrom($weekStart)
            ->sum('total_amount');

        // --- Trend 7 hari ---
        $effectiveDate = Transaction::effectiveDateSql();
        $dailyTrend = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveFrom(now()->subDays(6)->toDateString())
            ->selectRaw("DATE({$effectiveDate}) as date, COUNT(*) as count, SUM(total_amount) as revenue")
            ->groupByRaw("DATE({$effectiveDate})")
            ->orderBy('date')
            ->get();

        // --- Badges ---
        $badges = $this->badgeHelper->generate($tenant);

        // --- Ringkasan Langganan ---
        // Ikut ke dashboard karena halaman langganan tidak punya pintu masuk
        // lain: sebelum ini owner hanya sampai ke sana kalau mengetik URL-nya,
        // atau kalau langganannya sudah terlanjur bermasalah dan gerbangnya
        // melempar ke sana. Yang dikirim sengaja hanya seperlunya untuk sebuah
        // ringkasan — rinciannya tetap milik `/langganan`.
        $subscription = $this->subscriptions->ensureFor($tenant);
        $outstanding = $this->subscriptions->outstandingInvoice($tenant);

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
            'subscription' => [
                'status' => $tenant->status,
                'track' => $subscription->pricing_track,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'period_ends_at' => $subscription->current_period_end?->toDateString(),
                'suspends_at' => $this->subscriptions->suspensionDateFor($tenant)?->toDateString(),
                'outstanding' => $outstanding === null ? null : [
                    'amount' => (float) $outstanding->amount,
                    'due_date' => $outstanding->due_date?->toDateString(),
                    'status' => $outstanding->status,
                    'kind' => $outstanding->kind,
                ],
            ],
        ]);
    }
}
