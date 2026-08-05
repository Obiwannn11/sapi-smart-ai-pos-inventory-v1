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
 * ada yang perlu dijatah — pemeriksaan itu tinggal di pemanggilnya, karena
 * kelas ini menjawab "berapa jatahnya", bukan "apakah ia perlu dijatah".
 */
class AiQuota
{
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
