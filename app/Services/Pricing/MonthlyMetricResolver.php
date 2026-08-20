<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use Illuminate\Support\Carbon;

/**
 * Dasar bersama bagi dimensi harga yang dibaca dari `tenant_monthly_metrics`.
 *
 * Dua dimensi memakainya — omzet dan cacah transaksi — dan keduanya dulu
 * menyalin query yang sama. Salinan itu bukan sekadar pengulangan: `[BL-080]`
 * butir (c) memperbaiki cara ringkasan yang HILANG diperlakukan, dan perbaikan
 * yang hanya diterapkan pada satu salinan akan meninggalkan dimensi yang lain
 * tetap membaca bulan yang keliru.
 */
abstract class MonthlyMetricResolver implements DimensionResolver
{
    /**
     * Bulan ringkasan yang menentukan tarif periode yang dibuka pada `$asOf`.
     *
     * Satu bulan sebelumnya, sesuai keputusan `[BL-056]`: tarif periode P
     * diambil dari omzet bulan sebelum P. Ditulis sebagai satu fungsi supaya
     * penetapan harga dan penjaga kesiapan (`MetricReadiness`) tidak pernah
     * bisa berselisih soal bulan mana yang dimaksud.
     */
    public static function requiredPeriodFor(Carbon $asOf): string
    {
        // startOfMonth() dulu — `subMonth()` telanjang meluber pada tanggal 31
        // dan melompati satu bulan penuh ([BL-029]).
        return $asOf->copy()->startOfMonth()->subMonth()->format('Y-m');
    }

    /**
     * Ringkasan yang dipakai menetapkan harga pada `$asOf`.
     *
     * **TEGAS soal bulan yang diminta** (`[BL-080]` butir (c)). Sebelumnya
     * fungsi ini mengambil ringkasan TERBARU yang periodenya tidak melewati
     * `$asOf`, dan itulah yang mengubah ringkasan yang belum ada menjadi angka
     * bulan lain alih-alih ketiadaan yang terlihat: tagihan terbit dengan omzet
     * dua bulan lalu, tanpa satu pun tanda. Sekarang bulan yang tidak ada
     * menjawab `null`, dan pemanggilnya yang memutuskan apa artinya.
     *
     * Perbaikan kedua yang ikut terbawa: menghitung ulang tagihan lama. Dengan
     * aturan "terbaru sampai `$asOf`", tagihan Maret yang dihitung ulang bulan
     * Juli akan memungut ringkasan MARET — yang saat tagihannya terbit belum
     * ada. Bulan yang tegas mengembalikan Februari, yaitu angka yang benar-benar
     * dipakai saat itu.
     *
     * Tanpa `$asOf` perilakunya tidak berubah: diambil yang terbaru. Di sana
     * tidak ada periode yang sedang ditetapkan harganya — pemanggilnya sedang
     * bertanya "berapa angka tenant ini sekarang", bukan "berapa yang berlaku
     * untuk periode tertentu".
     */
    protected function metricFor(Tenant $tenant, ?Carbon $asOf): ?TenantMonthlyMetric
    {
        $query = TenantMonthlyMetric::where('tenant_id', $tenant->id);

        if ($asOf === null) {
            return $query->orderByDesc('period')->first();
        }

        return $query->where('period', self::requiredPeriodFor($asOf))->first();
    }
}
