<?php

namespace App\Services\Upsell\Strategies;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellEvent;
use App\Models\UpsellRule;
use App\Services\Upsell\CartLevelStrategy;
use App\Services\Upsell\SellableVariantQuery;
use App\Services\Upsell\Suggestion;
use App\Services\Upsell\SuggestionStrategy;
use Illuminate\Database\Eloquent\Collection;

/**
 * Saran yang DIPERINTAHKAN owner, bukan ditemukan mesin ([BL-074]).
 *
 * Strategi keempat, bukan mesin kedua: keluarannya `Suggestion` yang sama
 * persis, sehingga bentuk props POS, `UpsellStrip.vue`, dan pencatatan
 * event-nya tidak berubah sama sekali.
 *
 * Satu-satunya strategi yang mengisi KEDUA sisi indeks. Aturan berpemicu masuk
 * `by_variant`; aturan tanpa pemicu masuk `cart_level` — dan pembelahan itulah
 * jawaban atas dua kalimat pemilik yang terdengar sama tapi berbeda bentuk:
 * "kalau beli nasi goreng, tawarkan teh manis" (berpemicu) dan "bulan ini
 * dorong kopi susu botol" (tanpa pemicu).
 *
 * DUA ATURAN YANG MENENTUKAN BENAR-TIDAKNYA KELAS INI:
 *
 *   1. PENJAGA KANDIDAT TETAP BERLAKU, TANPA PENGECUALIAN. Varian kedaluwarsa,
 *      stok nol, dan produk nonaktif tetap gugur walaupun owner sendiri yang
 *      menuliskannya. `[BL-017]` menulis test khusus untuk ini; jalur manual
 *      yang menerobos akan menghidupkan kembali persis bug yang test itu jaga.
 *      Owner yang menyuruh menawarkan barang habis tidak sedang meminta barang
 *      habis ditawarkan — ia hanya belum tahu stoknya nol.
 *
 *   2. ATURAN MANUAL MENANG SAAT BEREBUT SLOT. Skornya diberi LANTAI jauh di
 *      atas skor tertinggi yang bisa dicapai mesin, bukan diadu dengan angka
 *      yang tidak pernah owner lihat. Kalau saran yang ia pasang sendiri bisa
 *      tergeser diam-diam, ia akan menyimpulkan fiturnya rusak — dan ia tidak
 *      akan salah, karena dari tempat ia berdiri memang begitu yang terjadi.
 */
class ManualRuleStrategy implements CartLevelStrategy, SuggestionStrategy
{
    /**
     * Lantai skor aturan manual.
     *
     * Skor mesin tertinggi hari ini 100 (`PressedStockStrategy` untuk barang
     * yang kedaluwarsa hari ini). 1000 memberi jarak yang tidak perlu dijaga
     * ulang tiap kali ada strategi baru — dan bila suatu saat sebuah strategi
     * mesin memberi skor di atas ini, itu bug pada strategi itu, bukan di sini.
     */
    private const SCORE_FLOOR = 1000.0;

    /**
     * Aturan berpemicu, dipetakan per varian pemicunya.
     *
     * @param  Collection<int, ProductVariant>  $sellableVariants
     * @return array<int, list<Suggestion>>
     */
    public function suggestFor(Tenant $tenant, Collection $sellableVariants): array
    {
        $sellableIds = $sellableVariants->keyBy('id');
        $mapped = [];

        foreach ($this->rules($tenant)->whereNotNull('trigger_variant_id') as $rule) {
            $suggested = $sellableIds->get($rule->suggested_variant_id);

            if ($suggested === null) {
                continue;
            }

            $mapped[$rule->trigger_variant_id][] = $this->toSuggestion($rule, $suggested);
        }

        return $mapped;
    }

    /**
     * Aturan tanpa pemicu — berlaku pada setiap keranjang yang tidak kosong.
     *
     * @return list<Suggestion>
     */
    public function suggest(Tenant $tenant): array
    {
        $sellableIds = SellableVariantQuery::for($tenant)->with('product:id,name')->get()->keyBy('id');

        $suggestions = [];

        foreach ($this->rules($tenant)->whereNull('trigger_variant_id') as $rule) {
            $suggested = $sellableIds->get($rule->suggested_variant_id);

            if ($suggested === null) {
                continue;
            }

            $suggestions[] = $this->toSuggestion($rule, $suggested);
        }

        return $suggestions;
    }

    /**
     * Aturan yang berlaku hari ini, terurut prioritas.
     *
     * @return \Illuminate\Support\Collection<int, UpsellRule>
     */
    private function rules(Tenant $tenant): \Illuminate\Support\Collection
    {
        // Scope tenant EKSPLISIT: pembangunan indeks juga dipanggil dari jalur
        // yang tidak punya pengguna terautentikasi (self-order, dan kelak
        // pekerjaan antrean), di mana TenantScope tidak menolong sama sekali.
        // Pelajaran yang sama sudah dibayar di `[BL-047]` dan di SAAS Tahap A.
        return UpsellRule::withoutGlobalScopes()
            ->where('tenant_id', $tenant->id)
            ->activeOn(now())
            ->orderByDesc('priority')
            ->orderBy('id')
            ->get();
    }

    private function toSuggestion(UpsellRule $rule, ProductVariant $suggested): Suggestion
    {
        $name = $this->displayName($suggested);

        return new Suggestion(
            type: UpsellEvent::TYPE_MANUAL,
            reason: UpsellEvent::REASON_OWNER_RULE,
            label: $name,
            // Catatan owner dipakai apa adanya bila ada. Mengarang kalimat
            // pengganti ("Direkomendasikan") membuang satu-satunya hal yang
            // strategi ini punya dan mesin tidak: alasan yang ditulis orang
            // yang tahu barangnya.
            note: $rule->note ?: 'Pilihan pemilik',
            extraAmount: (float) $suggested->price,
            score: self::SCORE_FLOOR + $rule->priority,
            triggerVariantId: $rule->trigger_variant_id,
            suggestedVariantId: $suggested->id,
            suggestedVariantName: $name,
            suggestedVariantPrice: (float) $suggested->price,
        );
    }

    private function displayName(ProductVariant $variant): string
    {
        $productName = $variant->relationLoaded('product') && $variant->product !== null
            ? $variant->product->name
            : null;

        return $productName === null ? $variant->name : $productName.' - '.$variant->name;
    }
}
