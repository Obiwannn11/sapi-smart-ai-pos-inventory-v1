<?php

namespace App\Services;

use App\Models\DiscountRule;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * Harga jual setelah potongan, dan LANTAI yang menjaganya ([BL-018]).
 *
 * Satu tempat yang menjawab "berapa harga varian ini hari ini". Checkout,
 * props POS, sinkronisasi offline, dan pengeditan transaksi semuanya bertanya
 * ke sini — kalau tidak, "harga yang sah" akan punya empat definisi yang
 * perlahan menyimpang.
 *
 * EMPAT ATURAN YANG TIDAK BOLEH DILANGGAR, DAN MASING-MASING PUNYA TESNYA:
 *
 *   1. RUMUS BERHENTI DI LANTAI, SELALU. `priceFor()` tidak pernah
 *      mengembalikan angka di bawah `floorFor()`. Manusia boleh menembusnya
 *      (lihat `belowFloor()`), mesin tidak. Kalau penjaga ini dilonggarkan
 *      juga, seluruh gunanya lantai hilang.
 *
 *   2. PEMBULATAN KE ATAS. Rp 18.750 jadi Rp 19.000, bukan Rp 18.500.
 *      Pembulatan ke bawah bisa menembus lantai margin yang baru saja susah
 *      payah dihitung — pembulatan yang salah arah membuat seluruh penjaganya
 *      sia-sia.
 *
 *   3. BARANG KEDALUWARSA TIDAK DIDISKON, TITIK. Diskon hanya untuk yang
 *      MENDEKATI kedaluwarsa. Ini batas keamanan pangan, bukan pilihan
 *      bisnis, jadi ia dijaga di sini — bukan diserahkan pada kedisiplinan
 *      kasir atau pada owner yang menulis aturannya.
 *
 *   4. LANTAINYA MILIK OWNER. `tenants.min_margin_percent`, bukan konstanta.
 *      Pedagang sayur dan kedai kopi tidak hidup dari persentase yang sama.
 *
 * **Keterbatasan yang sudah diketahui dan sengaja diterima:** harga hasil
 * perhitungan ini ikut ter-snapshot `useCatalogCache` bersama katalognya, jadi
 * perangkat yang seharian offline bisa menjual dengan diskon yang berakhir
 * semalam. Di `[BL-074]` konsekuensi serupa hanya membuat ajakan jadi basi; di
 * sini yang basi adalah UANG. Yang menahannya: sinkronisasi offline menandai
 * harga yang tidak cocok dengan harga sah mana pun sebagai `needs_review`,
 * jadi selisihnya sampai ke meja owner alih-alih hilang.
 */
class DiscountService
{
    /**
     * Kelipatan pembulatan, ke ATAS. 500 dipilih karena itu pecahan terkecil
     * yang benar-benar beredar di laci kasir — membulatkan ke rupiah terdekat
     * menghasilkan harga yang tidak bisa dibayar tunai.
     */
    private const ROUNDING_STEP = 500;

    /**
     * Sebab sebuah aturan tidak menurunkan harga hari ini.
     *
     * Empat sebab yang penanganannya berbeda-beda: yang satu menunggu tanggal,
     * yang satu menunggu owner mengisi modal, yang satu menuntut potongannya
     * diperbesar, dan yang satu tidak bisa diapa-apakan tanpa menurunkan margin
     * minimum. Layar yang hanya berkata "tidak berlaku" menyerahkan pemilihan
     * di antara keempatnya kepada tebakan owner.
     */
    public const REASON_NO_RULE = 'no_rule';

    public const REASON_EXPIRED = 'expired';

    public const REASON_UNKNOWN_COST = 'unknown_cost';

    public const REASON_NO_CUT_TODAY = 'no_cut_today';

    public const REASON_FLOOR_ABSORBED = 'floor_absorbed';

    public const REASON_CUT_TOO_SMALL = 'cut_too_small';

    /**
     * Harga terendah yang boleh dicapai RUMUS untuk varian ini.
     *
     * `cost_price × (1 + margin/100)`, dibulatkan KE ATAS. Varian tanpa
     * `cost_price` tidak punya lantai yang bisa dihitung — dan barang yang
     * modalnya tidak diketahui tidak boleh didiskon oleh rumus sama sekali,
     * karena "tetap untung" jadi klaim tanpa dasar.
     */
    public function floorFor(ProductVariant $variant, Tenant $tenant): ?float
    {
        $cost = $variant->cost_price;

        if ($cost === null || (float) $cost <= 0) {
            return null;
        }

        $margin = (float) ($tenant->min_margin_percent ?? 0);

        return $this->roundUp((float) $cost * (1 + $margin / 100));
    }

