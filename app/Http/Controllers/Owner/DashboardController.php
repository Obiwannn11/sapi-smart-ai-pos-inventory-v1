<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Services\BadgeHelperService;
use App\Services\BusinessClock;
use App\Services\StockRescueService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __construct(
        private BadgeHelperService $badgeHelper,
        private SubscriptionService $subscriptions,
        private StockRescueService $stockRescue,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = auth()->user()->tenant;
        $today = BusinessClock::today();

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
        $weekStart = BusinessClock::startOfWeek();
        $weekRevenue = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveFrom($weekStart)
            ->sum('total_amount');

        // Tren, badge, dan transaksi terakhir sengaja TIDAK dihitung di sini —
        // lihat Inertia::defer() di bawah.

        // --- Ringkasan Langganan ---
        // Ikut ke dashboard karena halaman langganan tidak punya pintu masuk
        // lain: sebelum ini owner hanya sampai ke sana kalau mengetik URL-nya,
        // atau kalau langganannya sudah terlanjur bermasalah dan gerbangnya
        // melempar ke sana. Yang dikirim sengaja hanya seperlunya untuk sebuah
        // ringkasan — rinciannya tetap milik `/langganan`.
        $subscription = $this->subscriptions->ensureFor($tenant);
        $outstanding = $this->subscriptions->outstandingInvoice($tenant);

        return Inertia::render('Owner/Dashboard', [
            'metrics' => [
                'today_revenue' => $todayRevenue,
                'today_count' => $todayCount,
                'today_average' => round($todayAverage),
                'week_revenue' => $weekRevenue,
                'today_by_payment_method' => $todayByPaymentMethod,
            ],
            // --- Bagian yang ditunda ([BL-037]) ---
            // Tidak satu pun dari ketiganya dibutuhkan untuk cat pertama,
            // sedangkan metrik hari ini di atasnya adalah isi utama layar ini.
            // Selama ini seluruh halaman menunggu kueri paling lambat sebelum
            // muncul sama sekali; sekarang metrik tampil lebih dulu dan ketiga
            // bagian ini punya kerangka pemuatannya sendiri di
            // Owner/Dashboard.vue.
            //
            // Badge dipisah ke grupnya sendiri supaya agregat yang paling berat
            // (BadgeHelperService memeriksa stok, upsell, dan kas sekaligus)
            // tidak menahan tren dan daftar transaksi yang masing-masing hanya
            // satu kueri.
            'dailyTrend' => Inertia::defer(function () {
                $effectiveDate = Transaction::effectiveDateSql();

                return Transaction::where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveFrom(BusinessClock::daysAgo(6))
                    ->selectRaw("DATE({$effectiveDate}) as date, COUNT(*) as count, SUM(total_amount) as revenue")
                    ->groupByRaw("DATE({$effectiveDate})")
                    ->orderBy('date')
                    ->get();
            }),
            'recentTransactions' => Inertia::defer(fn () => Transaction::with('user:id,name')
                ->where('status', Transaction::STATUS_COMPLETED)
                ->latest()
                ->take(5)
                ->get(['id', 'code', 'total_amount', 'user_id', 'created_at', 'source'])),
            'badges' => Inertia::defer(fn () => $this->badgeHelper->generate($tenant), 'badges'),

            // Sinyal pagi ([BL-105] butir 3) — apa yang harus keluar hari ini,
            // dan apakah potongannya sudah terpasang untuk membantunya keluar.
            //
            // Ikut grup 'badges' dengan sengaja: keduanya menyisir stok, dan
            // memisahkannya berarti dua rombongan kueri berat yang tiba
            // bergiliran di bagian layar yang sama.
            'pressedToday' => Inertia::defer(fn () => $this->stockRescue->pressedToday($tenant), 'badges'),
            'subscription' => [
                'status' => $tenant->status,
                'track' => $subscription->pricing_track,
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                'period_ends_at' => $subscription->current_period_end?->toDateString(),
                'suspends_at' => $this->subscriptions->suspensionDateFor($tenant)?->toDateString(),
                // Momen pilihan jalur di akhir masa gratis (`[BL-044]`(c)).
                // `null` di luar jendelanya, dan itu yang membuat kartunya
                // tidak berubah jadi pengumuman permanen — sesuatu yang selalu
                // ada di layar berhenti dibaca jauh sebelum harinya tiba.
                'trial_choice' => $this->subscriptions->trialChoice($tenant),
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
