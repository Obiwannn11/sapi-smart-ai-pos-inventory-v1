<?php

namespace App\Services\Ai;

use App\Models\AiQuotaPolicy;
use App\Models\AiUsage;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;

/**
 * Satu-satunya pembaca kuota AI harian.
 *
 * Berdiri sendiri karena angkanya dibaca DUA permukaan yang tak boleh
 * berselisih: `RunAiAnalysisJob` yang menolak permintaan, dan halaman
 * Pengaturan yang memberitahu owner sisa jatahnya. Selama keduanya menghitung
 * sendiri-sendiri, cepat atau lambat layar akan menjanjikan sisa yang
 * ditolak antrean — dan yang disalahkan owner adalah aplikasinya, bukan
 * dua tempat yang lupa disamakan.
 *
 * Urutan pembacaannya tunggal dan tetap (`[BL-047]`(b)):
 *
 *   1. Batas paket langganan tenant (`plans.limits.ai_daily`)
 *   2. Kebijakan bawaan platform yang berlaku (`ai_quota_policies`, `baseline`)
 *   3. Bawaan platform di `config/ai.php`
 *
 * lalu di ATAS hasilnya, promo yang sedang berlaku (`bonus`) menambahkan
 * jatahnya.
 *
 * Promo sengaja TIDAK ikut jadi mata rantai di urutan itu, meski `[BL-047]`(a)
 * menyebutnya "di antara" — sebab setiap paket hari ini menetapkan
 * `limits.ai_daily`-nya sendiri, sehingga lapis yang hanya mengisi kekosongan
 * bawaan tidak akan pernah terbaca oleh satu pun tenant yang berlangganan. Yang
 * diminta catatan pemilik ("promo dalam waktu tertentu") baru berarti sesuatu
 * bila ia bisa menambah jatah orang yang sudah punya jatah.
 *
 * Kuota hanya berlaku saat tenant memakai kunci bersama milik aplikasi. Tenant
 * yang mengisi kunci API-nya sendiri membayar pemakaiannya sendiri, jadi tidak
 * ada yang perlu dijatah — keputusan itu tetap milik `AiProviderFactory`,
 * karena metode di bawah menjawab "berapa jatahnya", bukan "apakah ia perlu
 * dijatah".
 *
 * `snapshotFor()` adalah satu-satunya pengecualian, dan pengecualian yang
 * disengaja: sejak `[BL-062]` angka ini dibaca DUA layar — Pengaturan dan AI
 * Analysis — dan keduanya butuh kedua jawaban itu sekaligus. Merakitnya di
 * masing-masing controller berarti syarat BYOK ditulis dua kali, dan yang
 * pertama kali salah menuliskannya akan memasang "sisa 0 dari 5" di layar
 * tenant yang justru tak berbatas. Jadi perakitannya dikunci di sini, sementara
 * pertanyaan "perlu dijatah?" tetap didelegasikan, tidak ditiru.
 */
class AiQuota
{
    /**
     * Kebijakan yang berlaku, dibaca sekali per instance.
     *
     * Kebijakannya berlaku untuk seluruh platform — tidak bergantung tenant —
     * sementara pemanggilnya membaca angka yang sama beberapa kali dalam satu
     * permintaan (`snapshotFor()` sendiri sudah dua kali). Tanpa ini, satu layar
     * Pengaturan menghasilkan query kebijakan berulang yang jawabannya sudah
     * pasti sama.
     *
     * @var array<string, AiQuotaPolicy|null>
     */
    protected array $policies = [];

    public function __construct(protected AiProviderFactory $providers) {}

    /**
     * Seluruh keadaan kuota yang dibutuhkan layar, dalam satu bentuk.
     *
     * Dipakai apa adanya oleh Pengaturan (versi lengkap) dan AI Analysis (versi
     * ringkas di sebelah tombol kirim). Keduanya membedakan tiga keadaan yang
     * di antrean berakhir sebagai tiga pesan galat berbeda, jadi ketiganya
     * harus bisa dibedakan dari data ini saja:
     *
     *   - `using_free_tier` salah → tenant ber-BYOK, angka kuota tidak berlaku
     *   - `daily_limit` nol       → paketnya tidak menyertakan analisis AI
     *   - `remaining` nol         → jatah hari ini sudah dibelanjakan
     *
     * `used` sengaja nol untuk tenant ber-BYOK: pemakaian mereka memang tidak
     * pernah dicatat di `ai_usages`, dan membacakan angka basi dari masa
     * sebelum kuncinya diisi hanya akan membingungkan.
     *
     * `bonus` dan `bonus_label` dikirim terpisah, bukan sudah lebur ke
     * `daily_limit`: promo yang menaikkan jatah tanpa menyebut namanya terbaca
     * sebagai jatah tetap, dan owner yang sudah terbiasa dengan angka itu akan
     * mengira aplikasinya rusak pada hari promonya berakhir.
     *
     * @return array{using_free_tier: bool, daily_limit: int, used: int, remaining: int, limit_source: string, plan_name: string|null, bonus: int, bonus_label: string|null}
     */
    public function snapshotFor(Tenant $tenant): array
    {
        $usingFreeTier = $this->providers->isUsingFreeTier($tenant);
        $plan = $this->planFor($tenant);
        $base = $this->baseLimitFor($tenant);
        $bonus = $this->bonusOn($base);
        $limit = $base + $bonus;
        $used = $usingFreeTier ? $this->usedTodayBy($tenant) : 0;

        return [
            'using_free_tier' => $usingFreeTier,
            'daily_limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            // Dari mana batasnya datang — supaya owner yang merasa jatahnya
            // kurang tahu apakah yang perlu diubah itu paketnya, atau memang
            // bawaan platform yang berlaku karena ia belum berlangganan.
            'limit_source' => $this->limitSourceFor($plan),
            'plan_name' => $plan?->name,
            'bonus' => $bonus,
            'bonus_label' => $bonus > 0 ? $this->policy(AiQuotaPolicy::MODE_BONUS)?->label : null,
        ];
    }

