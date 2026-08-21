<?php

namespace App\Services\Upsell;

use App\Models\Tenant;
use App\Services\Upsell\Strategies\AttachModifierStrategy;
use App\Services\Upsell\Strategies\ManualRuleStrategy;
use App\Services\Upsell\Strategies\PressedStockStrategy;
use App\Services\Upsell\Strategies\UpsizeVariantStrategy;

/**
 * Merakit indeks saran untuk satu tenant.
 *
 * Pembagian kerjanya disengaja: KANDIDAT dihitung di server (query ko-okurensi,
 * filter stok, filter kedaluwarsa — mahal dan butuh DB), sedangkan PENYARINGAN
 * terhadap isi keranjang dilakukan client (murah, dan berubah tiap klik).
 *
 * Konsekuensi terpentingnya: indeks ikut props POS, sehingga ikut ter-snapshot
 * `useCatalogCache` dan hidup saat perangkat offline tanpa kode tambahan. Sebuah
 * endpoint per-perubahan-keranjang akan mati justru di warung yang sinyalnya
 * paling buruk.
 */
class UpsellIndexBuilder
{
    public function __construct(
        private AttachModifierStrategy $attachModifier,
        private UpsizeVariantStrategy $upsizeVariant,
        private PressedStockStrategy $pressedStock,
        private ManualRuleStrategy $manualRule,
    ) {}

