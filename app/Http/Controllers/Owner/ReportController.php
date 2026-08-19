<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\TransactionPayment;
use App\Models\UpsellEvent;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
        //
        // Totalnya dihitung dengan agregat, bukan dengan menjumlahkan koleksi
        // yang sudah dimuat: daftar transaksinya kini ditunda ([BL-037]), jadi
        // koleksi itu tidak ada lagi di sini — dan SUM di basis data memang
        // lebih murah daripada memuat setiap baris beserta item serta
        // pembayarannya hanya untuk dijumlahkan di PHP.
        $completed = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveDate($date);

        $totalRevenue = (clone $completed)->sum('total_amount');
        $totalTransactions = (clone $completed)->count();
        $voidedCount = Transaction::where('status', Transaction::STATUS_VOIDED)
            ->whereEffectiveDate($date)
            ->count();

        $tenantId = auth()->user()->tenant_id;

        return Inertia::render('Owner/Reports/Daily', [
            'date' => $date,
            // Ringkasan tetap eager: tiga angka inilah yang dicari owner saat
            // membuka laporan, dan ketiganya hanya agregat.
            'summary' => [
                'total_revenue' => $totalRevenue,
                'total_transactions' => $totalTransactions,
                'voided_count' => $voidedCount,
            ],

            // --- Bagian yang ditunda ([BL-037]) ---
            // Daftar transaksi lengkap (dengan item dan pembayaran tiap baris)
            // dipisah dari dua rekap agregat: rekapnya hampir selalu sampai
            // lebih dulu dan langsung terbaca, tanpa menunggu daftar panjang di
            // bawahnya. Kerangka pemuatan tiap bagian ada di
            // Owner/Reports/Daily.vue.
            'transactions' => Inertia::defer(fn () => Transaction::with(['user:id,name', 'items', 'payments.paymentMethod'])
                ->where('status', Transaction::STATUS_COMPLETED)
                ->whereEffectiveDate($date)
                ->latest()
                ->get()),

            // Rekap per metode pembayaran
            'paymentSummary' => Inertia::defer(fn () => TransactionPayment::query()
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
                ->get(), 'rekap'),

            // Produk terlaris hari itu
            'topProducts' => Inertia::defer(fn () => TransactionItem::query()
                ->selectRaw('variant_name, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
                ->whereHas('transaction', function ($q) use ($date) {
                    $q->where('status', Transaction::STATUS_COMPLETED)
                        ->whereEffectiveDate($date);
                })
                ->groupBy('variant_name')
                ->orderByDesc('total_qty')
                ->take(10)
                ->get(), 'rekap'),

            // Potongan harga hari itu ([BL-018]).
            'discountSummary' => Inertia::defer(fn () => $this->discountSummary($date), 'rekap'),
        ]);
    }

    /**
     * Berapa yang dipotong hari itu, dan berapa yang benar-benar DIKORBANKAN.
     *
     * Dua angka, dan memisahkannya adalah permintaan eksplisit `[BL-018]`:
     *
     *   `discounted` — seluruh potongan, termasuk yang tetap di atas lantai
     *     margin. Ini "berapa yang kita korbankan untuk menghabiskan stok".
     *   `below_floor` — bagian yang dijual DI BAWAH lantai untung, yang tiap
     *     barisnya butuh persetujuan owner dan alasan tertulis.
     *
     * Angka kedua yang paling ingin dilihat owner, dan tanpa pemisahan ini ia
     * tenggelam di dalam angka pertama — sebuah penjualan rugi terlihat persis
     * seperti diskon 5% yang sehat.
     *
     * `discount_amount` adalah potongan PER UNIT, jadi ia dikali `qty`.
     *
     * @return array<string, mixed>
     */
    private function discountSummary(string $date): array
    {
        $scoped = fn () => TransactionItem::query()
            ->whereHas('transaction', function ($q) use ($date) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveDate($date);
            })
            ->where('discount_amount', '>', 0);

        $totalGiven = (float) (clone $scoped())->selectRaw('SUM(discount_amount * qty) as total')->value('total');

        $belowFloor = (clone $scoped())->whereNotNull('below_floor_approved_by');

        return [
            'total_given' => $totalGiven,
            'items_discounted' => (clone $scoped())->count(),
            'below_floor_total' => (float) (clone $belowFloor)->selectRaw('SUM(discount_amount * qty) as total')->value('total'),
            'below_floor_items' => (clone $belowFloor)->count(),
            // Barisnya sendiri, supaya owner bisa melihat APA yang dijual rugi
            // dan dengan alasan apa — bukan cuma jumlahnya.
            'below_floor_lines' => (clone $belowFloor)
                ->with('belowFloorApprover:id,name')
                ->get(['id', 'variant_name', 'qty', 'unit_price', 'original_unit_price', 'discount_amount', 'discount_reason', 'margin_floor_at_sale', 'below_floor_approved_by'])
                ->map(fn (TransactionItem $item) => [
                    'variant_name' => $item->variant_name,
                    'qty' => $item->qty,
                    'unit_price' => (float) $item->unit_price,
                    'original_unit_price' => (float) $item->original_unit_price,
                    'floor' => (float) $item->margin_floor_at_sale,
                    'reason' => $item->discount_reason,
                    'approved_by' => $item->belowFloorApprover?->name,
                ])
                ->all(),
        ];
    }

    /**
     * Rekap penjualan satu bulan kalender.
     *
     * Bulan KALENDER, bukan periode langganan. Keduanya masuk akal, tapi ini
     * laporan operasional — satuan yang dipakai pemilik toko untuk menghitung
     * sewa, gaji, dan setoran adalah tanggal 1 sampai akhir bulan. Periode
     * langganan (berjangkar di tanggal daftar) tetap milik halaman tagihan,
     * dan sengaja tidak dicampur di layar ini.
     *
     * Semua angka diagregasi di database. daily() boleh menarik transaksinya
     * satu per satu karena sehari muat di memori; sebulan di tenant yang ramai
     * tidak, jadi di sini tidak ada satu pun baris transaksi yang di-hydrate.
     */
    public function monthly(Request $request): Response
    {
        $month = $this->resolveMonth($request->input('month'));
        $previous = $month->copy()->subMonth();

        $dailySeries = $this->dailySeriesFor($month);
        $summary = $this->summarizeSeries($dailySeries);
        $previousTotals = $this->monthTotals($previous);

        return Inertia::render('Owner/Reports/Monthly', [
            'month' => $month->format('Y-m'),
            'monthLabel' => $this->monthLabel($month),
            'range' => [
                'from' => $month->toDateString(),
                'to' => $month->copy()->endOfMonth()->toDateString(),
            ],
            'summary' => $summary,
            'comparison' => [
                'month' => $previous->format('Y-m'),
                'label' => $this->monthLabel($previous),
                'total_revenue' => $previousTotals['total_revenue'],
                'total_transactions' => $previousTotals['total_transactions'],
                'revenue_delta_pct' => $this->deltaPercent($summary['total_revenue'], $previousTotals['total_revenue']),
                'transactions_delta_pct' => $this->deltaPercent($summary['total_transactions'], $previousTotals['total_transactions']),
            ],
            // Deret harian tetap eager meski [BL-037] mengusulkan sebaliknya:
            // ringkasan di atas diturunkan DARI deret ini, jadi kuerinya sudah
            // terlanjur jalan untuk cat pertama. Menundanya hanya memindahkan
            // 31 baris kecil ke permintaan kedua tanpa mempercepat apa pun —
            // dan grafiknya lalu berkedip untuk data yang sudah ada di tangan.
            'dailySeries' => $dailySeries,

            // --- Bagian yang ditunda ([BL-037]) ---
            // Dua rekap inilah yang benar-benar mahal di layar ini: keduanya
            // menyisir seluruh pembayaran dan item transaksi sebulan penuh.
            // Satu grup, karena keduanya sama-sama tabel di bawah lipatan dan
            // tidak ada gunanya sampai bergiliran.
            'paymentSummary' => Inertia::defer(fn () => $this->paymentSummaryFor($month), 'rekap'),
            'topProducts' => Inertia::defer(fn () => $this->topProductsFor($month), 'rekap'),
        ]);
    }

    /**
     * Unduhan CSV dari rekap bulanan yang sedang dilihat.
     *
     * Isinya persis yang ada di layar, dalam empat blok bersekat: ringkasan,
     * rincian harian, metode pembayaran, dan produk terlaris. Rekap bulanan
     * yang tidak bisa dibawa ke spreadsheet akan tetap disalin dengan tangan.
     */
    public function monthlyExport(Request $request): StreamedResponse
    {
        $month = $this->resolveMonth($request->input('month'));

        $dailySeries = $this->dailySeriesFor($month);
        $summary = $this->summarizeSeries($dailySeries);
        $paymentSummary = $this->paymentSummaryFor($month);
        $topProducts = $this->topProductsFor($month);
        $label = $this->monthLabel($month);

        return response()->streamDownload(function () use ($label, $summary, $dailySeries, $paymentSummary, $topProducts) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8: tanpa ini Excel membaca CSV-nya sebagai ANSI dan nama
            // produk beraksen berubah jadi karakter aneh.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['RINGKASAN']);
            fputcsv($out, ['Bulan', $label]);
            fputcsv($out, ['Omzet', $summary['total_revenue']]);
            fputcsv($out, ['Transaksi selesai', $summary['total_transactions']]);
            fputcsv($out, ['Rata-rata per transaksi', $summary['average_transaction']]);
            fputcsv($out, ['Transaksi void', $summary['voided_count']]);
            fputcsv($out, ['Hari berjualan', $summary['active_days']]);
            fputcsv($out, ['Rata-rata omzet per hari berjualan', $summary['average_active_day_revenue']]);
            fputcsv($out, []);

            fputcsv($out, ['RINCIAN HARIAN']);
            fputcsv($out, ['Tanggal', 'Transaksi', 'Omzet', 'Void']);
            foreach ($dailySeries as $day) {
                fputcsv($out, [$day['date'], $day['count'], $day['revenue'], $day['voided']]);
            }
            fputcsv($out, ['TOTAL', $summary['total_transactions'], $summary['total_revenue'], $summary['voided_count']]);
            fputcsv($out, []);

            fputcsv($out, ['METODE PEMBAYARAN']);
            fputcsv($out, ['Metode', 'Tipe', 'Total']);
            foreach ($paymentSummary as $row) {
                fputcsv($out, [$row->name, $row->type, $row->total]);
            }
            fputcsv($out, []);

            fputcsv($out, ['PRODUK TERLARIS']);
            fputcsv($out, ['Varian', 'Qty Terjual', 'Omzet']);
            foreach ($topProducts as $row) {
                fputcsv($out, [$row->variant_name, $row->total_qty, $row->total_revenue]);
            }

            fclose($out);
        }, 'laporan-bulanan-'.$month->format('Y-m').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Awal bulan dari parameter `month` (format `Y-m`).
     *
     * Parameter yang tidak terbaca jatuh ke bulan berjalan, bukan 422: ini
     * penyaring tampilan, dan laporan bulan ini lebih berguna daripada layar galat.
     */
    private function resolveMonth(?string $month): Carbon
    {
        if (is_string($month) && preg_match('/^\d{4}-\d{2}$/', $month) === 1) {
            try {
                return Carbon::createFromFormat('Y-m-d', $month.'-01')->startOfMonth();
            } catch (\Throwable) {
                // Bulan yang tidak masuk akal (mis. 2026-13) jatuh ke bawah.
            }
        }

        return Carbon::now()->startOfMonth();
    }

    /**
     * Deret harian satu bulan penuh; tanggal tanpa transaksi tetap muncul nol.
     *
     * Nol yang eksplisit itu yang membuat deretnya jujur: hari tutup yang
     * hilang dari deret akan tersambung jadi garis lurus di grafik dan
     * terbaca seolah toko tetap ramai.
     *
     * @return array<int, array{date: string, count: int, revenue: float, voided: int}>
     */
    private function dailySeriesFor(Carbon $month): array
    {
        $effectiveDate = Transaction::effectiveDateSql();
        $completed = Transaction::STATUS_COMPLETED;
        $voided = Transaction::STATUS_VOIDED;

        $rows = Transaction::query()
            ->whereEffectiveBetween($month, $month->copy()->endOfMonth())
            ->whereIn('status', [$completed, $voided])
            ->selectRaw("DATE({$effectiveDate}) as date")
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as tx_count', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN total_amount ELSE 0 END) as revenue', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as voided_count', [$voided])
            ->groupByRaw("DATE({$effectiveDate})")
            ->get()
            ->keyBy('date');

        $series = [];
        $cursor = $month->copy();
        $lastDay = $month->copy()->endOfMonth();

        while ($cursor->lte($lastDay)) {
            $date = $cursor->toDateString();
            $row = $rows->get($date);

            $series[] = [
                'date' => $date,
                'count' => (int) ($row->tx_count ?? 0),
                'revenue' => (float) ($row->revenue ?? 0),
                'voided' => (int) ($row->voided_count ?? 0),
            ];

            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Angka ringkasan bulan, diturunkan dari deret harian yang sudah diagregasi.
     *
     * Menjumlahkan 28–31 baris di PHP jauh lebih murah daripada mengirim
     * query agregat baru untuk tiap angka.
     *
     * @param  array<int, array{date: string, count: int, revenue: float, voided: int}>  $series
     * @return array{total_revenue: float, total_transactions: int, voided_count: int, average_transaction: float, days_in_month: int, active_days: int, average_active_day_revenue: float, best_day: array{date: string, revenue: float, count: int}|null}
     */
    private function summarizeSeries(array $series): array
    {
        $totalRevenue = (float) array_sum(array_column($series, 'revenue'));
        $totalTransactions = (int) array_sum(array_column($series, 'count'));
        $voidedCount = (int) array_sum(array_column($series, 'voided'));

        $activeDays = array_values(array_filter($series, fn (array $day) => $day['count'] > 0));
        $bestDay = null;

        foreach ($activeDays as $day) {
            if ($bestDay === null || $day['revenue'] > $bestDay['revenue']) {
                $bestDay = ['date' => $day['date'], 'revenue' => $day['revenue'], 'count' => $day['count']];
            }
        }

        return [
            'total_revenue' => $totalRevenue,
            'total_transactions' => $totalTransactions,
            'voided_count' => $voidedCount,
            'average_transaction' => $totalTransactions > 0 ? round($totalRevenue / $totalTransactions, 2) : 0.0,
            'days_in_month' => count($series),
            // Hari berjualan, bukan jumlah hari kalender: toko yang libur enam
            // hari tidak boleh terbaca seperti toko yang sepi sebulan penuh.
            'active_days' => count($activeDays),
            'average_active_day_revenue' => count($activeDays) > 0 ? round($totalRevenue / count($activeDays), 2) : 0.0,
            'best_day' => $bestDay,
        ];
    }

    /**
     * Omzet dan jumlah transaksi satu bulan — dipakai sebagai pembanding.
     *
     * @return array{total_revenue: float, total_transactions: int}
     */
    private function monthTotals(Carbon $month): array
    {
        $row = Transaction::query()
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween($month, $month->copy()->endOfMonth())
            ->selectRaw('COUNT(*) as total_transactions, COALESCE(SUM(total_amount), 0) as total_revenue')
            ->first();

        return [
            'total_revenue' => (float) $row->total_revenue,
            'total_transactions' => (int) $row->total_transactions,
        ];
    }

    /**
     * Selisih persen terhadap bulan sebelumnya.
     *
     * null bila pembandingnya nol: tumbuh dari nol bukan "naik 100%",
     * melainkan tidak punya pembanding — dan layarnya harus bilang begitu.
     */
    private function deltaPercent(float|int $current, float|int $previous): ?float
    {
        if ((float) $previous === 0.0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Rekap per metode pembayaran dalam satu bulan.
     *
     * Bentuknya sengaja sama dengan daily() supaya tabelnya bisa dibaca
     * dengan kebiasaan yang sama.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function paymentSummaryFor(Carbon $month)
    {
        $tenantId = auth()->user()->tenant_id;
        $endOfMonth = $month->copy()->endOfMonth();

        return TransactionPayment::query()
            ->selectRaw('payment_methods.name, payment_methods.type, SUM(transaction_payments.amount) as total')
            ->join('payment_methods', function ($join) use ($tenantId) {
                $join->on('transaction_payments.payment_method_id', '=', 'payment_methods.id')
                    ->where('payment_methods.tenant_id', $tenantId);
            })
            ->whereHas('transaction', function ($q) use ($month, $endOfMonth) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveBetween($month, $endOfMonth);
            })
            ->groupBy('payment_methods.name', 'payment_methods.type')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Produk terlaris dalam satu bulan.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function topProductsFor(Carbon $month)
    {
        $endOfMonth = $month->copy()->endOfMonth();

        return TransactionItem::query()
            ->selectRaw('variant_name, SUM(qty) as total_qty, SUM(subtotal) as total_revenue')
            ->whereHas('transaction', function ($q) use ($month, $endOfMonth) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveBetween($month, $endOfMonth);
            })
            ->groupBy('variant_name')
            ->orderByDesc('total_qty')
            ->take(10)
            ->get();
    }

    /**
     * Label bulan berbahasa Indonesia, mis. "Agustus 2026".
     */
    private function monthLabel(Carbon $month): string
    {
        return $month->locale('id')->translatedFormat('F Y');
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

        return Inertia::render('Owner/Transactions/Index', [
            // Ditunda ([BL-037]): filter dan tombolnya bisa langsung dipakai
            // sementara satu halaman transaksi beserta kasir dan pembayarannya
            // masih dimuat. Karena mengubah filter mengirim ulang seluruh
            // kunjungan, kerangka tabel juga muncul kembali setiap filter
            // berubah — dan itu memang yang diinginkan: ada tanda bahwa isi
            // tabel sedang diganti, bukan tabel lama yang diam-diam tertinggal.
            'transactions' => Inertia::defer(fn () => $query->paginate(25)),
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
     * Laporan konversi saran upsell.
     *
     * Menjawab persis pertanyaan yang akan datang di pitching berikutnya:
     * apakah saran ini menaikkan penjualan, atau hanya memperlambat antrean?
     * Rincian per jenis ada supaya jenis yang tidak pernah diterima bisa
     * dimatikan lewat `config/upsell.php`, bukan ditebak.
     */
    public function upsell(Request $request): Response
    {
        $from = $request->input('from', now()->subDays(29)->toDateString());
        $to = $request->input('to', now()->toDateString());

        $scoped = fn () => UpsellEvent::query()
            ->whereDate('created_at', '>=', $from)
            ->whereDate('created_at', '<=', $to);

        $shown = $scoped()->count();
        $accepted = $scoped()->where('status', UpsellEvent::STATUS_ACCEPTED)->count();
        $rejected = $scoped()->where('status', UpsellEvent::STATUS_REJECTED)->count();
        $extraRevenue = (float) $scoped()->where('status', UpsellEvent::STATUS_ACCEPTED)->sum('extra_amount');

        // Dua angka berbeda, dan membedakannya adalah inti [BL-025]:
        //
        //   conversion_rate — dari SEMUA yang tampil. Menjawab "seberapa sering
        //     saran berujung penjualan", termasuk yang cuma lewat.
        //   offer_rate      — dari yang BENAR-BENAR ditawarkan ke pelanggan
        //     (diterima + ditolak). Menjawab "kalau kasir menawarkan, seberapa
        //     sering pelanggan mau" — dan hanya angka ini yang bisa menilai
        //     sarannya sendiri, bukan kedisiplinan kasirnya.
        $offered = $accepted + $rejected;

        return Inertia::render('Owner/Reports/Upsell', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => [
                'shown' => $shown,
                'accepted' => $accepted,
                'rejected' => $rejected,
                'offered' => $offered,
                'conversion_rate' => $shown > 0 ? round($accepted / $shown * 100, 1) : 0,
                'offer_rate' => $offered > 0 ? round($accepted / $offered * 100, 1) : 0,
                'extra_revenue' => $extraRevenue,
            ],
            // --- Bagian yang ditunda ([BL-037]) ---
            // Ringkasan di atas sudah menjawab pertanyaan utama halaman ini;
            // ketiga rincian di bawahnya hanya dibaca ketika angka ringkasannya
            // memancing pertanyaan lanjutan. Dua rekap bersebelahan dijadikan
            // satu grup supaya keduanya muncul bersamaan, bukan bergiliran.
            'byType' => Inertia::defer(fn () => $this->upsellBreakdown($scoped(), 'type'), 'rekap'),
            'bySurface' => Inertia::defer(fn () => $this->upsellBreakdown($scoped(), 'surface'), 'rekap'),
            'topSuggestions' => Inertia::defer(fn () => $scoped()
                ->selectRaw('label, type, COUNT(*) as shown')
                ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted', [UpsellEvent::STATUS_ACCEPTED])
                ->selectRaw('SUM(extra_amount) as extra_revenue')
                ->groupBy('label', 'type')
                ->orderByDesc('shown')
                ->take(15)
                ->get()),
        ]);
    }

    /**
     * Rekap tampil/diterima/omzet untuk satu kolom pengelompokan.
     *
     * @param  \Illuminate\Database\Eloquent\Builder<UpsellEvent>  $query
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function upsellBreakdown($query, string $column)
    {
        return $query
            ->selectRaw("{$column} as bucket, COUNT(*) as shown")
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted', [UpsellEvent::STATUS_ACCEPTED])
            ->selectRaw('SUM(extra_amount) as extra_revenue')
            ->groupBy($column)
            ->orderByDesc('shown')
            ->get();
    }

    /**
     * Riwayat sesi kas.
     */
    public function cashDrawers(Request $request): Response
    {
        return Inertia::render('Owner/CashDrawers/Index', [
            // Ditunda ([BL-037]): satu halaman sesi kas beserta kasirnya, dan
            // tidak ada apa pun di layar ini yang bisa dikerjakan sebelum
            // barisnya sampai.
            'cashDrawers' => Inertia::defer(fn () => CashDrawer::with('user:id,name')
                ->latest('opened_at')
                ->paginate(25)),
        ]);
    }
}
