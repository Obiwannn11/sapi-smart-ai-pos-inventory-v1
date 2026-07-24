<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use App\Services\PricingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rincian omzet satu tenant jalur subsidi.
 *
 * Halaman ini adalah SATU-SATUNYA tempat angka rupiah omzet klien terlihat.
 * Daftar langganan hanya menampilkan kelompok harganya. Bedanya halus tapi
 * nyata: yang pertama membuka data saat memang dibutuhkan, yang kedua akan
 * membukanya terus-menerus di layar sehari-hari.
 *
 * Membukanya SELALU tercatat sebagai kejadian `sensitive` — tanpa deduplikasi.
 * Inilah yang membuat janji di dokumen persetujuan ("setiap kali itu dilakukan,
 * tercatat di jejak audit") bisa dibuktikan, bukan sekadar diucapkan.
 *
 * Membaca HANYA dari `tenant_monthly_metrics`. Model operasional tenant tidak
 * boleh diimpor di sini — ditegakkan PlatformArchTest.
 */
class RevenueController extends Controller
{
    public function show(Request $request, Tenant $tenant, PricingService $pricing): Response
    {
        // Tenant jalur normal tidak punya rincian omzet untuk dilihat, dan
        // halamannya pun tidak boleh terbuka untuk mereka. Menampilkan halaman
        // kosong akan mengaburkan batas yang justru ingin ditegaskan.
        abort_unless($tenant->pricing_track === Subscription::TRACK_SUBSIDIZED, 404);

        $metrics = TenantMonthlyMetric::where('tenant_id', $tenant->id)
            ->orderByDesc('period')
            ->get()
            ->map(fn (TenantMonthlyMetric $metric) => [
                'period' => $metric->period,
                'revenue' => (float) $metric->revenue,
                'transaction_count' => $metric->transaction_count,
                'bracket' => $pricing->bracketFor((float) $metric->revenue)['label'] ?? null,
                'computed_at' => $metric->computed_at?->toDateString(),
            ]);

        PlatformAuditLog::record('revenue.view', $tenant, [
            'tenant_name' => $tenant->name,
            'periods' => $metrics->pluck('period')->all(),
        ]);

        return Inertia::render('Platform/Revenue/Show', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
            ],
            'metrics' => $metrics,
            'retention_months' => (int) config('subscription.metrics_retention_months'),
        ]);
    }
}
