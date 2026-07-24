<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

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
        ]);
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
     * Pindahkan tenant ke keadaan berikutnya bila tenggatnya sudah lewat.
     *
     * Dua perpindahan, keduanya digerakkan oleh `current_period_end` — kolom
     * yang sama dipakai baik untuk akhir masa coba maupun akhir periode
     * berbayar, jadi tidak ada dua sumber tanggal yang bisa saling berselisih:
     *
     *   trial|active  → grace      begitu periodenya lewat
     *   grace         → suspended  setelah masa tenggang habis
     *
     * @return array{expired: int, suspended: int}
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

        $expiredCount = $expiring()->count();
        $suspendedCount = $suspending()->count();

        if (! $dryRun) {
            // Penangguhan dijalankan LEBIH DULU. Dengan urutan sebaliknya, tenant
            // yang baru saja dipindah ke `grace` di baris atas akan langsung ikut
            // tersaring penangguhan di jalan yang sama — trial yang terbengkalai
            // dua bulan melompat ke `suspended` tanpa pernah melewati masa
            // tenggang yang dijanjikan kepadanya.
            $suspending()->update(['status' => Tenant::STATUS_SUSPENDED]);
            $expiring()->update(['status' => Tenant::STATUS_GRACE]);
        }

        return ['expired' => $expiredCount, 'suspended' => $suspendedCount];
    }
}
