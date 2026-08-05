<?php

namespace App\Services;

use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\Subscription;
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
    /** Tarifnya keluar dari aturan yang cocok. */
    public const SOURCE_RULE = 'rule';

    /** Tak ada aturan yang cocok; yang berlaku tarif paket penampung. */
    public const SOURCE_PLAN = 'plan';

    /** Tak ada aturan DAN tak ada paket penampung — tarifnya belum ada. */
    public const SOURCE_NONE = 'none';

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
     * Sunting aturan yang belum berlaku berikut syarat-syaratnya.
     *
     * Syaratnya ditulis ulang seluruhnya, bukan dicocokkan satu per satu, dan
     * itu pilihan yang disengaja: syarat tidak punya identitas sendiri di mata
     * penggunanya — yang ia lihat adalah sekumpulan baris yang boleh ditambah,
     * dihapus, dan diubah urutannya. Mencoba mengenali "syarat yang sama" di
     * antara dua kiriman form hanya melahirkan tebakan yang kadang benar.
     *
     * Satu transaksi karena sesaat di tengahnya aturan ini tidak punya syarat
     * sama sekali — dan aturan tanpa syarat berarti "cocok untuk semua tenant".
     * Gagal di titik itu meninggalkan aturan yang berlaku paling luas, kegagalan
     * yang justru paling mahal. Alasan yang sama seperti di `publishRule()`.
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array{dimension: string, operator: string, value: string}>  $conditions
     */
    public function reviseRule(PricingRule $rule, array $attributes, array $conditions): PricingRule
    {
        return DB::transaction(function () use ($rule, $attributes, $conditions) {
            $rule->update($attributes);
            $rule->conditions()->delete();

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
     * `source` menyebutkan DARI MANA angkanya, dan itu bukan hiasan: sejak ada
     * paket penampung, harga yang keluar dari aturan dan harga yang keluar
     * karena tak ada aturan sama-sama berupa angka. Tanpa penanda ini keduanya
     * mustahil dibedakan pemanggil, dan "tenant ini kena tarif paket karena
     * bracketnya dihapus" akan terlihat sama persis dengan "tenant ini memang
     * masuk bracket seharga sekian".
     *
     * @return array{rule: PricingRule|null, source: string, label: string|null, price: float|null, context: array<string, float|string|null>}
     */
    public function resolveFor(Tenant $tenant, ?Carbon $asOf = null): array
    {
        $context = $this->dimensions->contextFor($tenant, $asOf);
        $rule = $this->matchContext($context, $asOf);

        if ($rule !== null) {
            return [
                'rule' => $rule,
                'source' => self::SOURCE_RULE,
                'label' => $rule->label,
                'price' => (float) $rule->price,
                'context' => $context,
            ];
        }

        $plan = $this->fallbackPlanFor($tenant);

        return [
            'rule' => null,
            'source' => $plan === null ? self::SOURCE_NONE : self::SOURCE_PLAN,
            'label' => $plan?->name,
            'price' => $plan === null ? null : (float) $plan->base_price,
            'context' => $context,
        ];
    }

    /**
     * Paket yang menampung tenant ketika tak ada aturan tarif yang cocok.
     *
     * Sebelum ini keadaan itu berakhir sebagai harga `null` — tenant tanpa
     * tarif sama sekali. Untuk jalur Harga Tetap null itu tidak pernah jadi
     * soal karena tarifnya memang bukan dari aturan; untuk jalur Harga Adaptif
     * ia berarti satu aturan yang dihapus, atau satu tenant yang tumbuh
     * melampaui bracket teratas, menghilang dari penagihan tanpa satu pun tanda.
     *
     * Karena itu keduanya dijawab, dan jawabannya berbeda:
     *
     *   - jalur Harga Tetap → paketnya sendiri. Itu memang tarif yang berlaku
     *     baginya sejak awal; aturan adaptif tidak pernah ditujukan kepadanya.
     *   - jalur Harga Adaptif → paket yang DITUNJUK pemilik SaaS sebagai
     *     penampung (mis. Premium). Bukan paketnya sendiri: tenant adaptif
     *     lazimnya masih memegang paket dasar seharga Rp 0, dan menjatuhkannya
     *     ke situ berarti bracket yang terhapus diam-diam menggratiskan layanan.
     *
     * Bila belum ada paket penampung yang ditunjuk, tenant adaptif tetap
     * berakhir tanpa tarif — dan itu disengaja. Menjatuhkannya ke paketnya
     * sendiri terdengar lebih ramah, tapi paket tenant adaptif lazimnya paket
     * dasar seharga Rp 0: satu aturan yang dihentikan akan diam-diam
     * menggratiskan layanan bagi seluruh kelompoknya, tanpa seorang pun
     * memutuskannya dan tanpa satu pun tanda di layar. Tarif kosong yang
     * kelihatan lebih baik daripada tarif nol yang tidak.
     */
    protected function fallbackPlanFor(Tenant $tenant): ?Plan
    {
        $subscription = Subscription::where('tenant_id', $tenant->id)->with('plan')->first();

        if ($subscription === null) {
            return null;
        }

        return $subscription->isSubsidized()
            ? Plan::adaptiveFallback()
            : $subscription->plan;
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
     * `source` diteruskan apa adanya supaya halaman langganan bisa mengatakan
     * yang sebenarnya kepada tenant: tarif yang berlaku karena bracketnya cocok
     * dan tarif yang berlaku karena tak ada bracket yang cocok adalah dua hal
     * berbeda, dan yang kedua pantas disebutkan namanya.
     *
     * @return array{period: string, source: string, label: string|null, price: float|null, revenue: float}|null
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
            'source' => $resolved['source'],
            'label' => $resolved['label'],
            'price' => $resolved['price'],
            'revenue' => (float) $metric->revenue,
        ];
    }
}