    /**
     * Batas analisis AI per hari untuk tenant ini, promo yang berlaku termasuk.
     */
    public function dailyLimitFor(Tenant $tenant): int
    {
        $base = $this->baseLimitFor($tenant);

        return $base + $this->bonusOn($base);
    }

    /**
     * Berapa yang sudah terpakai hari ini.
     *
     * `withoutGlobalScopes` karena pemanggilnya termasuk job yang berjalan
     * tanpa sesi; tenant-nya disaring di sini secara eksplisit.
     */
    public function usedTodayBy(Tenant $tenant): int
    {
        return (int) (AiUsage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('date', now())
            ->value('count') ?? 0);
    }

    public function remainingFor(Tenant $tenant): int
    {
        return max(0, $this->dailyLimitFor($tenant) - $this->usedTodayBy($tenant));
    }

    /**
     * Pemakaian hari ini di seluruh tenant, sebagai angka gabungan saja.
     *
     * Sengaja tanpa rincian per tenant, dan itu batas yang dijaga
     * `PlatformArchTest`: panel platform boleh tahu seberapa besar tagihan kunci
     * bersama yang sedang tumbuh hari ini — itu uangnya sendiri — tapi tidak
     * boleh tahu tenant mana yang memakainya sebanyak apa.
     *
     * @return array{tenants: int, analyses: int}
     */
    public function usageTodaySummary(): array
    {
        $rows = AiUsage::withoutGlobalScopes()->whereDate('date', now());

        return [
            'tenants' => (clone $rows)->count(),
            'analyses' => (int) $rows->sum('count'),
        ];
    }

    /**
     * Nolkan hitungan pemakaian hari ini untuk SELURUH tenant (`[BL-047]`(d)).
     *
     * Tindakan tersendiri, bukan efek samping mengubah angka kuota, karena
     * keduanya berbeda arti: menaikkan kuota mengubah jatah mulai sekarang,
     * sedangkan "reset semua" mengembalikan jatah yang SUDAH terpakai hari ini
     * kepada semua orang. Menyatukannya di satu tombol berarti pemilik SaaS yang
     * hanya ingin merapikan angka bawaan diam-diam membelanjakan ulang kuota
     * sehari penuh.
     *
     * Barisnya dihapus, bukan disetel nol: `ai_usages` menyimpan satu baris per
     * (tenant, tanggal), dan ketiadaan baris sudah berarti nol — lihat
     * `usedTodayBy()`. Menyisakan baris nol hanya menambah bentuk kedua untuk
     * keadaan yang sama.
     *
     * @return int Jumlah tenant yang hitungannya dikembalikan.
     */
    public function resetUsageToday(): int
    {
        return AiUsage::withoutGlobalScopes()->whereDate('date', now())->delete();
    }

    /**
     * Batas sebelum promo: paket → kebijakan bawaan → `config/ai.php`.
     */
    protected function baseLimitFor(Tenant $tenant): int
    {
        $bawaan = $this->baselineLimit();

        return $this->planFor($tenant)?->limit(Plan::LIMIT_AI_DAILY, $bawaan) ?? $bawaan;
    }

    /**
     * Kuota bawaan platform: kebijakan yang berlaku, atau `config/ai.php`.
     *
     * Config tetap jadi lapis terakhir, bukan dihapus. Selama tabel kebijakannya
     * kosong, tidak ada satu pun tenant yang jatahnya berubah oleh pemasangan
     * fitur ini — dan pemasangan yang diam-diam mengubah kuota orang adalah
     * persis hal yang entri ini dibangun untuk mencegahnya.
     */
    protected function baselineLimit(): int
    {
        return $this->policy(AiQuotaPolicy::MODE_BASELINE)?->daily_limit
            ?? (int) config('ai.free_tier.daily_limit');
    }

    /**
     * Tambahan promo yang berlaku di atas `$base`.
     *
     * Nol saat `$base` nol, dan itu aturan yang disengaja: batas nol berarti
     * "paket ini memang tidak menyertakan analisis AI" (`Plan::limit()`
     * membedakannya dari "paket tidak menetapkan batas"). Promo umum tidak boleh
     * diam-diam membukakan fitur yang sebuah paket sengaja tidak menjualnya.
     */
    protected function bonusOn(int $base): int
    {
        if ($base <= 0) {
            return 0;
        }

        return $this->policy(AiQuotaPolicy::MODE_BONUS)?->daily_limit ?? 0;
    }

    /**
     * Nama lapis yang menentukan batas dasarnya: `plan`, `policy`, `platform`.
     */
    protected function limitSourceFor(?Plan $plan): string
    {
        if ($plan?->limit(Plan::LIMIT_AI_DAILY) !== null) {
            return 'plan';
        }

        return $this->policy(AiQuotaPolicy::MODE_BASELINE) !== null ? 'policy' : 'platform';
    }

    protected function policy(string $mode): ?AiQuotaPolicy
    {
        return $this->policies[$mode] ??= AiQuotaPolicy::winnerFor($mode);
    }

    /**
     * Paket yang sedang dipakai tenant, bila langganannya ada.
     *
     * Tenant tanpa langganan sama sekali bukan keadaan yang mustahil — ia
     * muncul di data lama dan di pengujian — dan ia tidak boleh berakhir
     * sebagai galat. Yang berlaku baginya adalah bawaan platform.
     */
    protected function planFor(Tenant $tenant): ?Plan
    {
        return Subscription::where('tenant_id', $tenant->id)->first()?->plan;
    }
}