    /**
     * Aturan yang berlaku untuk varian ini hari ini, atau null.
     *
     * Bila ada beberapa, yang potongannya PALING DALAM menang. Owner yang
     * memasang dua aturan pada barang yang sama sedang menyatakan urgensi;
     * memilih yang paling dangkal akan mengabaikan yang paling ia maksud.
     */
    public function ruleFor(ProductVariant $variant, Tenant $tenant): ?DiscountRule
    {
        return $this->rulesFor($tenant, [$variant->id])->get($variant->id);
    }

    /**
     * Aturan berlaku untuk sekumpulan varian sekaligus — satu kueri.
     *
     * Dipakai jalur yang menghitung banyak harga sekaligus (props POS,
     * checkout multi-item), supaya tidak ada N+1 di jalur terpanas aplikasi.
     *
     * @param  list<int>  $variantIds
     * @return Collection<int, DiscountRule> dipetakan per product_variant_id
     */
    public function rulesFor(Tenant $tenant, array $variantIds): Collection
    {
        if ($variantIds === []) {
            return collect();
        }

        // Scope tenant EKSPLISIT: jalur self-order dan pekerjaan antrean
        // memanggil ini tanpa pengguna terautentikasi, dan di sana TenantScope
        // tidak menolong sama sekali.
        return DiscountRule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->whereIn('product_variant_id', $variantIds)
            ->activeOn(now())
            ->get()
            ->groupBy('product_variant_id')
            ->map(fn (Collection $rules) => $rules->sortByDesc(
                fn (DiscountRule $rule) => (float) ($rule->max_percent ?? $rule->percent)
            )->first());
    }

    /**
     * Harga jual efektif varian ini hari ini.
     *
     * Mengembalikan harga katalog bila tidak ada aturan yang berlaku, atau
     * bila aturannya tidak boleh dipakai (barang sudah kedaluwarsa, modal tak
     * diketahui).
     *
     * `reason` menyebutkan sebabnya saat harga TIDAK turun, dan `clamped`
     * menandai harga yang turun tapi tertahan lantai. Keduanya dipakai layar
     * Aturan Diskon: baris yang diam menyebut sebabnya sendiri, alih-alih
     * menyuruh owner menebak di antara empat kemungkinan.
     *
     * **`$rule` null di sini berarti "belum dicari", BUKAN "tidak ada"**, dan
     * yang menyusul adalah satu kueri untuk varian ini. Pemanggil yang sudah
     * memuat aturan sekumpulan varian lewat `rulesFor()` HARUS memakai
     * `priceFromRules()`: menyerahkan `$rules->get($id)` ke sini membuat setiap
     * varian tanpa diskon dicari ulang satu per satu — persis N+1 yang
     * `rulesFor()` ada untuk mencegahnya.
     *
     * @return array{price: float, discount: float, rule: ?DiscountRule, floor: ?float, reason: ?string, clamped: bool}
     */
    public function priceFor(ProductVariant $variant, Tenant $tenant, ?DiscountRule $rule = null): array
    {
        return $this->priceWithRule($variant, $tenant, $rule ?? $this->ruleFor($variant, $tenant));
    }

    /**
     * Harga varian ini dari peta aturan yang SUDAH dimuat sekaligus — tanpa
     * kueri tambahan, berapa pun isi petanya.
     *
     * Bentuk kembaliannya sama persis dengan `priceFor()`. Itulah gunanya:
     * pemanggil yang juga butuh `discount` dan `rule` — props POS mengisi
     * `discount_amount` dan `discount_reason` darinya — tidak perlu turun ke
     * `priceFor()` dan menghidupkan lagi pencariannya. Varian yang tidak ada di
     * peta memang tidak punya aturan yang berlaku hari ini; itu jawaban akhir,
     * bukan tanda bahwa pencariannya belum dilakukan.
     *
     * @param  Collection<int, DiscountRule>  $rules  hasil `rulesFor()`, per product_variant_id
     * @return array{price: float, discount: float, rule: ?DiscountRule, floor: ?float, reason: ?string, clamped: bool}
     */
    public function priceFromRules(ProductVariant $variant, Tenant $tenant, Collection $rules): array
    {
        return $this->priceWithRule($variant, $tenant, $rules->get($variant->id));
    }

    /**
     * Harga efektif satu varian dari peta aturan yang SUDAH dimuat sekaligus.
     *
     * Ada karena `priceFor()` menerima `?DiscountRule` dan tidak bisa
     * membedakan "varian ini memang tidak punya aturan" dari "aturannya belum
     * dicari": ia menjawab `null` dengan mencarinya sendiri, satu kueri per
     * varian. Pemanggil yang hanya butuh angkanya memakai ini; yang butuh
     * potongan dan aturannya sekalian memakai `priceFromRules()`.
     *
     * @param  Collection<int, DiscountRule>  $rules  hasil `rulesFor()`, per product_variant_id
     */
    public function effectivePrice(ProductVariant $variant, Tenant $tenant, Collection $rules): float
    {
        return $this->priceFromRules($variant, $tenant, $rules)['price'];
    }

