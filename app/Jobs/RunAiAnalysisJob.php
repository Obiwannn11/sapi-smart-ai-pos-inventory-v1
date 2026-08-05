<?php

namespace App\Jobs;

use App\Models\AiAnalysis;
use App\Models\AiUsage;
use App\Models\Tenant;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AiQuota;
use App\Services\AiContextService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class RunAiAnalysisJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(public int $analysisId) {}

    public function handle(AiContextService $context, AiProviderFactory $factory): void
    {
        // withoutGlobalScopes: Job jalan tanpa auth(), scope tenant manual via relasi.
        $analysis = AiAnalysis::withoutGlobalScopes()->findOrFail($this->analysisId);
        $tenant = $analysis->tenant;

        // Defense-in-depth: job berjalan tanpa middleware, dan bisa sudah
        // mengantre saat flag dimatikan. Diperiksa SEBELUM kuota dan sebelum
        // provider dipanggil — terbalik berarti tenant yang fiturnya mati
        // tetap menghabiskan jatah hariannya.
        if (! $tenant->hasFeature('ai')) {
            $analysis->update([
                'status' => AiAnalysis::STATUS_FAILED,
                'error' => 'Fitur AI tidak aktif untuk outlet ini.',
            ]);

            return;
        }

        $analysis->update(['status' => AiAnalysis::STATUS_PROCESSING]);

        // AiContextService & ProfitService memakai TenantScope berbasis auth().
        // Job tak punya sesi, jadi autentikasi sebagai pemilik analisis agar seluruh
        // query konteks ter-scope ke tenant yang benar.
        Auth::setUser($analysis->user);

        try {
            $usingFreeTier = $factory->isUsingFreeTier($tenant);
            if ($usingFreeTier) {
                $this->assertQuota($tenant);
            }

            $from = Carbon::parse($analysis->params['from']);
            $to = Carbon::parse($analysis->params['to'])->endOfDay();

            $data = $context->buildContext($tenant, $from, $to);
            [$system, $user] = $this->prompts($analysis->type, $analysis->prompt);

            $result = $factory->for($tenant)->generate($system, $data, $user);

            $analysis->update([
                'status' => AiAnalysis::STATUS_COMPLETED,
                'result' => $result->text,
                'tokens_used' => $result->tokensUsed,
            ]);

            if ($usingFreeTier) {
                $this->incrementUsage($tenant);
            }
        } catch (\Throwable $e) {
            $analysis->update([
                'status' => AiAnalysis::STATUS_FAILED,
                'error' => $e->getMessage(),
            ]);
        } finally {
            Auth::forgetGuards();
        }
    }

    /**
     * Batasnya datang dari paket langganan tenant, bukan lagi dari satu angka
     * yang sama untuk semua orang — lihat `App\Services\Ai\AiQuota`.
     */
    private function assertQuota(Tenant $tenant): void
    {
        $quota = app(AiQuota::class);
        $limit = $quota->dailyLimitFor($tenant);

        if ($limit <= 0) {
            throw new RuntimeException('Paket ini tidak menyertakan analisis AI. Isi API key sendiri di Pengaturan, atau naikkan paket.');
        }

        if ($quota->usedTodayBy($tenant) >= $limit) {
            throw new RuntimeException("Kuota AI hari ini habis ({$limit}/hari). Isi API key sendiri di Pengaturan untuk pemakaian tanpa batas.");
        }
    }

    /**
     * Naikkan hitungan pemakaian hari ini.
     *
     * Barisnya dicari dengan `whereDate`, bukan `firstOrCreate` berkunci
     * tanggal, dan bedanya bukan gaya penulisan: kolom `date` tersimpan sebagai
     * datetime, sehingga `where('date', '2026-08-01')` tidak pernah cocok
     * dengan baris berisi `2026-08-01 00:00:00`. `firstOrCreate` yang tidak
     * menemukan barisnya lalu mencoba menyisipkan baris kedua untuk (tenant,
     * tanggal) yang sama — dan indeks uniknya menolak. Akibatnya analisis KEDUA
     * seorang tenant di hari yang sama gagal, padahal jatahnya masih ada.
     */
    private function incrementUsage(Tenant $tenant): void
    {
        $usage = AiUsage::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereDate('date', now())
            ->first();

        if ($usage === null) {
            $usage = AiUsage::withoutGlobalScopes()->create([
                'tenant_id' => $tenant->id,
                'date' => now()->toDateString(),
                'count' => 0,
            ]);
        }

        $usage->increment('count');
    }

    /**
     * @return array{0: string, 1: string} [systemPrompt, userPrompt]
     */
    private function prompts(string $type, ?string $custom): array
    {
        $system = 'Kamu analis bisnis F&B. Berdasarkan DATA agregat berikut, beri jawaban ringkas, actionable, dalam Bahasa Indonesia, dengan angka konkret. Jangan mengarang data di luar yang diberikan.';

        $user = match ($type) {
            AiAnalysis::TYPE_DISCOUNT => 'Item mana yang sebaiknya didiskon dan berapa besarannya? Pertimbangkan margin per item, produk terlaris, dan dead stock.',
            AiAnalysis::TYPE_PROFIT_PROJECTION => 'Jelaskan profit aktual periode ini, proyeksi periode berikutnya, dan rekomendasi menaikkan profit.',
            AiAnalysis::TYPE_CUSTOM => $custom ?: 'Beri insight bisnis dari data ini.',
            default => 'Beri insight bisnis umum, anomali, dan rekomendasi dari data ini.',
        };

        return [$system, $user];
    }
}
