<?php

namespace App\Services;

use App\Models\PricingRule;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use App\Services\Pricing\DimensionRegistry;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Penetapan harga langganan: nilai dimensi tenant → aturan yang menang → tarif.
 *
 * Aturannya dibaca dari `pricing_rules` berikut syarat-syaratnya, yang di-CRUD
 * pemilik SaaS dari platform console. Tidak ada satu pun angka tarif — dan
 * sejak `[BL-015]`, tidak ada satu pun KATEGORI harga — yang hidup di kode.
 * Yang tinggal di kode hanyalah daftar dimensi yang bisa dihitung aplikasi,
 * di `config/pricing-dimensions.php`.
 */
class PricingService
{
    public function __construct(private readonly DimensionRegistry $dimensions) {}

    /**
     * Terbitkan aturan berikut syarat-syaratnya sebagai satu kesatuan.
     *
     * Tinggal di sini, bukan di controller, karena `PlatformArchTest` melarang
     * query mentah di namespace `Platform` — dan larangan itu bukan formalitas:
     * ia yang menjaga agar halaman platform tak pernah punya jalan pintas ke
     * data klien di luar model dan resource yang sudah diperiksa.
     *
     * Transaksinya perlu karena aturan tanpa syarat berarti "cocok untuk semua
     * tenant". Bila baris syaratnya gagal tersimpan setelah aturannya jadi,
     * yang tertinggal bukan aturan setengah jadi melainkan aturan yang berlaku
     * paling luas — kegagalan yang justru paling mahal.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{dimension: string, operator: string, value: string}>  $conditions
     */
    public function publishRule(array $attributes, array $conditions): PricingRule
    {
        return DB::transaction(function () use ($attributes, $conditions) {
            $rule = PricingRule::create($attributes);

            foreach ($conditions as $condition) {
                $rule->conditions()->create($condition);
            }

            return $rule->load('conditions');
        });
    }

    /**
     * Aturan yang menang untuk sekumpulan nilai dimensi.
     *
     * Urutan pemenangnya, dan tiap tingkat ada alasannya:
     *
     * 1. Hanya aturan yang SUDAH berlaku pada `$asOf` yang ikut dinilai —
     *    grandfathering, ditegakkan di lapisan query lewat `effectiveOn()`.
     * 2. Per `label` diambil revisi terbaru. Label mengidentifikasi aturannya,
     *    `effective_from` mengidentifikasi versinya; menerbitkan "B" yang baru
     *    menggantikan "B" yang lama, bukan menambah pesaingnya.
     * 3. Di antara yang tersisa, menang yang `priority`-nya tertinggi. Inilah
     *    yang membuat "omzet kecil DAN kuliner" bisa mendahului "omzet kecil"
     *    tanpa harus menuliskan syarat penyangkalnya di aturan yang umum.
     * 4. Seri diputus oleh `effective_from` lalu `id`, keduanya menurun —
     *    supaya hasilnya tidak pernah bergantung urutan baris di database.
     *
     * @param  array<string, float|string|null>  $context
     */
    public function matchContext(array $context, ?Carbon $asOf = null): ?PricingRule
    {
        return PricingRule::query()
            ->with('conditions')
            ->effectiveOn($asOf)
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->get()
            ->unique(fn (PricingRule $rule) => $rule->label)
            ->sort(fn (PricingRule $a, PricingRule $b) => [$b->priority, $b->effective_from->getTimestamp(), $b->id]
                <=> [$a->priority, $a->effective_from->getTimestamp(), $a->id])
            ->first(fn (PricingRule $rule) => $rule->matches($context));
    }

    /**
     * Harga tenant berikut aturan dan konteks yang menghasilkannya.
     *
     * Konteksnya ikut dikembalikan, bukan dibuang setelah dipakai: ia adalah
     * jawaban atas "kenapa harganya segini", dan penerbitan tagihan
     * membekukannya di `invoices.pricing_context`. Menghitung ulang belakangan
     * hanya akan mengembalikan nilai hari ini.
     *
     * @return array{rule: PricingRule|null, label: string|null, price: float|null, context: array<string, float|string|null>}
     */
    public function resolveFor(Tenant $tenant, ?Carbon $asOf = null): array
    {
        $context = $this->dimensions->contextFor($tenant, $asOf);
        $rule = $this->matchContext($context, $asOf);

        return [
            'rule' => $rule,
            'label' => $rule?->label,
            'price' => $rule === null ? null : (float) $rule->price,
            'context' => $context,
        ];
    }

    /**
     * Bracket untuk satu angka omzet, tanpa tenant.
     *
     * Dipertahankan karena tiga permukaan lama memanggilnya, dan karena ia satu
     * -satunya cara menanyakan "tarif untuk omzet sekian" tanpa memegang tenant
     * — berguna untuk menampilkan tabel tarif. Konsekuensinya harus disadari:
     * aturan yang menyebut dimensi SELAIN omzet tidak akan pernah cocok lewat
     * jalan ini, karena nilai dimensi lainnya tidak diketahui. Itu perilaku
     * gagal-menutup yang sama, bukan kekeliruan.
     *
     * @return array{label: string, price: float}|null
     */
    public function bracketFor(float $revenue, ?Carbon $asOf = null): ?array
    {
        $rule = $this->matchContext(['monthly_revenue' => $revenue], $asOf);

        if ($rule === null) {
            return null;
        }

        return [
            'label' => $rule->label,
            'price' => (float) $rule->price,
        ];
    }

    /**
     * Ringkasan omset terakhir milik tenant, bila ada.
     *
     * Membaca HANYA dari tabel ringkasan — tidak pernah dari `transactions`.
     */
    public function latestMetricFor(Tenant $tenant): ?TenantMonthlyMetric
    {
        return TenantMonthlyMetric::where('tenant_id', $tenant->id)
            ->orderByDesc('period')
            ->first();
    }

    /**
     * Bracket berjalan tenant beserta angka omset yang mendasarinya.
     *
     * Angka persisnya sengaja dipisahkan dari labelnya: pemanggil yang hanya
     * butuh menampilkan daftar cukup memakai `label`, dan `revenue` hanya
     * disentuh di jalur yang memang tercatat di audit log.
     *
     * Sejak `[BL-015]` pencocokannya memakai SELURUH dimensi tenant, bukan
     * omzet saja — sehingga aturan bersyarat majemuk ikut berlaku di sini.
     * `revenue` tetap dikembalikan apa adanya karena halaman langganan tenant
     * menampilkannya kepada pemiliknya sendiri.
     *
     * @return array{period: string, label: string|null, price: float|null, revenue: float}|null
     */
    public function currentBracketFor(Tenant $tenant): ?array
    {
        $metric = $this->latestMetricFor($tenant);

        if ($metric === null) {
            return null;
        }

        $resolved = $this->resolveFor($tenant);

        return [
            'period' => $metric->period,
            'label' => $resolved['label'],
            'price' => $resolved['price'],
            'revenue' => (float) $metric->revenue,
        ];
    }
}
