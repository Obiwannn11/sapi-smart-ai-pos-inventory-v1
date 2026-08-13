<?php

namespace App\Services\Ai;

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
 *   2. Bawaan platform di `config/ai.php`
 *
 * Kebijakan berjangka waktu — promo, reset serentak — belum masuk urutan ini;
 * tempatnya kelak di antara keduanya. Lihat `[BL-047]`(a).
 *
 * Kuota hanya berlaku saat tenant memakai kunci bersama milik aplikasi. Tenant
 * yang mengisi kunci API-nya sendiri membayar pemakaiannya sendiri, jadi tidak
 * ada yang perlu dijatah — keputusan itu tetap milik `AiProviderFactory`,
 * karena tiga metode di bawah menjawab "berapa jatahnya", bukan "apakah ia
 * perlu dijatah".
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
     * @return array{using_free_tier: bool, daily_limit: int, used: int, remaining: int, limit_source: string, plan_name: string|null}
     */
    public function snapshotFor(Tenant $tenant): array
    {
        $usingFreeTier = $this->providers->isUsingFreeTier($tenant);
        $plan = $this->planFor($tenant);
        $limit = $this->dailyLimitFor($tenant);
        $used = $usingFreeTier ? $this->usedTodayBy($tenant) : 0;

        return [
            'using_free_tier' => $usingFreeTier,
            'daily_limit' => $limit,
            'used' => $used,
            'remaining' => max(0, $limit - $used),
            // Dari mana batasnya datang — supaya owner yang merasa jatahnya
            // kurang tahu apakah yang perlu diubah itu paketnya, atau memang
            // bawaan platform yang berlaku karena ia belum berlangganan.
            'limit_source' => $plan?->limit(Plan::LIMIT_AI_DAILY) === null ? 'platform' : 'plan',
            'plan_name' => $plan?->name,
        ];
    }

    /**
     * Batas analisis AI per hari untuk tenant ini.
     */
    public function dailyLimitFor(Tenant $tenant): int
    {
        $bawaan = (int) config('ai.free_tier.daily_limit');

        return $this->planFor($tenant)?->limit(Plan::LIMIT_AI_DAILY, $bawaan) ?? $bawaan;
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