    /**
     * Indeks penuh untuk dikirim ke POS.
     *
     * @return array{by_variant: array<int, list<array<string, mixed>>>, cart_level: list<array<string, mixed>>, max_per_transaction: int, mandatory: bool, generated_at: string}
     */
    public function build(Tenant $tenant): array
    {
        $maxPerTransaction = (int) config('upsell.max_per_transaction', 2);

        // Ikut indeks, bukan prop tersendiri: dengan begitu saklarnya
        // ter-snapshot useCatalogCache bersama sarannya dan tetap berlaku saat
        // perangkat offline. Saran yang wajib diselesaikan online tapi bebas
        // dilewati offline adalah aturan yang tidak berarti apa-apa.
        $mandatory = (bool) $tenant->upsell_mandatory;

        if (! config('upsell.enabled', true)) {
            return $this->emptyIndex($maxPerTransaction);
        }

        $sellable = SellableVariantQuery::for($tenant)
            ->with('product:id,name')
            ->get();

        $byVariant = [];

        foreach ($this->triggeredSuggestions($tenant, $sellable) as $variantId => $suggestions) {
            usort($suggestions, fn (Suggestion $a, Suggestion $b) => $b->score <=> $a->score);

            $byVariant[$variantId] = array_map(
                fn (Suggestion $suggestion) => $suggestion->toArray(),
                array_slice($suggestions, 0, $maxPerTransaction),
            );
        }

        return [
            'by_variant' => $byVariant,
            'cart_level' => array_map(
                fn (Suggestion $suggestion) => $suggestion->toArray(),
                $this->cartLevelSuggestions($tenant),
            ),
            'max_per_transaction' => $maxPerTransaction,
            'mandatory' => $mandatory,
            'generated_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Daftar saran datar untuk sebuah keranjang yang sudah diketahui isinya —
     * dipakai jalur self-order, di mana tidak ada snapshot katalog yang perlu
     * dihidupi dan keranjangnya dikirim langsung.
     *
     * @param  list<int>  $variantIds
     * @return list<array<string, mixed>>
     */
    public function forCart(Tenant $tenant, array $variantIds): array
    {
        return $this->pickForCart($this->build($tenant), $variantIds);
    }

    /**
     * Perebutan slot untuk satu keranjang, di atas indeks yang SUDAH dirakit.
     *
     * Terpisah dari `forCart()` supaya pratinjau owner ([BL-092]) memakai kode
     * pemilihan yang sama persis dengan kasir, bukan tiruannya — pratinjau yang
     * menyimpang dari kenyataan lebih buruk daripada tidak ada pratinjau.
     *
     * @param  array{by_variant: array<int, list<array<string, mixed>>>, cart_level: list<array<string, mixed>>, max_per_transaction: int, mandatory: bool, generated_at: string}  $index
     * @param  list<int>  $variantIds
     * @return list<array<string, mixed>>
     */
    public function pickForCart(array $index, array $variantIds): array
    {
        return array_slice($this->rankForCart($index, $variantIds), 0, $index['max_per_transaction']);
    }

    /**
     * Seluruh kandidat keranjang itu, terurut skor — TERMASUK yang kalah slot.
     *
     * Kasir tidak pernah melihat ekornya; pratinjau owner justru hidup dari
     * ekor itu, karena pertanyaannya persis "apa yang tidak muncul gara-gara
     * batas 3 ini?" ([BL-092]).
     *
     * @param  array{by_variant: array<int, list<array<string, mixed>>>, cart_level: list<array<string, mixed>>, max_per_transaction: int, mandatory: bool, generated_at: string}  $index
     * @param  list<int>  $variantIds
     * @return list<array<string, mixed>>
     */
    public function rankForCart(array $index, array $variantIds): array
    {
        $picked = [];

        foreach ($variantIds as $variantId) {
            foreach ($index['by_variant'][$variantId] ?? [] as $suggestion) {
                $picked[$suggestion['key']] = $suggestion;
            }
        }

        if ($variantIds !== []) {
            foreach ($index['cart_level'] as $suggestion) {
                $picked[$suggestion['key']] = $suggestion;
            }
        }

        // Menyarankan apa yang sudah ada di keranjang membuat saran terlihat
        // asal-asalan — dan pada naik ukuran, menyarankan varian yang justru
        // jadi pemicunya sendiri.
        $picked = array_filter(
            $picked,
            fn (array $suggestion) => ! in_array($suggestion['suggested_variant_id'], $variantIds, true),
        );

        $picked = array_values($picked);

        usort($picked, fn (array $a, array $b) => $b['score'] <=> $a['score']);

        return $picked;
    }

    /**
     * @param  \Illuminate\Database\Eloquent\Collection<int, \App\Models\ProductVariant>  $sellable
     * @return array<int, list<Suggestion>>
     */
    private function triggeredSuggestions(Tenant $tenant, $sellable): array
    {
        $merged = [];

        $strategies = array_filter([
            'attach' => $this->attachModifier,
            'upsize' => $this->upsizeVariant,
            // Aturan manual masuk sebagai strategi keempat, bukan sebagai
            // cabang tersendiri di atas hasil mesin ([BL-074]). Yang membuatnya
            // menang bukan tempatnya di daftar ini, melainkan lantai skornya —
            // lihat ManualRuleStrategy.
            'manual' => $this->manualRule,
        ], fn (string $type) => $this->enabled($type), ARRAY_FILTER_USE_KEY);

        foreach ($strategies as $strategy) {
            foreach ($strategy->suggestFor($tenant, $sellable) as $variantId => $suggestions) {
                $merged[$variantId] = array_merge($merged[$variantId] ?? [], $suggestions);
            }
        }

        return $merged;
    }

    /**
     * Saran yang tidak butuh pemicu, dari kedua sumbernya.
     *
     * Urutannya di sini tidak menentukan apa pun — client menyortir ulang
     * berdasarkan skor, dan itulah tempat aturan manual memenangkan slotnya.
     *
     * @return list<Suggestion>
     */
    private function cartLevelSuggestions(Tenant $tenant): array
    {
        return array_merge(
            $this->enabled('pressed_stock') ? $this->pressedStock->suggest($tenant) : [],
            $this->enabled('manual') ? $this->manualRule->suggest($tenant) : [],
        );
    }

    private function enabled(string $type): bool
    {
        return (bool) config("upsell.types.{$type}", true);
    }

    /**
     * @return array{by_variant: array<int, list<array<string, mixed>>>, cart_level: list<array<string, mixed>>, max_per_transaction: int, mandatory: bool, generated_at: string}
     */
    private function emptyIndex(int $maxPerTransaction): array
    {
        return [
            'by_variant' => [],
            'cart_level' => [],
            'max_per_transaction' => $maxPerTransaction,
            // Tanpa saran, tidak ada yang bisa ditahan.
            'mandatory' => false,
            'generated_at' => now()->toIso8601String(),
        ];
    }
}
