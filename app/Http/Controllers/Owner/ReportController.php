<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CashDrawer;
use App\Models\CashDrawerMovement;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\UpsellEvent;
use App\Services\BusinessClock;
use App\Services\PaymentMethodRecap;
use App\Services\Reports\MonthlyReportWorkbook;
use App\Services\StockRescueService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(
        private StockRescueService $stockRescue,
        private PaymentMethodRecap $paymentRecap,
    ) {}

    /**
     * Laporan penjualan harian.
     */
    public function daily(Request $request): Response
    {
        $date = $request->input('date', BusinessClock::today());

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

        // Dua angka pajak hari itu ([BL-065] butir (e)). Satu agregat, bukan
        // dua: keduanya menyisir baris yang sama persis.
        $taxTotals = (clone $completed)
            ->selectRaw('COALESCE(SUM(subtotal_amount), 0) as net_revenue')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax_collected')
            ->selectRaw('COALESCE(SUM(service_charge_amount), 0) as service_charge_collected')
            ->first();

        $taxCollected = (float) $taxTotals->tax_collected;
        $serviceChargeCollected = (float) $taxTotals->service_charge_collected;

        $tenantId = auth()->user()->tenant_id;

        return Inertia::render('Owner/Reports/Daily', [
            'date' => $date,
            // Ringkasan tetap eager: tiga angka inilah yang dicari owner saat
            // membuka laporan, dan ketiganya hanya agregat.
            'summary' => [
                'total_revenue' => $totalRevenue,
                // Omzet toko dan pajak titipan, terpisah. `total_revenue`
                // TETAP berarti yang dibayar pelanggan — ia tidak berubah arti,
                // dua angka ini yang ditambahkan di sebelahnya.
                'net_revenue' => (float) $taxTotals->net_revenue,
                'tax_collected' => $taxCollected,
                // Angka keempat ([BL-097]). `net_revenue` sudah
                // mengecualikannya tanpa perubahan kueri apa pun, karena
                // ia dibaca dari `subtotal_amount`.
                'service_charge_collected' => $serviceChargeCollected,
                'total_transactions' => $totalTransactions,
                'voided_count' => $voidedCount,
            ],
            'tax' => $this->taxContext(clone $completed, $taxCollected),
            'serviceCharge' => $this->serviceChargeContext(clone $completed, $serviceChargeCollected),

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

            // Rekap per metode pembayaran — lewat pembaca bersama, yang
            // mengurangkan kembalian dari baris tunai ([BL-109]). Kueri yang
            // dioper `$completed` yang sama dengan sumber angka omzet di atas,
            // jadi keduanya tidak mungkin menjawab hari yang berbeda.
            'paymentSummary' => Inertia::defer(
                fn () => $this->paymentRecap->for(clone $completed, $tenantId),
                'rekap'
            ),

            // Produk terlaris hari itu
            'topProducts' => Inertia::defer(fn () => $this->topProducts(
                fn (Builder $q) => $q->whereEffectiveDate($date)
            ), 'rekap'),

            // Potongan harga hari itu ([BL-018]).
            'discountSummary' => Inertia::defer(fn () => $this->discountSummary(
                fn (Builder $q) => $q->whereEffectiveDate($date)
            ), 'rekap'),
        ]);
    }

    /**
     * Berapa yang dipotong dalam satu periode, dan berapa yang benar-benar
     * DIKORBANKAN.
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
     * **Periodenya dioper sebagai closure**, seperti {@see self::topProducts()}:
     * rekap harian dan rekap bulanan harus menghitung himpunan yang sama, dan
     * dua salinan definisi "potongan" akan berbeda suatu hari ([BL-116]).
     *
     * `gross_sales` adalah harga NORMAL barang yang terjual — omzet sebelum
     * potongan. Ia dihitung sebagai `net_sales + total_given`, bukan dari
     * `product_variants.price` hari ini: harga katalog berubah, dan bruto masa
     * lalu yang dihitung dari harga hari ini adalah angka karangan yang
     * terlihat pasti ([BL-116] "Yang JANGAN dilakukan" butir 1). Keduanya
     * mengikuti `transaction_items.subtotal`, jadi modifier ada di dalam
     * kedua angka dan di mode pajak inclusive pajaknya pun ada di dalam
     * keduanya — yang dijamin bukan bebas-pajaknya, melainkan bahwa
     * selisihnya benar-benar potongan dan bukan efek dasar yang berbeda.
     *
     * `$lineLimit` memotong daftar baris rugi untuk periode panjang. Urutannya
     * potongan terbesar lebih dulu — daftar yang dipotong tanpa urutan berarti
     * yang tampil ditentukan kebetulan.
     *
     * **Penjualan offline ikut terhitung sejak `[BL-115]`**: jalur sinkron kini
     * menulis `original_unit_price` dan `discount_amount` seperti jalur online,
     * jadi potongan yang terjadi saat perangkat putus tidak lagi hilang dari
     * angka ini. Yang tetap di luar hanyalah baris yang tersinkron SEBELUM
     * entri itu — jejaknya tidak pernah tercatat dan tidak boleh dikarang.
     *
     * @return array<string, mixed>
     */
    private function discountSummary(\Closure $withinPeriod, ?int $lineLimit = null): array
    {
        $items = fn () => TransactionItem::query()
            ->whereHas('transaction', function (Builder $q) use ($withinPeriod) {
                $withinPeriod($q->where('status', Transaction::STATUS_COMPLETED));
            });

        // Satu baris agregat untuk tiga angka sekaligus. Tiga kueri terpisah
        // menyisir himpunan yang sama tiga kali, dan di rekap bulanan himpunan
        // itu seluruh item sebulan.
        $totals = $items()
            ->selectRaw('COALESCE(SUM(subtotal), 0) as net_sales')
            ->selectRaw('COALESCE(SUM(discount_amount * qty), 0) as total_given')
            ->selectRaw('COALESCE(SUM(CASE WHEN discount_amount > 0 THEN 1 ELSE 0 END), 0) as items_discounted')
            ->first();

        $belowFloor = fn () => $items()->whereNotNull('below_floor_approved_by');

        $floor = $belowFloor()
            ->selectRaw('COALESCE(SUM(discount_amount * qty), 0) as total')
            ->selectRaw('COUNT(*) as items')
            ->first();

        $netSales = (float) $totals->net_sales;
        $totalGiven = (float) $totals->total_given;

        return [
            'gross_sales' => round($netSales + $totalGiven, 2),
            'net_sales' => $netSales,
            'total_given' => $totalGiven,
            'items_discounted' => (int) $totals->items_discounted,
            'below_floor_total' => (float) $floor->total,
            'below_floor_items' => (int) $floor->items,
            // Barisnya sendiri, supaya owner bisa melihat APA yang dijual rugi
            // dan dengan alasan apa — bukan cuma jumlahnya.
            'below_floor_lines' => $belowFloor()
                ->with('belowFloorApprover:id,name')
                ->orderByRaw('discount_amount * qty DESC')
                ->when($lineLimit !== null, fn (Builder $q) => $q->limit($lineLimit))
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
     * Potongan sepanjang satu bulan kalender ([BL-116] butir 1).
     *
     * Daftar baris rugi dibatasi 50: tiap barisnya butuh persetujuan owner satu
     * per satu, jadi jumlahnya memang terbatas manusia — tapi "terbatas
     * manusia" bukan "terbatas", dan `below_floor_items` tetap membawa jumlah
     * sebenarnya supaya layarnya bisa mengaku kalau daftarnya terpotong.
     *
     * @return array<string, mixed>
     */
    private function discountSummaryFor(Carbon $month): array
    {
        $endOfMonth = $month->copy()->endOfMonth();

        return $this->discountSummary(
            fn (Builder $q) => $q->whereEffectiveBetween($month, $endOfMonth),
            lineLimit: 50,
        );
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
            'tax' => $this->taxContext($this->completedIn($month), $summary['tax_collected']),
            'serviceCharge' => $this->serviceChargeContext($this->completedIn($month), $summary['service_charge_collected']),
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

            // Potongan sebulan ([BL-116] butir 1). Ikut ditunda bersama dua
            // rekap di atas: ia menyisir item transaksi sebulan penuh, dan
            // sama-sama tabel di bawah lipatan.
            'discountSummary' => Inertia::defer(fn () => $this->discountSummaryFor($month), 'rekap'),
        ]);
    }

    /**
     * Seluruh isi rekap bulanan, dikumpulkan sekali.
     *
     * Dipakai bersama oleh kedua unduhan. Dua unduhan yang masing-masing
     * mengumpulkan angkanya sendiri adalah dua laporan yang suatu hari akan
     * menjawab berbeda untuk bulan yang sama — dan yang paling mungkin
     * menyimpang justru syarat pemunculan kolom pajak dan biaya layanan, yang
     * ditentukan dari dua hal sekaligus di tiap tempat pemanggilannya.
     *
     * @return array{
     *     month: string,
     *     label: string,
     *     range: array{from: string, to: string},
     *     tenant_name: string,
     *     generated_at: Carbon,
     *     summary: array<string, mixed>,
     *     comparison: array<string, mixed>,
     *     daily_series: array<int, array<string, mixed>>,
     *     payment_summary: array<int, array{id: int, name: string, type: string, total: float}>,
     *     top_products: \Illuminate\Support\Collection<int, array<string, mixed>>,
     *     discount_summary: array<string, mixed>,
     *     tax: array{active: bool, label: string},
     *     service_charge: array{active: bool, label: string}
     * }
     */
    private function monthlyReport(Carbon $month): array
    {
        $dailySeries = $this->dailySeriesFor($month);
        $summary = $this->summarizeSeries($dailySeries);
        $previous = $month->copy()->subMonth();
        $previousTotals = $this->monthTotals($previous);

        return [
            'month' => $month->format('Y-m'),
            'label' => $this->monthLabel($month),
            'range' => [
                'from' => $month->toDateString(),
                'to' => $month->copy()->endOfMonth()->toDateString(),
            ],
            'tenant_name' => auth()->user()->tenant?->name ?? config('app.name'),
            'generated_at' => BusinessClock::now(),
            'summary' => $summary,
            'comparison' => [
                'month' => $previous->format('Y-m'),
                'label' => $this->monthLabel($previous),
                'total_revenue' => $previousTotals['total_revenue'],
                'total_transactions' => $previousTotals['total_transactions'],
                'revenue_delta_pct' => $this->deltaPercent($summary['total_revenue'], $previousTotals['total_revenue']),
                'transactions_delta_pct' => $this->deltaPercent($summary['total_transactions'], $previousTotals['total_transactions']),
            ],
            'daily_series' => $dailySeries,
            'payment_summary' => $this->paymentSummaryFor($month),
            'top_products' => $this->topProductsFor($month),
            'discount_summary' => $this->discountSummaryFor($month),
            'tax' => $this->taxContext($this->completedIn($month), $summary['tax_collected']),
            'service_charge' => $this->serviceChargeContext($this->completedIn($month), $summary['service_charge_collected']),
        ];
    }

    /**
     * Unduhan Excel dari rekap bulanan yang sedang dilihat.
     *
     * Ini unduhan yang dianjurkan, dan CSV di bawah tetap berdiri di sebelahnya.
     * Bedanya bukan ekstensinya: tabel-tabel yang di CSV ditumpuk dengan baris
     * kosong sebagai sekat di sini berdiri di lembarnya masing-masing, dengan
     * satu baris kepala per lembar, angka yang tetap berupa angka, dan tanggal
     * yang benar-benar tanggal. Perakitannya ada di `MonthlyReportWorkbook`.
     */
    public function monthlyExportExcel(Request $request): StreamedResponse
    {
        $month = $this->resolveMonth($request->input('month'));
        $book = (new MonthlyReportWorkbook)->build($this->monthlyReport($month));

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');

            // Buku kerjanya memegang tiap lembarnya di memori sampai dilepas,
            // dan lembar-lembar itu saling menunjuk — tanpa ini, memori satu
            // bulan penuh baru dibebaskan saat proses PHP-nya berakhir.
            $book->disconnectWorksheets();
        }, 'laporan-bulanan-'.$month->format('Y-m').'.xlsx', [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /**
     * Unduhan CSV dari rekap bulanan yang sedang dilihat.
     *
     * Isinya persis yang ada di layar, dalam blok-blok bersekat: ringkasan,
     * rincian harian, metode pembayaran, potongan harga, dan produk terlaris.
     * Bentuk datar tanpa gaya inilah yang membuatnya tetap ada di sebelah
     * unduhan Excel alih-alih digantikan olehnya: yang dimakan
     * pemroses lain — impor ke akuntansi, skrip, spreadsheet yang bukan
     * Excel — adalah file seperti ini.
     */
    public function monthlyExport(Request $request): StreamedResponse
    {
        $month = $this->resolveMonth($request->input('month'));

        $report = $this->monthlyReport($month);
        $label = $report['label'];
        $summary = $report['summary'];
        $dailySeries = $report['daily_series'];
        $paymentSummary = $report['payment_summary'];
        $topProducts = $report['top_products'];
        $discountSummary = $report['discount_summary'];
        $tax = $report['tax'];
        $service = $report['service_charge'];

        return response()->streamDownload(function () use ($label, $summary, $dailySeries, $paymentSummary, $topProducts, $discountSummary, $tax, $service) {
            $out = fopen('php://output', 'w');

            // BOM UTF-8: tanpa ini Excel membaca CSV-nya sebagai ANSI dan nama
            // produk beraksen berubah jadi karakter aneh.
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['RINGKASAN']);
            fputcsv($out, ['Bulan', $label]);
            fputcsv($out, ['Omzet', $summary['total_revenue']]);
            // Kolom pajak hanya muncul untuk tenant yang memang memungut.
            // Kolom nol di setiap baris bukan kejujuran, melainkan derau yang
            // harus dibaca ulang tiap bulan oleh mayoritas yang tidak memungut.
            if ($tax['active'] || $service['active']) {
                // Judulnya menyebut apa saja yang sudah dikeluarkan dari
                // angka ini. Membiarkannya 'Omzet sebelum pajak' saat biaya
                // layanan ikut dikeluarkan berarti menamai angka dengan
                // separuh isinya ([BL-097]).
                fputcsv($out, [
                    $service['active'] ? 'Omzet toko (sebelum biaya layanan dan pajak)' : 'Omzet sebelum pajak',
                    $summary['net_revenue'],
                ]);
            }
            if ($service['active']) {
                fputcsv($out, [$service['label'].' terpungut', $summary['service_charge_collected']]);
            }
            if ($tax['active']) {
                fputcsv($out, [$tax['label'].' terpungut', $summary['tax_collected']]);
            }
            fputcsv($out, ['Transaksi selesai', $summary['total_transactions']]);
            fputcsv($out, ['Rata-rata per transaksi', $summary['average_transaction']]);
            fputcsv($out, ['Transaksi void', $summary['voided_count']]);
            fputcsv($out, ['Hari berjualan', $summary['active_days']]);
            fputcsv($out, ['Rata-rata omzet per hari berjualan', $summary['average_active_day_revenue']]);
            fputcsv($out, []);

            fputcsv($out, ['RINCIAN HARIAN']);
            // Dua kolom opsional yang saling bebas; ternary bersarang di
            // tiga tempat sekaligus akan menyimpang satu sama lain begitu
            // kolom ketiga lahir. Urutannya mengikuti aliran uang:
            // omzet -> biaya layanan -> pajak.
            $optional = [];
            if ($service['active']) {
                $optional[] = ['label' => $service['label'], 'key' => 'service_charge_collected'];
            }
            if ($tax['active']) {
                $optional[] = ['label' => $tax['label'], 'key' => 'tax_collected'];
            }

            fputcsv($out, array_merge(
                ['Tanggal', 'Transaksi', 'Omzet'],
                array_column($optional, 'label'),
                ['Void'],
            ));
            foreach ($dailySeries as $day) {
                fputcsv($out, array_merge(
                    [$day['date'], $day['count'], $day['revenue']],
                    array_map(fn (array $c) => $day[$c['key']], $optional),
                    [$day['voided']],
                ));
            }
            fputcsv($out, array_merge(
                ['TOTAL', $summary['total_transactions'], $summary['total_revenue']],
                array_map(fn (array $c) => $summary[$c['key']], $optional),
                [$summary['voided_count']],
            ));
            fputcsv($out, []);

            fputcsv($out, ['METODE PEMBAYARAN']);
            fputcsv($out, ['Metode', 'Tipe', 'Total']);
            foreach ($paymentSummary as $row) {
                fputcsv($out, [$row['name'], $row['type'], $row['total']]);
            }
            fputcsv($out, []);

            // Potongan harga ([BL-116] butir 1). Seluruh bloknya hilang untuk
            // periode tanpa satu pun potongan, dengan alasan yang sama seperti
            // kolom pajak di atas: blok nol bukan kejujuran, melainkan sesuatu
            // yang harus dibaca ulang tiap bulan oleh mayoritas yang tidak
            // pernah mendiskon.
            $hasDiscounts = $discountSummary['items_discounted'] > 0;

            if ($hasDiscounts) {
                fputcsv($out, ['POTONGAN HARGA']);
                fputcsv($out, ['Harga normal barang terjual', $discountSummary['gross_sales']]);
                fputcsv($out, ['Total dipotong', $discountSummary['total_given']]);
                fputcsv($out, ['Tertagih setelah potongan', $discountSummary['net_sales']]);
                fputcsv($out, ['Baris penjualan berpotongan', $discountSummary['items_discounted']]);
                // Dua baris terakhir yang paling ingin dilihat owner, dan
                // alasannya ada di discountSummary(): penjualan rugi terlihat
                // persis seperti diskon sehat sampai dipisahkan.
                fputcsv($out, ['Di bawah batas untung', $discountSummary['below_floor_total']]);
                fputcsv($out, ['Baris di bawah batas untung', $discountSummary['below_floor_items']]);
                fputcsv($out, []);

                if ($discountSummary['below_floor_lines'] !== []) {
                    fputcsv($out, ['PENJUALAN DI BAWAH LANTAI UNTUNG']);
                    fputcsv($out, ['Barang', 'Qty', 'Harga normal', 'Harga jual', 'Batas', 'Alasan', 'Disetujui']);
                    foreach ($discountSummary['below_floor_lines'] as $line) {
                        fputcsv($out, [
                            $line['variant_name'],
                            $line['qty'],
                            $line['original_unit_price'],
                            $line['unit_price'],
                            $line['floor'],
                            $line['reason'],
                            $line['approved_by'],
                        ]);
                    }
                    // Daftarnya dibatasi 50 baris; kalau terpotong, CSV-nya
                    // mengaku alih-alih membiarkan pembacanya menjumlahkan
                    // sebagian dan menamainya seluruhnya.
                    if ($discountSummary['below_floor_items'] > count($discountSummary['below_floor_lines'])) {
                        fputcsv($out, [
                            'Menampilkan '.count($discountSummary['below_floor_lines'])
                            .' baris potongan terbesar dari '.$discountSummary['below_floor_items'].' baris',
                        ]);
                    }
                    fputcsv($out, []);
                }
            }

            // Satu baris per VARIAN, dengan peringkat dan nama produknya
            // diulang di tiap baris. Barisnya sengaja tidak dicampur dengan
            // baris total per produk: kolom Qty yang memuat total dan
            // rinciannya sekaligus akan terhitung dua kali begitu seseorang
            // menyeret SUM() ke bawahnya.
            //
            // Dua kolom potongan hanya ikut untuk periode yang memang punya
            // potongan ([BL-116] butir 2) — syarat yang sama dengan blok di
            // atas, dan sengaja dibaca dari angka periode, bukan dari setelan
            // tenant hari ini: tenant yang menghapus semua aturan diskonnya
            // bulan ini tetap harus bisa membaca laporan bulan lalu.
            fputcsv($out, ['PRODUK TERLARIS']);
            fputcsv($out, array_merge(
                ['Peringkat', 'Produk', 'Varian', 'Qty Terjual'],
                $hasDiscounts ? ['Harga Normal', 'Potongan'] : [],
                ['Omzet'],
            ));
            foreach ($topProducts as $rank => $product) {
                foreach ($product['variants'] as $variant) {
                    fputcsv($out, array_merge(
                        [
                            $rank + 1,
                            $product['product_name'],
                            $variant['variant_name'],
                            $variant['total_qty'],
                        ],
                        $hasDiscounts ? [$variant['total_gross'], $variant['total_discount']] : [],
                        [$variant['total_revenue']],
                    ));
                }
            }

            fclose($out);
        }, 'laporan-bulanan-'.$month->format('Y-m').'.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Penjualan selesai dalam satu bulan kalender — sebagai builder.
     *
     * Dipisah supaya `monthly()` dan unduhan CSV-nya menyaring dengan syarat
     * yang persis sama; dua salinan syarat yang sama akan berbeda suatu hari.
     */
    private function completedIn(Carbon $month): Builder
    {
        return Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween($month, $month->copy()->endOfMonth());
    }

    /**
     * Apakah layar ini perlu bicara pajak, dan dengan kata apa ([BL-065] (e)).
     *
     * Pajak tidak pernah ditampilkan sebagai kolom nol kepada tenant yang
     * tidak memungut — bawaannya mati, dan mayoritas tenant memang di bawah
     * ambang wajib pungut. Yang membuat bagian ini muncul ada dua, dan
     * keduanya perlu:
     *
     *   - `tax_enabled` menyala — pajaknya berjalan, meski hari itu kebetulan
     *     belum ada penjualan; nol yang eksplisit lebih menenangkan daripada
     *     bagian yang hilang.
     *   - ada pajak yang benar-benar terpungut di periode itu — ini yang
     *     menjaga periode lampau tetap terbaca kalau sakelarnya suatu saat
     *     dibuka kembali lewat konsol platform.
     *
     * Labelnya diambil dari transaksinya, BUKAN dari setelan tenant hari ini.
     * `tax_label` tidak ikut terkunci saat penjualan berpajak pertama, jadi
     * tenant yang mengganti "PPN" jadi "PB1" bulan lalu tidak boleh membuat
     * laporan lamanya menyebut dasar hukum yang salah. Bila satu periode
     * memuat dua label, keduanya disebut — menampilkan salah satu berarti
     * memilih sebagian angka dan menamainya seluruhnya.
     *
     * @return array{active: bool, label: string}
     */
    private function taxContext(Builder $completed, float $taxCollected): array
    {
        $tenant = auth()->user()->tenant;

        if (! ($tenant?->tax_enabled ?? false) && $taxCollected <= 0) {
            return ['active' => false, 'label' => 'Pajak'];
        }

        $labels = $completed
            ->whereNotNull('tax_label')
            ->where('tax_amount', '>', 0)
            ->distinct()
            ->orderBy('tax_label')
            ->pluck('tax_label')
            ->all();

        return [
            'active' => true,
            // Periode tanpa penjualan berpajak belum punya label bekunya, jadi
            // ia meminjam setelan tenant sekarang — dan 'Pajak' bila itu pun
            // belum diisi. Yang tidak boleh: menebak antara PPN dan PB1.
            'label' => $labels === []
                ? ($tenant?->tax_label ?: 'Pajak')
                : implode(', ', $labels),
        ];
    }

    /**
     * Pasangan `taxContext()` untuk biaya layanan ([BL-097]).
     *
     * Aturan munculnya sama persis — sakelar tenant ATAU ada yang benar-benar
     * terpungut di periode itu — dan alasannya juga sama: kolom nol di setiap
     * baris bukan kejujuran, melainkan derau bagi mayoritas yang tidak
     * memungut.
     *
     * Satu hal yang berbeda dan disengaja: tidak ada padanan `taxLocked()`
     * yang perlu dijaga di sini. Biaya layanan boleh dimatikan pemilik toko
     * kapan pun, jadi periode lampau yang memungutnya JAUH lebih mungkin
     * ditemui daripada pada pajak — dan justru itu sebabnya cabang "sakelar
     * mati tapi ada yang terpungut" di bawah menanggung beban lebih besar
     * di sini daripada di `taxContext()`.
     *
     * @return array{active: bool, label: string}
     */
    private function serviceChargeContext(Builder $completed, float $collected): array
    {
        $tenant = auth()->user()->tenant;

        if (! ($tenant?->service_charge_enabled ?? false) && $collected <= 0) {
            return ['active' => false, 'label' => 'Biaya Layanan'];
        }

        $labels = $completed
            ->whereNotNull('service_charge_label')
            ->where('service_charge_amount', '>', 0)
            ->distinct()
            ->orderBy('service_charge_label')
            ->pluck('service_charge_label')
            ->all();

        return [
            'active' => true,
            'label' => $labels === []
                ? ($tenant?->service_charge_label ?: 'Biaya Layanan')
                : implode(', ', $labels),
        ];
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

        return BusinessClock::now()->startOfMonth();
    }

    /**
     * Deret harian satu bulan penuh; tanggal tanpa transaksi tetap muncul nol.
     *
     * Nol yang eksplisit itu yang membuat deretnya jujur: hari tutup yang
     * hilang dari deret akan tersambung jadi garis lurus di grafik dan
     * terbaca seolah toko tetap ramai.
     *
     * @return array<int, array{date: string, count: int, revenue: float, net_revenue: float, tax_collected: float, service_charge_collected: float, voided: int}>
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
            // Omzet bersih dan pajak terpungut ikut diagregasi di sini, bukan
            // lewat kueri sendiri: barisnya sama, penyaringnya sama, dan
            // ringkasan bulan diturunkan dari deret ini juga.
            ->selectRaw('SUM(CASE WHEN status = ? THEN subtotal_amount ELSE 0 END) as net_revenue', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN tax_amount ELSE 0 END) as tax_collected', [$completed])
            ->selectRaw('SUM(CASE WHEN status = ? THEN service_charge_amount ELSE 0 END) as service_charge_collected', [$completed])
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
                'net_revenue' => (float) ($row->net_revenue ?? 0),
                'tax_collected' => (float) ($row->tax_collected ?? 0),
                'service_charge_collected' => (float) ($row->service_charge_collected ?? 0),
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
     * @param  array<int, array{date: string, count: int, revenue: float, net_revenue: float, tax_collected: float, service_charge_collected: float, voided: int}>  $series
     * @return array{total_revenue: float, net_revenue: float, tax_collected: float, service_charge_collected: float, total_transactions: int, voided_count: int, average_transaction: float, days_in_month: int, active_days: int, average_active_day_revenue: float, best_day: array{date: string, revenue: float, count: int}|null}
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
            'net_revenue' => (float) array_sum(array_column($series, 'net_revenue')),
            'tax_collected' => (float) array_sum(array_column($series, 'tax_collected')),
            'service_charge_collected' => (float) array_sum(array_column($series, 'service_charge_collected')),
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
     * dengan kebiasaan yang sama — sekarang secara harfiah sama, karena
     * keduanya memanggil pembaca yang sama ([BL-109]).
     *
     * @return array<int, array{id: int, name: string, type: string, total: float}>
     */
    private function paymentSummaryFor(Carbon $month): array
    {
        return $this->paymentRecap->for(
            $this->completedIn($month),
            auth()->user()->tenant_id
        );
    }

    /**
     * Produk terlaris dalam satu bulan.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function topProductsFor(Carbon $month)
    {
        $endOfMonth = $month->copy()->endOfMonth();

        return $this->topProducts(
            fn (Builder $q) => $q->whereEffectiveBetween($month, $endOfMonth)
        );
    }

    /**
     * Sepuluh PRODUK terlaris, berikut pecahan variannya.
     *
     * Dikelompokkan per produk, bukan per `transaction_items.variant_name`.
     * Kolom itu hanya menyimpan nama variannya saja — "Hot", "Single",
     * "Plain" — dan nama yang sama dipakai ulang oleh produk yang berbeda.
     * Mengelompokkan langsung padanya bukan sekadar salah label di kepala
     * tabel: qty dan omzet Cafe Latte "Hot", Kopi Susu Signature "Hot", dan
     * Kopi Susu Gula Aren "Hot" terjumlah jadi SATU baris bernama "Hot", dan
     * angka yang dibaca pemilik adalah angka tiga produk yang tidak pernah ia
     * gabungkan. Nama produknya hanya bisa dicapai lewat
     * `product_variants` → `products`, jadi keduanya ikut di-join.
     *
     * Produk yang sudah dihapus (soft delete) sengaja TIDAK disaring:
     * penjualannya tetap terjadi di periode itu, dan membuangnya berarti omzet
     * yang lenyap dari laporan tanpa meninggalkan jejak.
     *
     * Pecahan variannya dikelompokkan pada `product_variants.id`, dan namanya
     * diambil dari katalog — bukan dari `variant_name` yang dibekukan di
     * barisnya. Kolom beku itu tidak konsisten sepanjang riwayat: sebagian
     * baris lama menyimpan label lengkap ("Espresso - Single") dan sebagian
     * lagi hanya nama variannya ("Single"), sehingga satu varian yang sama
     * muncul dua kali di bawah satu produk — persis kebingungan yang tabel ini
     * dibuat untuk menghilangkan. Yang dijawab baris ini adalah "varian mana",
     * dan varian mana ditentukan oleh id-nya, bukan oleh string yang kebetulan
     * diketik saat itu.
     *
     * Konsekuensinya disadari: varian yang DIGANTI NAMANYA akan tampil dengan
     * nama barunya untuk penjualan lama. Itu pertukaran yang diambil dengan
     * sengaja di layar ini saja — nama bekunya tetap utuh di
     * `transaction_items`, tetap dipakai struk, dan tetap jadi dasar
     * pemeriksaan nama karangan model (`[BL-100]`).
     *
     * @param  \Closure(Builder): Builder  $withinPeriod  penyaring rentang pada transaksinya
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function topProducts(\Closure $withinPeriod)
    {
        return TransactionItem::query()
            ->join('product_variants', 'product_variants.id', '=', 'transaction_items.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->whereHas('transaction', function ($q) use ($withinPeriod) {
                $withinPeriod($q->where('status', Transaction::STATUS_COMPLETED));
            })
            ->selectRaw('products.id as product_id, products.name as product_name')
            ->selectRaw('product_variants.id as variant_id, product_variants.name as variant_name')
            ->selectRaw('SUM(transaction_items.qty) as total_qty, SUM(transaction_items.subtotal) as total_revenue')
            // Potongan per produk ([BL-116] butir 2), supaya "penjualan murni"
            // dan "yang benar-benar tertagih" terbaca berdampingan alih-alih di
            // dua layar berbeda. Harga normalnya dijumlahkan di PHP dari kedua
            // kolom ini, bukan dari `original_unit_price`: kolom itu kosong
            // untuk baris tanpa potongan dan untuk penjualan offline yang
            // tersinkron sebelum `[BL-115]`, sedangkan
            // `total_revenue + total_discount` selalu berjumlah tepat dengan
            // kolom omzet di sebelahnya.
            ->selectRaw('SUM(transaction_items.discount_amount * transaction_items.qty) as total_discount')
            ->groupBy('products.id', 'products.name', 'product_variants.id', 'product_variants.name')
            ->get()
            ->groupBy('product_id')
            ->map(fn ($rows) => [
                'product_id' => (int) $rows->first()->product_id,
                'product_name' => $rows->first()->product_name,
                'total_qty' => (int) $rows->sum('total_qty'),
                'total_revenue' => (float) $rows->sum('total_revenue'),
                'total_discount' => (float) $rows->sum('total_discount'),
                'total_gross' => (float) $rows->sum('total_revenue') + (float) $rows->sum('total_discount'),
                // Pecahan variannya ikut: produk menjawab "apa yang laku",
                // varian menjawab "dalam bentuk apa" — dan yang kedua yang
                // menentukan apa yang harus disiapkan besok pagi.
                'variants' => $rows
                    ->sortByDesc(fn ($row) => (int) $row->total_qty)
                    ->map(fn ($row) => [
                        'variant_id' => (int) $row->variant_id,
                        'variant_name' => $row->variant_name,
                        'total_qty' => (int) $row->total_qty,
                        'total_revenue' => (float) $row->total_revenue,
                        'total_discount' => (float) $row->total_discount,
                        'total_gross' => (float) $row->total_revenue + (float) $row->total_discount,
                    ])
                    ->values(),
            ])
            ->sortByDesc('total_qty')
            ->take(10)
            ->values();
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
     *
     * Angkanya dipecah menjadi tiga kolom — gabungan, saran yang DITEMUKAN
     * mesin, dan aturan yang DITULIS owner sendiri ([BL-092]). Rekap per jenis
     * sebenarnya sudah memuat bahannya, tapi menuntut owner menjumlahkan tiga
     * baris mesin di kepalanya untuk membandingkannya dengan satu baris manual
     * adalah cara paling pasti membuat perbandingan itu tidak pernah dilakukan
     * — padahal justru itu satu-satunya cara ia tahu tebakannya sendiri lebih
     * baik atau lebih buruk daripada tebakan sistem.
     */
    /**
     * Jenis saran jual yang tidak sedang menghasilkan apa pun untuk tenant ini.
     *
     * Kedua lapisan digabung di sini dengan sengaja, berbeda dari halaman
     * Aturan yang memisahkannya. Yang dijawab layar ini cuma satu pertanyaan —
     * "apakah angka nol ini berarti jenisnya gagal, atau berarti jenisnya
     * mati" — dan untuk itu asal matinya tidak penting. Tempat mengubahnya
     * ada satu tautan jauhnya, dan di sanalah bedanya terlihat.
     *
     * @return list<string>
     */
    private function inactiveUpsellTypes(Tenant $tenant): array
    {
        return array_values(array_filter(
            array_keys(Tenant::upsellTypeColumns()),
            fn (string $type) => ! config("upsell.types.{$type}", true)
                || ! $tenant->upsellTypeEnabled($type),
        ));
    }

    public function upsell(Request $request): Response
    {
        $from = $request->input('from', BusinessClock::daysAgo(29));
        $to = $request->input('to', BusinessClock::today());

        // Definisinya milik StockRescueService, bukan halaman ini: angka utama
        // "diselamatkan" di kepala halaman dan tabel rekap di bawahnya harus
        // menghitung himpunan yang sama persis ([BL-105]). Alasan menyaring
        // transaksi batal ada di sana ([BL-092]).
        $tenant = $request->user()->tenant;

        $scoped = fn () => $this->stockRescue->countableEvents($tenant, $from, $to);

        // Satu kali baca, dipakai tiga kali. Memisahkan mesin dari manual
        // dengan tiga rombongan query terpisah akan mengalikan biaya halaman
        // ini demi angka yang sumbernya sama persis.
        $perType = $scoped()
            ->selectRaw('type, COUNT(*) as shown')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as accepted', [UpsellEvent::STATUS_ACCEPTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) as rejected', [UpsellEvent::STATUS_REJECTED])
            ->selectRaw('SUM(CASE WHEN status = ? THEN extra_amount ELSE 0 END) as extra_revenue', [UpsellEvent::STATUS_ACCEPTED])
            ->groupBy('type')
            ->get();

        $summary = $this->upsellSummary($perType);

        return Inertia::render('Owner/Reports/Upsell', [
            'filters' => ['from' => $from, 'to' => $to],
            'summary' => $summary,

            // Penyelamat Stok ([BL-105]) — satu-satunya bagian halaman ini yang
            // menjawab pertanyaan pemilik, bukan pertanyaan analis: berapa uang
            // yang masuk lewat barang yang sedang tertekan, dan berapa yang
            // terlanjur mati di rak.
            //
            // Kedua angkanya BERPERIODE BEDA dengan sengaja: `rescued` mengikuti
            // filter tanggal di atas, `spoiled` adalah potret hari ini karena
            // `stock` tidak menyimpan sejarah. Labelnya di Upsell.vue yang
            // memikul beda itu — lihat catatan di StockRescueService::spoiled().
            'rescue' => [
                'rescued' => $this->stockRescue->rescued($tenant, $from, $to),
                'spoiled' => $this->stockRescue->spoiled($tenant),
                // Pendamping `spoiled` ([BL-108]): barang basi yang keluar
                // lewat penjualan. Berperiode seperti `rescued`, dan membawa
                // daftar barisnya — di sinilah owner meninjau konfirmasi kasir.
                'soldExpired' => $this->stockRescue->soldExpired($tenant, $from, $to),
            ],

            // Jenis yang saat ini tidak menghasilkan apa pun — entah dimatikan
            // owner sendiri, entah dimatikan untuk seluruh toko ([BL-099]).
            // Tabel "Per Jenis Saran" di bawah memecah angkanya per jenis
            // SUPAYA jenis yang tak pernah diterima bisa dimatikan; tanpa
            // penanda ini, jenis yang sudah mati terbaca seperti jenis yang
            // gagal, dan owner menonaktifkan sesuatu dua kali.
            'inactiveTypes' => $this->inactiveUpsellTypes($tenant),

            // Dua sumber saran yang bersaing memperebutkan slot yang sama di
            // layar kasir, jadi hanya berguna kalau bisa dibandingkan
            // berdampingan ([BL-092]).
            'sources' => [
                'auto' => $this->upsellSummary($perType->where('type', '!=', UpsellEvent::TYPE_MANUAL)),
                'manual' => $this->upsellSummary($perType->where('type', UpsellEvent::TYPE_MANUAL)),
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
     * Ringkasan satu rombongan baris rekap per jenis.
     *
     * Dua angka berbeda di sini, dan membedakannya adalah inti [BL-025]:
     *
     *   conversion_rate — dari SEMUA yang tampil. Menjawab "seberapa sering
     *     saran berujung penjualan", termasuk yang cuma lewat.
     *   offer_rate      — dari yang BENAR-BENAR ditawarkan ke pelanggan
     *     (diterima + ditolak). Menjawab "kalau kasir menawarkan, seberapa
     *     sering pelanggan mau" — dan hanya angka ini yang bisa menilai
     *     sarannya sendiri, bukan kedisiplinan kasirnya.
     *
     * @param  \Illuminate\Support\Collection<int, object>  $rows
     * @return array{shown: int, accepted: int, rejected: int, offered: int, conversion_rate: float, offer_rate: float, extra_revenue: float}
     */
    private function upsellSummary($rows): array
    {
        $shown = (int) $rows->sum('shown');
        $accepted = (int) $rows->sum('accepted');
        $rejected = (int) $rows->sum('rejected');
        $offered = $accepted + $rejected;

        return [
            'shown' => $shown,
            'accepted' => $accepted,
            'rejected' => $rejected,
            'offered' => $offered,
            'conversion_rate' => $shown > 0 ? round($accepted / $shown * 100, 1) : 0,
            'offer_rate' => $offered > 0 ? round($accepted / $offered * 100, 1) : 0,
            'extra_revenue' => (float) $rows->sum('extra_revenue'),
        ];
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
                // Jejak pengungkapan angka seharusnya ([BL-090]). Dihitung di
                // basis data, bukan dimuat barisnya: yang dipajang di daftar
                // hanya "pernah dibuka berapa kali dan kapan pertama", dan
                // memuat seluruh barisnya untuk 25 sesi berarti puluhan baris
                // yang tak satu pun ditampilkan.
                ->withCount('reveals')
                ->withMin('reveals', 'revealed_at')
                ->latest('opened_at')
                ->paginate(25)),

            // Mutasi kas yang menunggu keputusan pemilik ([BL-087]).
            //
            // TIDAK ditunda seperti daftar di atasnya, dan itu disengaja: ini
            // satu-satunya hal di halaman ini yang menuntut TINDAKAN, dan
            // sebuah tindakan yang baru muncul setelah kerangka pemuatan hilang
            // akan terlewat oleh pemilik yang sudah selesai membaca. Ongkosnya
            // satu query berindeks atas tabel yang biasanya kosong.
            'pendingMovements' => CashDrawerMovement::pending()
                ->with(['user:id,name', 'cashDrawer:id,opened_at'])
                ->orderBy('created_at')
                ->get(),
        ]);
    }
}
