<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Services\PricingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Perkiraan tarif Harga Adaptif untuk tenant yang BELUM masuk jalur itu.
 *
 * Kenapa ini tidak melanggar gerbang privasi, dan kenapa batasnya dinyatakan di
 * sini supaya tidak kabur di kemudian hari:
 *
 * `ComputeTenantMonthlyRevenue` hanya menghitung tenant yang sudah menyetujui
 * jalur adaptif, dan hasilnya ditulis ke `tenant_monthly_metrics` — satu-satunya
 * tabel yang dibaca pemilik SaaS. Yang dijaga gerbang itu adalah **pandangan
 * pengelola layanan**, bukan pandangan pemilik toko atas datanya sendiri.
 *
 * Kelas ini menghitung untuk mata pemiliknya sendiri saja:
 *
 * - hasilnya TIDAK PERNAH disimpan, jadi tidak ada baris baru yang bisa dibaca
 *   halaman platform;
 * - angkanya adalah angka yang sudah bisa dijumlahkan sendiri oleh pemiliknya
 *   dari Laporan Harian.
 *
 * Konsekuensinya, kelas ini SENGAJA bukan `DimensionResolver` dan tidak boleh
 * dipakai penetapan harga sungguhan: satu-satunya jalan dari data penjualan ke
 * tagihan tetap `tenant_monthly_metrics`, dan itulah yang membuat batasnya bisa
 * ditegakkan `PlatformArchTest`.
 */
class SubsidyEstimator
{
    public function __construct(private readonly PricingService $pricing) {}

    /**
     * Perkiraan untuk bulan terakhir yang sudah TUTUP.
     *
     * Bulan berjalan sengaja tidak dipakai: angkanya berubah tiap hari, dan
     * perkiraan yang bergoyang tiap kali halaman dibuka bukan dasar keputusan.
     * Periodenya sama persis dengan yang dipakai job penghitung sungguhan.
     *
     * @return array{period: string, revenue: float, transaction_count: int, label: string|null, price: float|null, current_price: float, is_cheaper: bool}
     */
    public function estimateFor(Tenant $tenant, float $currentPrice): array
    {
        // startOfMonth() dulu, baru subMonth() — urutan sebaliknya meluber tiap
        // tanggal 31 dan berakhir menghitung bulan berjalan. Lihat [BL-029].
        $period = now()->startOfMonth()->subMonth();

        $revenue = (float) $this->monthQuery($tenant, $period)->sum('total_amount');
        $bracket = $this->pricing->bracketFor($revenue);

        return [
            'period' => $period->format('Y-m'),
            'revenue' => $revenue,
            'transaction_count' => $this->monthQuery($tenant, $period)->count(),
            'label' => $bracket['label'] ?? null,
            'price' => $bracket['price'] ?? null,
            'current_price' => $currentPrice,
            // Tanpa aturan yang cocok tidak ada yang bisa diklaim lebih murah.
            'is_cheaper' => $bracket !== null && $bracket['price'] < $currentPrice,
        ];
    }

    /**
     * Aturan hitungnya disamakan persis dengan `ComputeTenantMonthlyRevenue`:
     * hanya `completed`, dan memakai tanggal efektif supaya penjualan offline
     * yang tersinkron belakangan jatuh di bulan terjadinya. Perkiraan yang
     * memakai aturan berbeda dari penghitung sungguhan akan meleset justru pada
     * tenant yang paling perlu mempercayainya.
     */
    private function monthQuery(Tenant $tenant, Carbon $period): Builder
    {
        return Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween(
                $period->copy()->startOfMonth(),
                $period->copy()->endOfMonth(),
            );
    }
}
