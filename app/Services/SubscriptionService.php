<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use App\Services\Pricing\AdaptiveEligibility;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    public function __construct(
        private readonly PricingService $pricing,
        private readonly AdaptiveEligibility $eligibility,
    ) {}

    /**
     * Panjang masa gratis untuk tenant baru, dalam bulan.
     */
    public static function trialMonths(): int
    {
        return (int) config('subscription.trial_months');
    }

    /**
     * Panjang masa tenggang sebelum penangguhan, dalam hari.
     */
    public static function graceDays(): int
    {
        return (int) config('subscription.grace_days');
    }

    /**
     * Hari tenggat ke berapa peringatan berubah jadi mengganggu.
     */
    public static function graceIntensiveFromDay(): int
    {
        return (int) config('subscription.grace_intensive_from_day');
    }

    /**
     * Hari tenggat ke berapa kemampuan menulis dicabut.
     */
    public static function graceLockFromDay(): int
    {
        return (int) config('subscription.grace_lock_from_day');
    }

    /**
     * Berapa hari sebelum periode habis tagihan berikutnya terbit.
     */
    public static function invoiceLeadDays(): int
    {
        return (int) config('subscription.invoice_lead_days');
    }

    /**
     * Titik waktu penetapan harga untuk periode tagihan `Y-m`.
     *
     * Awal bulan periodenya, bukan akhirnya: aturan yang mulai berlaku di
     * tengah bulan tidak boleh mengubah harga bulan yang sudah berjalan. Yang
     * dipakai adalah aturan yang sudah berdiri saat periodenya dibuka — itulah
     * yang akan dikatakan kepada tenant bila ia bertanya.
     *
     * Tinggal di sini supaya penerbit otomatis dan penerbit manual di
     * `Platform\InvoiceController` memakai titik waktu yang sama. Dua tanggal
     * penetapan harga yang berbeda akan melahirkan dua nominal untuk periode
     * yang sama, dan yang menang tinggal soal siapa yang menekan tombol.
     */
    public static function pricingAsOf(string $period): Carbon
    {
        return Carbon::createFromFormat('Y-m', $period)->startOfMonth();
    }

    /**
     * Tanggal akses ditutup sepenuhnya, bila tenant sedang di masa tenggang.
     *
     * Dihitung, bukan disimpan: menyimpannya berarti ada dua kebenaran yang
     * bisa berselisih begitu `grace_days` diubah. Dipakai bersama halaman
     * langganan dan ringkasan di dashboard — dua perhitungan yang mirip pasti
     * bercabang begitu salah satunya diperbaiki.
     *
     * Mengembalikan null untuk status selain `grace`, karena di luar masa
     * tenggang tanggal ini tidak punya arti apa pun.
     */
    public function suspensionDateFor(Tenant $tenant): ?Carbon
    {
        if ($tenant->status !== Tenant::STATUS_GRACE) {
            return null;
        }

        return $this->ensureFor($tenant)
            ->current_period_end
            ?->copy()
            ->addDays(self::graceDays());
    }

    /**
     * Pastikan tenant punya langganan, buatkan trial bila belum.
     *
     * Idempoten dan sengaja dipanggil dari beberapa tempat — registrasi,
     * pemeriksaan batas seat, halaman langganan. Alasannya: batas seat yang
     * bersandar pada `$tenant->subscription` akan diam-diam TERBUKA LEBAR untuk
     * tenant yang lahir lewat jalur lain (seeder, impor, pembuatan manual di
     * database). Menjamin barisnya ada jauh lebih aman daripada memperlakukan
     * ketiadaannya sebagai "tanpa batas".
     */
    public function ensureFor(Tenant $tenant): Subscription
    {
        $existing = $tenant->subscription()->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->startTrial($tenant);
    }

    /**
     * Buka masa gratis di paket `free`, jalur harga normal.
     *
     * Jalur normal adalah default yang disengaja: tenant belum menyetujui apa
     * pun soal pembukaan data omset, jadi jalur adaptif tidak boleh menjadi
     * keadaan awal siapa pun.
     *
     * **Panjangnya dihitung dalam bulan, bukan hari** (keputusan pemilik
     * 2026-08-07). `addMonthsNoOverflow()` dan bukan `addMonths()`: dua bulan
     * dari 31 Desember tanpa penjaga luberan mendarat di 3 Maret dan melewatkan
     * Februari sama sekali.
     */
    public function startTrial(Tenant $tenant): Subscription
    {
        $plan = Plan::default();
        $mulai = now();
        $trialEndsAt = $mulai->copy()->addMonthsNoOverflow(self::trialMonths());

        return $tenant->subscription()->create([
            'plan_id' => $plan->id,
            'pricing_track' => Subscription::TRACK_NORMAL,
            'seats' => $plan->included_seats,
            'seat_high_water' => 1,
            'price_locked' => null,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => $mulai->toDateString(),
            'current_period_end' => $trialEndsAt->toDateString(),
            // Jangkar tanggal tagih lahir di sini dan tidak pernah berubah lagi.
            // Ia harus ditulis sekarang, bukan disimpulkan belakangan: begitu
            // sebuah periode berakhir di bulan pendek, tanggalnya sudah terjepit
            // dan hari aslinya tak bisa dipulihkan dari data mana pun.
            //
            // Diambil dari tanggal DAFTAR, bukan tanggal masa gratis berakhir:
            // keduanya hampir selalu sama, kecuali ketika akhir masa gratis
            // terjepit bulan pendek. Yang daftar 31 Desember berakhir 28
            // Februari — mengambil jangkar dari situ mengunci tanggal tagihnya
            // di 28 selamanya, padahal yang ia minta tanggal 31.
            'billing_anchor_day' => $mulai->day,
        ]);
    }

    /**
     * Tanggal periode berbayar berikutnya, sebagai atribut siap-simpan.
     *
     * Satu-satunya tempat periode langganan dimajukan. Dituliskan di sini dan
     * bukan di pemanggilnya karena aturannya bukan aritmetika tanggal biasa,
     * melainkan tiga keputusan yang harus jalan bersama — dan tiga keputusan
     * yang disalin ke pemanggil kedua pasti bercabang.
     *
     * **Menyambung, bukan mulai dari hari verifikasi.** Titik mulainya adalah
     * akhir periode sebelumnya, bukan `now()`. Tanpa itu tenant yang telat bayar
     * lima hari menggeser tanggal tagihnya maju lima hari, permanen — dan
     * tanggal 29/30/31 bisa menjadi titik mulai periode hanya karena kebetulan
     * bukti bayarnya diperiksa pada tanggal itu.
     *
     * **Mengikuti jangkar, bukan menambah satu bulan.** Lihat
     * `Subscription::anchoredDateIn()`: penjepitan bulan pendek tidak menular ke
     * bulan sesudahnya.
     *
     * **Tunggakan tidak ditumpuk.** Bila periode yang tersambung ternyata sudah
     * lewat seluruhnya — tenant membayar setelah berbulan-bulan tertangguh —
     * periodenya dimajukan sampai berakhir di masa depan. Satu pembayaran
     * memulihkan satu periode ke depan, bukan menyeret tenant ke periode yang
     * sudah usai lalu langsung menangguhkannya lagi.
     *
     * **Ditinjau ulang 2026-08-07, setelah `[BL-044]`(b) berjalan — aturannya
     * tetap.** Kekhawatirannya waktu itu: begitu tagihan terbit otomatis tiap
     * periode, tiap bulan yang terlewat punya tagihannya sendiri, jadi
     * "melompati" periode berarti melompati tagihan. Itu tidak terjadi, karena
     * `issueDuePeriodInvoices()` hanya menerbitkan satu tagihan per pelanggaran:
     * `current_period_end` tidak pernah maju selama tenant belum membayar, jadi
     * kunci `Y-m` periodenya membeku dan penjaga periode-ganda menolak semua
     * penerbitan sesudahnya. Satu pembayaran memulihkan satu periode, dan hanya
     * pernah ada satu tagihan langganan terbuka untuk dipulihkan — kedua aturan
     * itu bertemu, bukan bertabrakan.
     *
     * Yang akan mematahkannya, bila kelak ditulis: penerbit yang menagih tiap
     * bulan terlewat secara terpisah, atau penerbitan yang ikut berjalan untuk
     * tenant `suspended` (yang periodenya juga beku). Keduanya melahirkan
     * tagihan kedua, dan sejak saat itu melompati periode berarti benar-benar
     * melompati uang. Lihat `[BL-051]`.
     *
     * @return array{current_period_start: string, current_period_end: string, billing_anchor_day: int}
     */
    public function renewPeriod(Subscription $subscription): array
    {
        $today = now()->startOfDay();
        $start = $subscription->current_period_end?->copy()->startOfDay() ?? $today->copy();
        $end = $subscription->nextAnchoredDateAfter($start);

        while ($end->lte($today)) {
            $start = $end;
            $end = $subscription->nextAnchoredDateAfter($start);
        }

        return [
            'current_period_start' => $start->toDateString(),
            'current_period_end' => $end->toDateString(),
            // Ditulis ulang tiap kali supaya baris lama yang jangkarnya masih
            // kosong mendapatkannya pada pembaruan periode pertamanya, bukan
            // menunggu backfill kedua.
            'billing_anchor_day' => $subscription->billingAnchorDay(),
        ];
    }

    /**
     * Pindahkan tenant yang masa gratisnya hampir habis ke paket berbayar
     * tujuannya — `[BL-052]`.
     *
     * Sampai sekarang `trial_ends_at` ditulis di `startTrial()` dan tidak pernah
     * dibaca lagi. Akibatnya paket `free` tidak bisa hidup: tarifnya Rp 0,
     * penerbit tagihan melewatinya tanpa memperpanjang periode, lalu periodenya
     * lewat dan tenant turun ke masa tenggang — tiap periode, selamanya. Yang
     * hilang bukan penyempurnaan melainkan penggeraknya; `changePlan()` sudah
     * ada sejak lama, tak ada yang memanggilnya.
     *
     * **Dipindahkan pada jendela yang sama dengan penerbitan tagihan**
     * (`invoice_lead_days`), bukan sehari setelah masa gratisnya habis. Dua
     * sebabnya:
     *
     *   - Tagihan periode berbayar pertama terbit H-7. Menunggu sampai
     *     `trial_ends_at` benar-benar lewat berarti penerbit sudah melihat
     *     tenant ini seharga Rp 0 dan melewatinya — tepat kegagalan yang
     *     hendak ditutup. Pemindahan HARUS mendahului penerbitan, dan urutan itu
     *     dijaga di `advanceLifecycle()`, bukan di jadwal.
     *   - `[BL-052]`(c) meminta tenant tahu sebelum hari-H. Tagihan yang tiba
     *     tujuh hari lebih awal, dengan nama paket barunya tertulis di halaman
     *     langganan, adalah pemberitahuan itu.
     *
     * **Sisa hari gratisnya tidak berkurang, dan batasnya tidak menyempit.**
     * Periode berjalan tidak disentuh — tenant tetap tidak ditagih sampai
     * `current_period_end`. Yang berubah hanya paketnya, dan paket tujuannya
     * selalu lebih longgar daripada `free` (jatah pengguna dan kuota AI naik).
     * Bila kelak ada paket tujuan yang lebih sempit dari paket gratis, jendela
     * ini harus dipikirkan ulang — bukan angkanya, melainkan arahnya.
     *
     * **Tanpa paket tujuan, pemindahannya berhenti dan bersuara.** Tidak jatuh
     * diam-diam ke paket termurah: menebak berarti memindahkan tenant ke tarif
     * yang tak seorang pun putuskan, lalu menagihkannya. Tenantnya dihitung
     * sebagai `stranded` dan dicatat sebagai kejadian sensitif — ini salah
     * setel, bukan kebijakan.
     *
     * `suspended` di luar jangkauan, mengikuti penerbit tagihan: aksesnya sudah
     * tertutup, dan memindahkan paket tenant yang tak bisa memakainya hanya
     * mengubah angka tanpa ada yang melihatnya.
     *
     * @return array{graduated: int, stranded: int}
     */
    public function graduateExpiredTrials(bool $dryRun = false): array
    {
        $horizon = now()->startOfDay()->addDays(self::invoiceLeadDays());
        $freePlan = Plan::default();

        $due = Tenant::query()
            ->whereIn('status', [Tenant::STATUS_TRIAL, Tenant::STATUS_ACTIVE, Tenant::STATUS_GRACE])
            ->whereHas('subscription', fn ($query) => $query
                // Paketnya, bukan tarifnya. Tenant berbayar yang tarifnya
                // kebetulan Rp 0 hari ini — keringanan, atau paket yang
                // angkanya belum ditetapkan — bukan tenant yang masa gratisnya
                // habis, dan memindahkannya berarti mencabut kesepakatan.
                ->where('plan_id', $freePlan->id)
                // Baris tanpa tenggat masa gratis (seeder, impor, pembuatan
                // manual) sengaja dibiarkan. Memindahkan tenant yang tak pernah
                // dijanjikan tanggal berakhir berarti menagihnya karena datanya
                // tidak lengkap.
                ->whereNotNull('trial_ends_at')
                ->whereDate('trial_ends_at', '<=', $horizon))
            ->with('subscription')
            ->get();

        if ($due->isEmpty()) {
            return ['graduated' => 0, 'stranded' => 0];
        }

        $target = Plan::postTrialTarget();

        // Paket tujuan yang menunjuk paket gratis itu sendiri ditolak seperti
        // ketiadaan penunjukan. Ia akan "memindahkan" tenant ke tempat yang
        // sama, tiap hari, dan lingkaran yang berjalan mulus jauh lebih sulit
        // dikenali daripada perpindahan yang berhenti dan mengeluh.
        if ($target === null || $target->is($freePlan)) {
            if (! $dryRun) {
                PlatformAuditLog::record('subscriptions.post-trial-target-missing', null, [
                    'tenant_ids' => $due->pluck('id')->all(),
                    'target_plan_id' => $target?->id,
                ]);
            }

            return ['graduated' => 0, 'stranded' => $due->count()];
        }

        if (! $dryRun) {
            foreach ($due as $tenant) {
                $subscription = $tenant->subscription;
                $sebelum = $subscription->plan_id;

                $this->changePlan($subscription, $target);

                PlatformAuditLog::record('subscriptions.trial-graduated', $tenant, [
                    'tenant_id' => $tenant->id,
                    'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                    'from_plan_id' => $sebelum,
                    'to_plan' => $target->slug,
                    'seats' => $subscription->seats,
                ]);
            }
        }

        return ['graduated' => $due->count(), 'stranded' => 0];
    }

    /**
     * Komponen seat pada tagihan langganan periode berjalan.
     *
     * Seat tambahan adalah biaya BULANAN, bukan sekali bayar (keputusan pemilik
     * 2026-08-07, `[BL-053]`). Sampai 2026-08-07 ia ditagih sekali lewat
     * `KIND_UPGRADE` lalu melekat permanen — artinya Rp 20.000 berbunyi "sekali,
     * seat itu milik Anda selamanya". Angkanya benar, satuannya salah.
     *
     * Jumlahnya dari `entitledExtraSeats()` — hak yang DIBELI, bukan pemakaian
     * yang diamati (keputusan pemilik kedua 2026-08-07, yang mengoreksi butir
     * (a) entri itu). Usul semula memakai `seat_high_water − included_seats`;
     * puncak pemakaian kini tidak menentukan nominal apa pun.
     *
     * `$periodStart` adalah awal periode yang DITAGIH, bukan hari ini. Tagihan
     * terbit `invoice_lead_days` sebelum periode berjalan habis, jadi menanyakan
     * hak "sekarang" akan menagih periode depan dengan keadaan hari ini — dan
     * pelepasan yang sudah dijadwalkan tepat di antara keduanya akan tertagih
     * satu periode lebih lama daripada yang dijanjikan.
     *
     * **Tarif per seat selalu dari PAKET, termasuk untuk tenant Adaptif**
     * (keputusan pemilik 2026-08-07). Yang didiskon jalur Adaptif adalah harga
     * langganannya, bukan harga penggunanya: tenant Adaptif di `paid-1` membayar
     * Rp 15.000 per seat meski langganannya turun ke Rp 10.000 oleh bracket. Itu
     * konsisten dengan "Adaptif = `paid-1` yang didiskon", dan ia disengaja —
     * bukan efek samping dari `resolveFor()` yang kebetulan tidak menyentuh seat.
     *
     * @return array{seats: int, unit_price: float, amount: float}
     */
    public function seatChargeFor(Subscription $subscription, ?Carbon $periodStart = null): array
    {
        $subscription->loadMissing('plan');

        $seats = $subscription->entitledExtraSeats($periodStart);
        $unitPrice = (float) ($subscription->plan?->extra_seat_price ?? 0);

        return [
            'seats' => $seats,
            'unit_price' => $unitPrice,
            'amount' => $seats * $unitPrice,
        ];
    }

    /**
     * Terbitkan tagihan periode berikutnya untuk tenant yang periodenya hampir
     * habis.
     *
     * Sebelum ini, satu-satunya cara tagihan bulanan lahir adalah pemilik SaaS
     * mengetiknya sendiri untuk tiap tenant, tiap bulan. Tenant tidak pernah
     * diberi tahu berapa yang harus dibayar — ia hanya menemukan aplikasinya
     * berubah jadi hanya-baca. Lihat `[BL-044]`.
     *
     * Nominalnya keluar dari `PricingService::resolveFor()`, resolver yang sama
     * dengan penerbit manual, supaya keduanya tidak pernah bercabang.
     *
     * **Yang tidak bisa ditagih tidak menghentikan apa pun** (keputusan pemilik
     * 2026-08-05). Tenant tanpa tarif tetap menempuh siklus hidupnya seperti
     * biasa; perintah ini hanya menolak menerbitkan tagihan dan melaporkan
     * jumlahnya. Dua sebabnya dipisah karena obatnya berbeda:
     *
     *   - **nominal nol** — paket `free` berharga Rp 0. Menerbitkan tagihan Rp 0
     *     akan menuntut tenant mengunggah bukti transfer nol rupiah
     *     (`[BL-049]`), jadi tidak diterbitkan sama sekali. Menyembuhkan dirinya
     *     sendiri: begitu tarifnya ditetapkan, tagihan mulai terbit tanpa satu
     *     baris kode pun berubah — termasuk untuk tenant yang keburu turun ke
     *     masa tenggang sementara tarifnya masih nol; lihat catatan `grace` di
     *     bawah.
     *     Yang diperiksa adalah **totalnya**, bukan tarif paketnya saja
     *     (`[BL-053]`): tenant di paket Rp 0 yang membeli seat tambahan punya
     *     nominal yang benar-benar harus dibayar, dan melewatinya berarti
     *     memberikan seat berbayar itu cuma-cuma.
     *   - **tarif tidak ada** — `resolveFor()` mengembalikan `null` untuk tenant
     *     Adaptif tanpa bracket yang cocok dan tanpa paket penampung, persis
     *     keadaan "menghilang dari penagihan tanpa satu pun tanda" yang
     *     diperingatkan `PricingService::fallbackPlanFor()`. Ia dicatat sebagai
     *     kejadian sensitif, karena ia salah setel, bukan kebijakan.
     *
     * **Masa tenggang ikut ditagih; penangguhan tidak.** Awalnya `grace`
     * dikecualikan dengan alasan "tagihannya sudah terbit saat ia masih aktif" —
     * benar untuk tenant yang memang sudah ditagih, dan justru tidak berlaku
     * untuk tenant yang kedua sebabnya di atas membuatnya lewat tanpa tagihan.
     * Bagi mereka pengecualian itu permanen: `current_period_end` tidak pernah
     * maju selama tenant belum membayar, jadi ia tak akan pernah kembali ke
     * `active` sendiri, dan menetapkan tarifnya besok tidak menerbitkan apa pun.
     * Satu-satunya jalan keluar adalah pemilik SaaS mengetiknya manual — persis
     * keadaan yang `[BL-044]` tutup. Alasan sebenarnya sudah dipegang penjaga
     * periode-ganda di bawah, yang menolak tagihan kedua tanpa peduli status
     * tenantnya, jadi pengecualian statusnya bisa dilepas tanpa membuka apa pun.
     *
     * `suspended` tetap di luar: aksesnya sudah tertutup penuh, dan menerbitkan
     * tagihan atas bulan yang tak bisa dipakai berarti menumbuhkan utang yang
     * tak pernah diminta siapa pun. Melonggarkannya adalah keputusan pemilik,
     * bukan pembersihan kode — dicatat di `[BL-051]`.
     *
     * @return array{issued: int, free: int, unpriced: int, skipped: int}
     */
    public function issueDuePeriodInvoices(bool $dryRun = false): array
    {
        $today = now()->startOfDay();
        $horizon = $today->copy()->addDays(self::invoiceLeadDays());

        $issued = 0;
        $free = 0;
        $unpriced = 0;
        $skipped = 0;

        $due = Tenant::query()
            ->whereIn('status', [Tenant::STATUS_TRIAL, Tenant::STATUS_ACTIVE, Tenant::STATUS_GRACE])
            ->whereHas('subscription', fn ($query) => $query->whereDate('current_period_end', '<=', $horizon))
            ->with('subscription')
            ->get();

        foreach ($due as $tenant) {
            $subscription = $tenant->subscription;

            // Akhir periode berjalan adalah awal periode berikutnya — periode
            // itulah yang ditagih di sini.
            $periodStart = $subscription?->current_period_end;

            if ($periodStart === null) {
                continue;
            }

            $period = $periodStart->format('Y-m');

            // Penjaga yang sama dengan penerbit manual. Siklus berjangkar selalu
            // membuka tepat satu periode per bulan kalender, jadi kunci `Y-m`
            // tidak pernah bertabrakan dengan dirinya sendiri — yang ditolaknya
            // adalah tagihan yang sudah diketik pemilik SaaS untuk periode itu.
            //
            // **`kind` wajib ikut disaring** (`[BL-058]`). Tanpa itu, satu
            // tagihan penambahan seat di bulan X — yang memakai `period` yang
            // sama — membatalkan tagihan LANGGANAN bulan X, dan tenantnya lolos
            // sebulan penuh. Saringan ini menyelaraskan penjaga aplikasi dengan
            // indeks uniknya, yang sejak awal sudah `(tenant_id, period, kind)`;
            // sebelumnya keduanya menjaga dua hal yang berbeda.
            $sudahAda = Invoice::where('tenant_id', $tenant->id)
                ->where('period', $period)
                ->where('kind', Invoice::KIND_SUBSCRIPTION)
                ->exists();

            if ($sudahAda) {
                // Dihitung, bukan sekadar dilewati. Tiga penghitung lainnya
                // menjelaskan KENAPA sebuah tagihan tidak terbit; yang ini dulu
                // satu-satunya yang tidak, dan justru itu yang membuat
                // `[BL-058]` tak terlihat selama ada: keluaran perintahnya
                // terbaca normal sementara satu tenant hilang dari hitungan.
                $skipped++;

                continue;
            }

            $resolved = $this->pricing->resolveFor($tenant, self::pricingAsOf($period));
            $price = $resolved['price'];

            if ($price === null) {
                $unpriced++;

                if (! $dryRun) {
                    PlatformAuditLog::record('invoices.unpriced', $tenant, [
                        'tenant_id' => $tenant->id,
                        'period' => $period,
                        'pricing_track' => $subscription->pricing_track,
                    ]);
                }

                continue;
            }

            // Komponen seat, dihitung untuk periode yang DITAGIH — bukan untuk
            // hari ini (`[BL-053]`). Ia ditambahkan setelah penjaga tarif-null
            // dan sebelum penjaga tarif-nol, keduanya disengaja: tenant tanpa
            // tarif tetap tak bisa ditagih meski punya seat berbayar, sementara
            // tenant bertarif Rp 0 yang membeli seat kini PUNYA yang harus
            // dibayar dan karena itu berhenti terhitung `free`.
            $seat = $this->seatChargeFor($subscription, $periodStart);
            $amount = $price + $seat['amount'];

            if ($amount <= 0.0) {
                // Tidak dicatat ke jejak audit: selama tarifnya belum
                // ditetapkan, keadaan ini berlaku untuk SETIAP tenant setiap
                // bulan, dan jejak yang terisi hal yang sama tiap hari
                // menenggelamkan kejadian yang benar-benar perlu terlihat.
                // Angkanya dilaporkan perintahnya, dan itu cukup.
                $free++;

                continue;
            }

            $issued++;

            if (! $dryRun) {
                $invoice = Invoice::create([
                    'tenant_id' => $tenant->id,
                    'subscription_id' => $subscription->id,
                    'period' => $period,
                    'kind' => Invoice::KIND_SUBSCRIPTION,
                    'amount' => $amount,
                    // Selalu terisi bila ada aturan yang cocok: penerbit ini
                    // menagih persis tarif yang dihitung, jadi tidak ada kasus
                    // "nominal diketik ulang" seperti pada penerbit manual.
                    'pricing_rule_id' => $resolved['rule']?->id,
                    // Rincian pecahannya ikut dibekukan bersama konteks dimensi
                    // (`[BL-053]`(a)). Tagihan yang tidak bisa dijelaskan
                    // pecahannya akan jadi tiket dukungan pertama — dan
                    // menghitungnya ulang belakangan hanya mengembalikan angka
                    // hari ini, bukan angka yang benar-benar ditagihkan.
                    //
                    // Bersarang di satu kunci, bukan disebar sebagai kunci
                    // sejajar: `context` berisi nilai DIMENSI, dan rincian
                    // tagihan yang menumpang di ruang nama yang sama cepat atau
                    // lambat akan bertabrakan dengan dimensi baru.
                    'pricing_context' => $resolved['context'] + [
                        'billing_breakdown' => [
                            'base_price' => $price,
                            'extra_seats' => $seat['seats'],
                            'extra_seat_price' => $seat['unit_price'],
                            'extra_seats_amount' => $seat['amount'],
                            'total' => $amount,
                        ],
                    ],
                    'status' => Invoice::STATUS_UNPAID,
                    // Jatuh tempo = hari periode berjalan habis. Sesudah itu
                    // tenant masuk masa tenggang, bukan langsung tertutup.
                    //
                    // Tidak pernah di masa lalu. Tenant yang ditagih susulan di
                    // masa tenggang periodenya memang sudah lewat, dan tagihan
                    // yang lahir sudah lewat tempo hari itu juga membacanya
                    // seperti tunggakan yang ia abaikan — padahal hari ini
                    // barulah pertama kali ia melihat angkanya.
                    'due_date' => $periodStart->max($today)->toDateString(),
                ]);

                PlatformAuditLog::record('invoices.auto-create', $invoice, [
                    'tenant_id' => $tenant->id,
                    'period' => $period,
                    'amount' => $amount,
                    'base_price' => $price,
                    'extra_seats' => $seat['seats'],
                    'pricing_rule' => $resolved['label'],
                    'source' => $resolved['source'],
                ]);
            }
        }

        return [
            'issued' => $issued,
            'free' => $free,
            'unpriced' => $unpriced,
            'skipped' => $skipped,
        ];
    }

    /**
     * Apakah tenant masih punya sisa seat untuk satu pengguna aktif lagi.
     *
     * Satu-satunya tempat aturan batas seat dituliskan. Titik penegakannya ada
     * dua — menambah staf dan mengaktifkan kembali staf — dan keduanya harus
     * memanggil ini, bukan menyalin logikanya. Aturan yang disalin akan
     * bercabang begitu salah satunya diperbaiki.
     *
     * Pendaftaran tenant baru sengaja TIDAK memanggilnya: owner pertama adalah
     * pengguna ke-0, jadi ia selalu muat, dan memanggilnya di sana hanya
     * menambah query tanpa menutup celah apa pun.
     */
    public function hasSeatFor(Tenant $tenant): bool
    {
        return $this->ensureFor($tenant)->hasSeatAvailable();
    }

    /**
     * Kalimat penolakan saat seat habis — menyebut angkanya dan menunjuk jalan
     * keluar. Penolakan tanpa jalan keluar hanya membuat orang buntu.
     */
    public function seatLimitMessage(Tenant $tenant): string
    {
        $subscription = $this->ensureFor($tenant);

        return sprintf(
            'Paket Anda mencakup %d pengguna aktif dan semuanya sudah terpakai. '
            .'Nonaktifkan salah satu staf, atau tingkatkan paket dari halaman Langganan.',
            $subscription->seats,
        );
    }

    /**
     * Pindahkan langganan ke paket lain.
     *
     * Sampai sekarang `plan_id` ditulis sekali seumur hidup langganan, di
     * `startTrial()`, dan tidak ada satu pun jalur yang mengubahnya lagi
     * (`[BL-046]`(2)). Akibatnya paket kedua hanya bisa dibuat, tidak bisa
     * dihuni — dan keputusan pemilik 2026-08-01 ("tenant beromset tinggi hanya
     * bisa Premium") tidak punya cara ditegakkan sama sekali.
     *
     * **Seat tambahan yang sudah dibeli ikut pindah.** Batas pengguna sebuah
     * langganan adalah jatah paketnya ditambah seat yang dibelinya sendiri.
     * Menyalin `seats` apa adanya akan menelan jatah paket baru bagi tenant yang
     * tak pernah membeli tambahan, sementara menyetelnya ke jatah paket baru saja
     * akan mencabut seat yang sudah dibayar — keduanya kekeliruan yang baru
     * terlihat berbulan-bulan kemudian, saat tenant menabrak batas yang tak
     * pernah ia setujui.
     *
     * Angkanya dibaca dari `purchased_extra_seats`, bukan disimpulkan lagi dari
     * `seats − included_seats` (`[BL-053]`). Selisih itu benar hanya selama
     * tidak ada yang lain yang menggerakkan `seats`; sejak seat tambahan jadi
     * komponen tagihan bulanan, angka yang jadi dasar uang tidak boleh
     * bergantung pada pengurangan yang bisa meleset.
     *
     * **Tarif periode berjalan tidak disentuh.** `price_locked` sudah memegang
     * harga yang disepakati, dan `pricingAsOf()` menetapkan harga periode
     * berikutnya dari aturan yang berdiri saat periodenya dibuka. Pemindahan di
     * tengah periode karena itu berlaku pada tagihan berikutnya, bukan pada
     * tagihan yang sedang berjalan.
     */
    public function changePlan(Subscription $subscription, Plan $plan): void
    {
        $subscription->update([
            'plan_id' => $plan->id,
            'seats' => $plan->included_seats + $subscription->purchased_extra_seats,
        ]);

        $subscription->setRelation('plan', $plan);
    }

    /**
     * Jarak minimum antar perpindahan jalur harga, dalam bulan.
     */
    public static function trackSwitchMinimumMonths(): int
    {
        return (int) config('subscription.track_switch_minimum_months');
    }

    /**
     * Boleh pindah jalur sekarang?
     *
     * Perpindahan pertama selalu boleh — `track_changed_at` masih kosong. Jarak
     * minimum baru berlaku setelahnya, supaya tenant tidak bolak-balik ke jalur
     * subsidi mengikuti bulan ramai dan sepi.
     */
    public function canSwitchTrack(Tenant $tenant): bool
    {
        // Dihitung lewat `trackSwitchAvailableAt()`, bukan dengan menguranginya
        // sendiri dari `now()`. Keduanya setara dalam aritmetika tanggal biasa,
        // tapi TIDAK setara begitu penjaga luberan ikut bermain: 30 Nov + 3
        // bulan dijepit ke 28 Feb, sementara 28 Feb − 3 bulan mendarat di 28
        // Nov. Tanggal yang dipajang di layar akan menjanjikan 28 Februari
        // sementara gerbangnya baru terbuka 2 Maret. Satu perhitungan, satu
        // jawaban.
        $availableAt = $this->trackSwitchAvailableAt($tenant);

        return $availableAt === null || $availableAt->lte(now());
    }

    /**
     * Tanggal paling awal tenant boleh pindah jalur lagi.
     */
    public function trackSwitchAvailableAt(Tenant $tenant): ?Carbon
    {
        $changedAt = $this->ensureFor($tenant)->track_changed_at;

        // Kembaran `canSwitchTrack()` — keduanya wajib memakai penjaga luberan
        // yang sama, kalau tidak tanggal yang dipajang di layar akan berselisih
        // dengan tanggal yang benar-benar ditegakkan.
        return $changedAt?->copy()->addMonthsNoOverflow(self::trackSwitchMinimumMonths());
    }

    /**
     * Pindahkan tenant ke jalur subsidi.
     *
     * Dipanggil HANYA setelah persetujuan jalur subsidi tercatat. Kolom
     * `pricing_track` di `tenants` ikut diubah karena di sanalah gerbang privasi
     * job penghitung omset membaca — filter itu harus tanpa join, sesederhana
     * dan semurah mungkin.
     */
    public function switchToSubsidized(Tenant $tenant): void
    {
        $subscription = $this->ensureFor($tenant);

        $subscription->update([
            'pricing_track' => Subscription::TRACK_SUBSIDIZED,
            'track_changed_at' => now(),
            'track_reverts_at' => null,
        ]);

        $tenant->update(['pricing_track' => Subscription::TRACK_SUBSIDIZED]);
    }

    /**
     * Jadwalkan kembalinya tenant ke jalur normal setelah consent dicabut.
     *
     * Jalurnya BELUM berubah sekarang: harga subsidi tetap berlaku sampai
     * periode berjalan habis, persis seperti yang dijanjikan dokumen consent.
     * Yang berhenti seketika hanyalah pengumpulan datanya — job penghitung omset
     * menyaring berdasarkan persetujuan yang masih aktif, bukan berdasarkan
     * kolom jalur.
     *
     * Ringkasan omset yang sudah ada dihapus di sini juga. Harga yang sedang
     * berjalan tetap bisa dipertanggungjawabkan karena angkanya sudah tersimpan
     * di `price_locked` — jadi tak ada alasan menahan datanya lebih lama.
     */
    public function scheduleTrackRevert(Tenant $tenant): void
    {
        $subscription = $this->ensureFor($tenant);

        $subscription->update([
            'track_reverts_at' => $subscription->current_period_end ?? now()->toDateString(),
        ]);

        TenantMonthlyMetric::where('tenant_id', $tenant->id)->delete();
    }

    /**
     * Belikan tenant seat tambahan. Berlaku seketika, tanpa tagihan tersendiri.
     *
     * Menggantikan `requestSeatUpgrade()`, yang menerbitkan tagihan
     * `KIND_UPGRADE` sekali bayar lalu menunggu bukti transfer sebelum seat-nya
     * berlaku. Sejak seat jadi komponen bulanan (`[BL-053]`), tagihan sekali
     * bayar itu bukan sekadar berlebihan — ia menagih DUA KALI untuk hak yang
     * sama, sekali di muka dan sekali tiap bulan sesudahnya.
     *
     * **Gratis sampai periode berjalan habis** (keputusan pemilik 2026-08-07).
     * Tagihan periode berikutnya sudah memuatnya, jadi tak ada yang lolos: yang
     * ditiadakan hanyalah tagihan di tengah bulan, bukan uangnya. Warung yang
     * kedatangan kasir pagi ini bisa langsung mempekerjakannya — alasan yang
     * sama seperti pemberlakuan provisional dulu, hanya tanpa antrean
     * pemeriksaan yang tidak memeriksa apa pun.
     *
     * **Pelepasan yang sedang menunggu dibatalkan.** Tenant yang menjadwalkan
     * pengurangan lalu berubah pikiran dan membeli lagi jelas tidak sedang
     * meminta keduanya. Membiarkan keduanya hidup berarti seat yang baru dibeli
     * ikut lenyap di tanggal pelepasan, tanpa seorang pun memintanya.
     */
    public function grantSeats(Tenant $tenant, int $additionalSeats): Subscription
    {
        $subscription = $this->ensureFor($tenant);
        $subscription->loadMissing('plan');

        $sebelum = $subscription->purchased_extra_seats;

        $subscription->update([
            'purchased_extra_seats' => $sebelum + $additionalSeats,
            'seats' => $subscription->seats + $additionalSeats,
            'scheduled_extra_seats' => null,
            'seat_release_at' => null,
        ]);

        PlatformAuditLog::record('subscriptions.seats-granted', $subscription, [
            'tenant_id' => $tenant->id,
            'added' => $additionalSeats,
            'extra_seats_before' => $sebelum,
            'extra_seats_after' => $subscription->purchased_extra_seats,
            'seats' => $subscription->seats,
        ]);

        return $subscription;
    }

    /**
     * Jadwalkan pelepasan seat tambahan.
     *
     * Wajib ada sejak tagihan seat mengikuti PEMBELIAN, bukan pemakaian
     * (`[BL-053]`). Selama dasarnya pemakaian puncak, tenant yang mengecil ikut
     * mengecil sendiri; begitu dasarnya pembelian, tanpa jalan keluar ia
     * terkunci membayar selamanya.
     *
     * **Berlaku satu periode penuh ke depan, bukan di akhir periode berjalan.**
     * Tagihan periode berikutnya terbit `invoice_lead_days` SEBELUM periode
     * berjalan habis dan sudah memuat seat itu. Melepasnya di akhir periode
     * berjalan berarti tenant membayar sebulan untuk seat yang sudah dicabut —
     * jendela pakai dan jendela bayar harus berimpit. Sekaligus menutup celah
     * "beli tanggal 1, lepas tanggal 2": tiap seat yang dibeli pasti tertagih
     * sekali, tidak pernah nol kali.
     *
     * **Seat yang masih diduduki staf aktif tidak boleh dilepas** — itu
     * ditegakkan pemanggilnya lewat `seatReleaseCeiling()`, bukan di sini, supaya
     * kalimat penolakannya bisa menyebut angka yang tenant lihat di layarnya.
     */
    public function releaseSeats(Tenant $tenant, int $seats): Subscription
    {
        $subscription = $this->ensureFor($tenant);
        $subscription->loadMissing('plan');

        $target = max(0, $subscription->entitledExtraSeats() - $seats);

        // Dihitung dari akhir periode berjalan, bukan dari hari ini: jangkar
        // tanggal tagihlah yang menentukan batas periode, dan menghitungnya
        // dengan `addMonth()` dari `now()` akan meleset di tiap bulan pendek.
        $releaseAt = $subscription->nextAnchoredDateAfter(
            $subscription->current_period_end ?? now(),
        );

        $subscription->update([
            'scheduled_extra_seats' => $target,
            'seat_release_at' => $releaseAt->toDateString(),
        ]);

        PlatformAuditLog::record('subscriptions.seats-release-scheduled', $subscription, [
            'tenant_id' => $tenant->id,
            'released' => $seats,
            'extra_seats_now' => $subscription->purchased_extra_seats,
            'extra_seats_after' => $target,
            'effective_at' => $releaseAt->toDateString(),
        ]);

        return $subscription;
    }

    /**
     * Paling banyak berapa seat tambahan yang boleh dilepas sekarang.
     *
     * Dua batas sekaligus, dan yang terketat menang: tenant tidak bisa melepas
     * lebih banyak daripada yang ia beli, dan tidak bisa melepas seat yang masih
     * diduduki staf aktif (keputusan pemilik 2026-08-07). Yang kedua ditolak,
     * bukan dipaksakan dengan mengunci akun: pelepasan seat yang diam-diam
     * mematikan akun kasir di tengah jam kerja adalah kerugian yang jauh lebih
     * besar daripada sebulan tagihan yang tertunda.
     */
    public function seatReleaseCeiling(Subscription $subscription): int
    {
        $subscription->loadMissing('plan');

        $dibeli = $subscription->entitledExtraSeats();
        $ruangKosong = $subscription->seats - $subscription->activeSeatsUsed();

        return max(0, min($dibeli, $ruangKosong));
    }

    /**
     * Berlakukan pelepasan seat yang tanggalnya sudah tiba.
     *
     * Menumpang di `advanceLifecycle()` bersama tenggat-tenggat lain, bukan
     * sebagai perintah terjadwal sendiri — satu jadwal yang lupa dipasang cukup
     * untuk membuat tenant terus tertagih atas seat yang sudah ia lepas
     * berbulan-bulan lalu.
     *
     * @return int berapa langganan yang seat-nya benar-benar turun
     */
    public function applyDueSeatReleases(bool $dryRun = false): int
    {
        $due = Subscription::query()
            ->whereNotNull('seat_release_at')
            ->whereNotNull('scheduled_extra_seats')
            ->whereDate('seat_release_at', '<=', now()->startOfDay())
            ->with('plan')
            ->get();

        if ($dryRun) {
            return $due->count();
        }

        foreach ($due as $subscription) {
            $sebelum = $subscription->purchased_extra_seats;
            $target = (int) $subscription->scheduled_extra_seats;

            $subscription->update([
                'purchased_extra_seats' => $target,
                // `seats` diturunkan sebanyak yang dilepas, bukan disetel ulang
                // ke `included_seats + target`. Keduanya biasanya sama, dan
                // berbeda persis ketika pemilik SaaS pernah menyetel `seats`
                // manual dari panel — angka yang ia ketik tidak boleh hilang
                // sebagai efek samping pelepasan seat oleh tenant.
                'seats' => max(
                    $subscription->plan?->included_seats ?? 0,
                    $subscription->seats - ($sebelum - $target),
                ),
                'scheduled_extra_seats' => null,
                'seat_release_at' => null,
            ]);

            PlatformAuditLog::record('subscriptions.seats-released', $subscription, [
                'tenant_id' => $subscription->tenant_id,
                'extra_seats_before' => $sebelum,
                'extra_seats_after' => $target,
                'seats' => $subscription->seats,
            ]);
        }

        return $due->count();
    }

    /**
     * Tagihan upgrade yang masih terbuka, bila ada.
     *
     * **Peninggalan.** Tak ada lagi yang menerbitkan `KIND_UPGRADE` sejak seat
     * jadi komponen bulanan (`[BL-053]`); yang bisa ditemukannya hanyalah
     * tagihan yang terbit sebelum itu dan belum selesai. Ia tetap dipakai
     * sebagai penjaga di jalur pembelian seat: melunasi tagihan upgrade lama
     * menulis `seats = grants_seats`, angka dari dunia lama yang akan MENURUNKAN
     * jatah tenant yang sudah membeli seat lewat jalur baru.
     */
    public function openUpgradeInvoice(Tenant $tenant): ?Invoice
    {
        return $tenant->invoices()
            ->where('kind', Invoice::KIND_UPGRADE)
            ->whereIn('status', [Invoice::STATUS_UNPAID, Invoice::STATUS_AWAITING_VERIFICATION])
            ->latest('id')
            ->first();
    }

    /**
     * Tagihan yang masih menuntut perhatian tenant — yang paling mendesak
     * lebih dulu.
     *
     * Ketiga status non-lunas ikut dihitung terbuka, masing-masing dengan
     * alasannya sendiri. `rejected` justru yang paling perlu terlihat: buktinya
     * ditolak, jadi tagihannya kembali menunggu tindakan. `awaiting_verification`
     * memang tidak menuntut apa-apa dari tenant, tapi menyembunyikannya membuat
     * ia mengira tak ada tagihan sama sekali sampai buktinya ternyata ditolak.
     *
     * Diurut menurut jatuh tempo, bukan menurut id: tagihan upgrade terbit di
     * tengah periode dan bisa jatuh tempo lebih dulu daripada tagihan bulanan
     * yang nomornya lebih kecil.
     */
    public function outstandingInvoice(Tenant $tenant): ?Invoice
    {
        return $tenant->invoices()
            ->whereIn('status', [
                Invoice::STATUS_UNPAID,
                Invoice::STATUS_REJECTED,
                Invoice::STATUS_AWAITING_VERIFICATION,
            ])
            ->orderBy('due_date')
            ->orderBy('id')
            ->first();
    }

    /**
     * Berlakukan upgrade sebelum buktinya diperiksa.
     *
     * Fasilitas ini dicabut untuk tenant yang buktinya pernah ditolak — mereka
     * tetap boleh naik paket, hanya saja seat-nya baru berlaku setelah
     * diperiksa.
     */
    public function applyProvisionalUpgrade(Invoice $invoice): bool
    {
        $subscription = $invoice->subscription;

        if (! $invoice->isUpgrade() || $subscription->provisional_blocked) {
            return false;
        }

        $subscription->update(['seats' => $invoice->grants_seats]);

        return true;
    }

    /**
     * Kembalikan seat ke angka semula setelah bukti bayar ditolak.
     *
     * Akun staf yang terlanjur dibuat sengaja TIDAK disentuh. Akibatnya jumlah
     * pengguna aktif bisa melampaui seat — dan justru itu yang diinginkan:
     * penambahan berikutnya tertutup sendirinya, tanpa mengusir siapa pun dari
     * pekerjaannya.
     */
    public function revertUpgrade(Invoice $invoice): void
    {
        if (! $invoice->isUpgrade() || $invoice->previous_seats === null) {
            return;
        }

        $invoice->subscription->update([
            'seats' => $invoice->previous_seats,
            'provisional_blocked' => true,
        ]);
    }

    /**
     * Pindahkan tenant ke keadaan berikutnya bila tenggatnya sudah lewat.
     *
     * Dua perpindahan, keduanya digerakkan oleh `current_period_end` — kolom
     * yang sama dipakai baik untuk akhir masa coba maupun akhir periode
     * berbayar, jadi tidak ada dua sumber tanggal yang bisa saling berselisih:
     *
     *   trial|active  → grace      begitu periodenya lewat
     *   grace         → suspended  setelah masa tenggang habis
     *
     * Sekalian memproses kembalinya tenant ke jalur normal setelah consent
     * subsidinya dicabut — keduanya sama-sama "tenggat yang sudah lewat", dan
     * memisahkannya jadi dua perintah terjadwal hanya menambah satu hal lagi
     * yang bisa lupa dipasang.
     *
     * Penerbitan tagihan menumpang di sini karena alasan yang sama, dan lebih
     * kuat: ia harus berjalan SEBELUM tenant dipindahkan ke masa tenggang
     * (`[BL-044]`). Sebagai perintah terjadwal sendiri, urutan itu bersandar
     * pada dua baris jadwal yang kebetulan ditulis berurutan — dan jadwal yang
     * kebetulan benar akan salah pada hari seseorang menggesernya. Di sini
     * keduanya tak terpisahkan.
     *
     * Penerbitan tagihan TIDAK mengubah siapa yang berpindah keadaan. Tenant
     * yang tak bisa ditagih tetap menempuh masa tenggang dan penangguhan seperti
     * biasa — keputusan pemilik 2026-08-05, supaya tidak ada jaminan lama yang
     * diam-diam tercabut oleh tarif yang kebetulan belum ditetapkan.
     *
     * Perpindahan paket akhir masa gratis (`[BL-052]`) menumpang di sini dengan
     * alasan yang sama sekali lagi, dan lebih keras: ia harus berjalan SEBELUM
     * penerbitan tagihan. Terbalik, tagihan periode berbayar pertama dihitung
     * dari paket gratis seharga Rp 0, dilewati sebagai "tidak ada yang perlu
     * ditagih", dan tenantnya turun ke masa tenggang tanpa pernah melihat
     * angka — persis keadaan yang perpindahan ini tutup.
     *
     * Pelepasan seat (`[BL-053]`) ikut di sini, dan urutannya juga mengikat: ia
     * berjalan SESUDAH penerbitan tagihan. Terbalik, seat yang tanggal
     * lepasnya jatuh tepat di hari penerbitan akan hilang lebih dulu, dan
     * tagihan periode itu — periode yang seat-nya masih sah dipakai — terbit
     * tanpa memuatnya. Tenant mendapat sebulan gratis, tiap kali tanggalnya
     * kebetulan berimpit.
     *
     * @return array{expired: int, suspended: int, reverted: int, invoiced: int, free: int, unpriced: int, skipped: int, graduated: int, stranded: int, seats_released: int}
     */
    public function advanceLifecycle(bool $dryRun = false): array
    {
        $today = now()->startOfDay();
        $graceCutoff = $today->copy()->subDays(self::graceDays());

        $graduation = $this->graduateExpiredTrials($dryRun);
        $billing = $this->issueDuePeriodInvoices($dryRun);
        $seatsReleased = $this->applyDueSeatReleases($dryRun);

        $expiring = fn () => Tenant::query()
            ->whereIn('status', [Tenant::STATUS_TRIAL, Tenant::STATUS_ACTIVE])
            ->whereHas('subscription', fn ($query) => $query->whereDate('current_period_end', '<', $today));

        $suspending = fn () => Tenant::query()
            ->where('status', Tenant::STATUS_GRACE)
            ->whereHas('subscription', fn ($query) => $query->whereDate('current_period_end', '<', $graceCutoff));

        $reverting = fn () => Subscription::query()
            ->whereNotNull('track_reverts_at')
            ->whereDate('track_reverts_at', '<=', $today);

        $expiredCount = $expiring()->count();
        $suspendedCount = $suspending()->count();
        $revertedCount = $reverting()->count();

        if (! $dryRun) {
            foreach ($reverting()->get() as $subscription) {
                $subscription->update([
                    'pricing_track' => Subscription::TRACK_NORMAL,
                    'track_reverts_at' => null,
                    // `track_changed_at` sengaja TIDAK disetel ulang di sini.
                    // Jarak minimum dihitung dari perpindahan yang dipilih
                    // tenant, bukan dari kembalinya otomatis — kalau tidak,
                    // mencabut consent malah memperpanjang masa tunggunya.
                ]);

                $subscription->tenant->update(['pricing_track' => Subscription::TRACK_NORMAL]);
            }
        }

        if (! $dryRun) {
            // Penangguhan dijalankan LEBIH DULU. Dengan urutan sebaliknya, tenant
            // yang baru saja dipindah ke `grace` di baris atas akan langsung ikut
            // tersaring penangguhan di jalan yang sama — trial yang terbengkalai
            // dua bulan melompat ke `suspended` tanpa pernah melewati masa
            // tenggang yang dijanjikan kepadanya.
            $suspending()->update(['status' => Tenant::STATUS_SUSPENDED]);
            $expiring()->update(['status' => Tenant::STATUS_GRACE]);
        }

        return [
            'expired' => $expiredCount,
            'suspended' => $suspendedCount,
            'reverted' => $revertedCount,
            'invoiced' => $billing['issued'],
            'free' => $billing['free'],
            'unpriced' => $billing['unpriced'],
            'skipped' => $billing['skipped'],
            'graduated' => $graduation['graduated'],
            'stranded' => $graduation['stranded'],
            'seats_released' => $seatsReleased,
        ];
    }
}