    /**
     * Rumus harganya sendiri, dengan aturan yang sudah PASTI.
     *
     * `null` di sini hanya punya satu arti — varian ini tidak punya aturan yang
     * berlaku — jadi tidak ada lagi yang dicari. Semua pintu masuk di atas
     * bermuara ke sini supaya lantai, pembulatan, dan keempat `reason`-nya
     * tetap punya satu definisi.
     *
     * @return array{price: float, discount: float, rule: ?DiscountRule, floor: ?float, reason: ?string, clamped: bool}
     */
    private function priceWithRule(ProductVariant $variant, Tenant $tenant, ?DiscountRule $rule): array
    {
        $catalog = (float) $variant->price;
        $floor = $this->floorFor($variant, $tenant);

        $none = fn (string $reason): array => [
            'price' => $catalog,
            'discount' => 0.0,
            'rule' => null,
            'floor' => $floor,
            'reason' => $reason,
            'clamped' => false,
        ];

        if ($rule === null) {
            return $none(self::REASON_NO_RULE);
        }

        if (! $this->isDiscountable($variant)) {
            return $none(self::REASON_EXPIRED);
        }

        // Modal tak diketahui → tak ada lantai → rumus tidak boleh menurunkan
        // harga. Lihat floorFor().
        if ($floor === null) {
            return $none(self::REASON_UNKNOWN_COST);
        }

        $percent = $this->effectivePercent($rule, $variant);

        if ($percent <= 0) {
            return $none(self::REASON_NO_CUT_TODAY);
        }

        $raw = $this->roundUp($catalog * (1 - $percent / 100));

        // Aturan (1): rumus berhenti di lantai. Selalu.
        $price = max($raw, $floor);

        if ($price >= $catalog) {
            // Lantai yang sudah setinggi katalog dan potongan yang lebih kecil
            // daripada satu langkah pembulatan sama-sama berakhir "tidak turun",
            // tapi yang pertama menuntut margin minimum diturunkan sedangkan
            // yang kedua cukup dinaikkan persentasenya.
            return $none($floor >= $catalog
                ? self::REASON_FLOOR_ABSORBED
                : self::REASON_CUT_TOO_SMALL);
        }

        return [
            'price' => $price,
            'discount' => round($catalog - $price, 2),
            'rule' => $rule,
            'floor' => $floor,
            'reason' => null,
            'clamped' => $raw < $floor,
        ];
    }

    /**
     * Apakah harga ini di bawah lantai varian tersebut?
     *
     * Dipakai checkout untuk memutuskan apakah sebuah penjualan butuh
     * persetujuan owner dan alasan tertulis.
     */
    public function belowFloor(ProductVariant $variant, Tenant $tenant, float $price): bool
    {
        $floor = $this->floorFor($variant, $tenant);

        return $floor !== null && $price < $floor;
    }

    /**
     * Boleh didiskon sama sekali?
     *
     * Aturan (3): barang yang SUDAH kedaluwarsa gugur. Yang mendekati
     * kedaluwarsa justru inti fiturnya.
     */
    public function isDiscountable(ProductVariant $variant): bool
    {
        if ($variant->expiry_date === null) {
            return true;
        }

        return $variant->expiry_date->startOfDay()->gte(now()->startOfDay());
    }

    /**
     * Persentase potongan yang benar-benar berlaku hari ini.
     *
     * Untuk `near_expiry` ia MENDALAM seiring tanggal kedaluwarsa mendekat —
     * itulah arti "menyesuaikan" pada permintaan aslinya. Untuk pemicu lain ia
     * rata: lama tak terjual bukan hitungan mundur.
     */
    public function effectivePercent(DiscountRule $rule, ProductVariant $variant): float
    {
        $base = (float) $rule->percent;
        $max = $rule->max_percent === null ? null : (float) $rule->max_percent;

        if ($rule->trigger !== DiscountRule::TRIGGER_NEAR_EXPIRY || $max === null || $max <= $base) {
            return $base;
        }

        if ($variant->expiry_date === null) {
            return $base;
        }

        $daysLeft = now()->startOfDay()->diffInDays($variant->expiry_date->startOfDay(), false);

        // Ambangnya sama dengan `upsell.pressed_stock.near_expiry_days` supaya
        // owner tidak melihat dua definisi "mendekati kedaluwarsa" yang berbeda
        // di dua layar.
        $window = (int) config('upsell.pressed_stock.near_expiry_days', 7);

        if ($daysLeft >= $window) {
            return $base;
        }

        if ($daysLeft <= 0) {
            return $max;
        }

        // Interpolasi linier: makin sedikit hari tersisa, makin dalam.
        $progress = ($window - $daysLeft) / $window;

        return $base + ($max - $base) * $progress;
    }

    /**
     * Aturan (2): pembulatan KE ATAS, ke kelipatan yang bisa dibayar tunai.
     */
    public function roundUp(float $amount): float
    {
        return (float) (ceil($amount / self::ROUNDING_STEP) * self::ROUNDING_STEP);
    }
}
