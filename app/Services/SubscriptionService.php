<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use Illuminate\Support\Carbon;

class SubscriptionService
{
    /**
     * Panjang masa coba untuk tenant baru, dalam hari.
     */
    public static function trialDays(): int
    {
        return (int) config('subscription.trial_days');
    }

    /**
     * Panjang masa tenggang hanya-baca sebelum penangguhan, dalam hari.
     */
    public static function graceDays(): int
    {
        return (int) config('subscription.grace_days');
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
     * Buka masa coba 1 bulan di paket dasar, jalur harga normal.
     *
     * Jalur normal adalah default yang disengaja: tenant belum menyetujui apa
     * pun soal pembukaan data omset, jadi jalur subsidi tidak boleh menjadi
     * keadaan awal siapa pun.
     */
    public function startTrial(Tenant $tenant): Subscription
    {
        $plan = Plan::default();
        $trialEndsAt = now()->addDays(self::trialDays());

        return $tenant->subscription()->create([
            'plan_id' => $plan->id,
            'pricing_track' => Subscription::TRACK_NORMAL,
            'seats' => $plan->included_seats,
            'seat_high_water' => 1,
            'price_locked' => null,
            'trial_ends_at' => $trialEndsAt,
            'current_period_start' => now()->toDateString(),
            'current_period_end' => $trialEndsAt->toDateString(),
            // Jangkar tanggal tagih lahir di sini dan tidak pernah berubah lagi.
            // Ia harus ditulis sekarang, bukan disimpulkan belakangan: begitu
            // sebuah periode berakhir di bulan pendek, tanggalnya sudah terjepit
            // dan hari aslinya tak bisa dipulihkan dari data mana pun.
            'billing_anchor_day' => $trialEndsAt->day,
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
     * sudah usai lalu langsung menangguhkannya lagi. Keputusan ini perlu
     * ditinjau ulang saat `[BL-044]` dikerjakan: begitu tagihan terbit otomatis
     * tiap periode, tiap bulan yang terlewat punya tagihannya sendiri dan
     * "melompati" periode berarti melompati tagihan.
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
     * Terbitkan tagihan penambahan seat untuk tenant.
     *
     * Tagihan upgrade sengaja dipisahkan dari tagihan langganan lewat kolom
     * `kind`: warung yang butuh kasir tambahan di pertengahan bulan harus tetap
     * bisa membayar meski tagihan bulanannya sudah terbit.
     */
    public function requestSeatUpgrade(Tenant $tenant, int $additionalSeats): Invoice
    {
        $subscription = $this->ensureFor($tenant);
        $subscription->loadMissing('plan');

        return Invoice::create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'period' => now()->format('Y-m'),
            'kind' => Invoice::KIND_UPGRADE,
            'grants_seats' => $subscription->seats + $additionalSeats,
            'previous_seats' => $subscription->seats,
            'amount' => $subscription->plan->extra_seat_price * $additionalSeats,
            'status' => Invoice::STATUS_UNPAID,
            'due_date' => now()->addDays(7)->toDateString(),
        ]);
    }

    /**
     * Tagihan upgrade yang masih terbuka, bila ada.
     *
     * Dipakai untuk membatasi satu upgrade berjalan dalam satu waktu. Tanpa
     * batas itu, seat bisa dinaikkan berkali-kali hanya dengan mengunggah
     * berkas apa pun dan tak pernah membayar.
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
     * @return array{expired: int, suspended: int, reverted: int}
     */
    public function advanceLifecycle(bool $dryRun = false): array
    {
        $today = now()->startOfDay();
        $graceCutoff = $today->copy()->subDays(self::graceDays());

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
        ];
    }
}
