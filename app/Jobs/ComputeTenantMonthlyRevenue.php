<?php

namespace App\Jobs;

use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\TenantMonthlyMetric;
use App\Models\Transaction;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Carbon;

/**
 * Satu-satunya pintu dari data penjualan tenant ke pemilik SaaS.
 *
 * Job ini sengaja berada DI LUAR namespace `Platform`. Aturannya: yang dilarang
 * menyentuh data operasional adalah controller/halaman platform, bukan job
 * terjadwal. Dengan begitu tetap ada satu pintu ke data mentah, dan pintu itu
 * mudah diaudit — sementara arch test tetap bisa menjaga sisi panelnya.
 *
 * Hasilnya hanya ditulis ke tabel ringkasan. Halaman platform tidak pernah
 * membaca `transactions`.
 */
class ComputeTenantMonthlyRevenue implements ShouldQueue
{
    use Queueable;

    public function __construct(private readonly ?string $period = null) {}

    public function handle(): void
    {
        $period = $this->period !== null
            ? Carbon::createFromFormat('Y-m', $this->period)->startOfMonth()
            // Bulan yang sudah TUTUP. Menghitung bulan berjalan menghasilkan
            // angka yang berubah tiap hari dan bracket yang ikut goyang.
            : now()->subMonth()->startOfMonth();

        Tenant::query()
            // GERBANG PRIVASI, dua lapis. Tenant jalur normal tidak pernah
            // tersentuh, dan karenanya tidak pernah punya baris di tabel
            // ringkasan sama sekali.
            ->where('pricing_track', TenantConsent::TYPE_SUBSIDIZED)
            // Lapis kedua: persetujuan yang MASIH BERLAKU. Kolom jalur belum
            // berubah sampai akhir periode setelah consent dicabut — kalau
            // hanya kolom itu yang disaring, pengumpulan data akan terus jalan
            // sebulan penuh setelah tenant menarik izinnya.
            ->whereHas('consents', fn ($query) => $query
                ->whereNull('revoked_at')
                ->where('type', TenantConsent::TYPE_SUBSIDIZED))
            ->each(function (Tenant $tenant) use ($period) {
                $this->computeFor($tenant, $period);
            });
    }

    private function computeFor(Tenant $tenant, Carbon $period): void
    {
        // withoutGlobalScopes() ditulis EKSPLISIT. Di konteks job, TenantScope
        // memang tidak aktif karena tidak ada yang login — tapi jangan
        // bergantung pada kebetulan itu. Nyatakan niatnya, lalu filter
        // tenant_id sendiri.
        $query = Transaction::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween(
                $period->copy()->startOfMonth(),
                $period->copy()->endOfMonth(),
            );

        TenantMonthlyMetric::updateOrCreate(
            ['tenant_id' => $tenant->id, 'period' => $period->format('Y-m')],
            [
                // Hanya `completed`. Kalau `voided` ikut terhitung, tenant bisa
                // menaikkan omsetnya — dan karenanya harganya — tanpa penjualan
                // nyata, atau sebaliknya dirugikan transaksi yang batal.
                'revenue' => (clone $query)->sum('total_amount'),
                'transaction_count' => (clone $query)->count(),
                'computed_at' => now(),
            ],
        );
    }
}
