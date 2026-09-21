<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\Transaction;
use App\Services\BadgeHelperService;
use App\Services\BusinessClock;
use App\Services\PaymentMethodRecap;
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
        private PaymentMethodRecap $paymentRecap,
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

        // --- Metrics Bulan Ini ---
        // Berdampingan dengan angka hari ini, bukan menggantikannya: hari
        // menjawab "bagaimana hari ini berjalan", bulan menjawab "bagaimana
        // bulan ini sejauh ini" — dan keduanya pintu masuk ke laporannya
        // masing-masing. Angka "minggu ini" yang dulu berdiri di sini tidak
        // punya laporan untuk dituju, dan karena itu tidak bisa ditindaklanjuti.
        $monthStart = BusinessClock::startOfMonth();
        $monthTransactions = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveFrom($monthStart);

        $monthRevenue = (clone $monthTransactions)->sum('total_amount');
        $monthCount = (clone $monthTransactions)->count();
        $monthAverage = $monthCount > 0 ? $monthRevenue / $monthCount : 0;

        // Pendapatan per metode pembayaran — hari ini dan bulan ini.
        //
        // Kueri transaksi yang sama dengan yang melahirkan omzet di atasnya
        // diteruskan apa adanya ([BL-109]). Dulu rekap ini membangun penyaring
        // periodenya sendiri, dan karena itu bisa menjawab rentang yang berbeda
        // dari kartu omzet yang berdiri tepat di atasnya.
        $tenantId = auth()->user()->tenant_id;
        $todayByPaymentMethod = $this->paymentRecap->for(clone $todayTransactions, $tenantId);
        $monthByPaymentMethod = $this->paymentRecap->for(clone $monthTransactions, $tenantId);

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
                'month_revenue' => $monthRevenue,
                'month_count' => $monthCount,
                'month_average' => round($monthAverage),
                'today_by_payment_method' => $todayByPaymentMethod,
                'month_by_payment_method' => $monthByPaymentMethod,
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

            // Sisi "Bulan Ini" dari kartu yang sama — pembaca pertama tabel
            // `expired_stock_records` ([BL-105], butir yang tersisa).
            //
            // Ketiga isinya BERPERIODE SAMA, dan itu syarat, bukan kebetulan:
            // sakelar di kartunya menjanjikan "bulan ini", jadi potret hari ini
            // milik `spoiled()` TIDAK boleh ikut ke sini. Yang berperiode beda
            // tinggal di tab sebelahnya, di bawah label "Hari Ini".
            'rescueMonth' => Inertia::defer(fn () => [
                'rescued' => $this->stockRescue->rescued($tenant, $monthStart, $today),
                'spoiled' => $this->stockRescue->spoiledInPeriod($tenant, $monthStart, $today),
                // Pembeda antara "bulan ini tidak ada yang basi" dan
                // "pencatatnya baru berjalan sejak kemarin". Tanpa ini kedua
                // keadaan itu tampil sebagai Rp 0 yang sama persis.
                'recording_started_on' => $this->stockRescue->recordingStartedOn($tenant),
            ], 'badges'),
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
