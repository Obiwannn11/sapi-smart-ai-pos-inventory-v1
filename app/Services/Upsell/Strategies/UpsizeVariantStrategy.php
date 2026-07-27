<?php

namespace App\Services\Upsell\Strategies;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellEvent;
use App\Services\Upsell\Suggestion;
use App\Services\Upsell\SuggestionStrategy;
use Illuminate\Database\Eloquent\Collection;

/**
 * "Naik ukuran" — varian lain dari produk yang SAMA dengan harga lebih tinggi.
 *
 * Dua keputusan yang menentukan apakah fiturnya dipakai atau ditutup terus:
 *
 *   - Yang ditawarkan adalah varian dengan selisih harga PALING KECIL, bukan
 *     yang termahal. Lompatan Small → Jumbo hampir selalu ditolak, dan kasir
 *     yang menolak terus akan berhenti membaca strip-nya sama sekali.
 *   - Tidak ada kolom urutan varian baru. `price` sudah mendefinisikan urutan
 *     yang dimaksud "naik ukuran"; kolom yang harus diisi manual berarti fitur
 *     ini mati diam-diam pada tenant yang tidak pernah mengisinya.
 */
class UpsizeVariantStrategy implements SuggestionStrategy
{
    public function suggestFor(Tenant $tenant, Collection $sellableVariants): array
    {
        $maxGapRatio = (float) config('upsell.upsize.max_price_gap_ratio', 0.6);

        $byProduct = $sellableVariants->groupBy('product_id');
        $suggestions = [];

        foreach ($sellableVariants as $variant) {
            $price = (float) $variant->price;

            if ($price <= 0) {
                continue;
            }

            $ceiling = $price * (1 + $maxGapRatio);

            /** @var ProductVariant|null $step */
            $step = $byProduct->get($variant->product_id, collect())
                ->filter(fn (ProductVariant $other) => $other->id !== $variant->id
                    && (float) $other->price > $price
                    && (float) $other->price <= $ceiling)
                ->sortBy(fn (ProductVariant $other) => (float) $other->price)
                ->first();

            if ($step === null) {
                continue;
            }

            $extra = (float) $step->price - $price;
            $gapRatio = $extra / $price;

            $suggestions[$variant->id] = [
                new Suggestion(
                    type: UpsellEvent::TYPE_UPSIZE,
                    reason: UpsellEvent::REASON_PRICE_STEP,
                    label: $this->displayName($step),
                    note: 'Naik ukuran dari '.$variant->name,
                    extraAmount: $extra,
                    // Selisih kecil dinilai lebih tinggi: makin dekat harganya,
                    // makin besar peluang tawarannya diambil.
                    score: 30.0 + (1 - min($gapRatio, 1.0)) * 10,
                    triggerVariantId: $variant->id,
                    suggestedVariantId: $step->id,
                    suggestedVariantName: $this->displayName($step),
                    suggestedVariantPrice: (float) $step->price,
                ),
            ];
        }

        return $suggestions;
    }

    /**
     * Nama yang dilihat kasir mengikuti format keranjang POS: "Produk - Varian".
     */
    private function displayName(ProductVariant $variant): string
    {
        $productName = $variant->relationLoaded('product') && $variant->product !== null
            ? $variant->product->name
            : null;

        return $productName === null ? $variant->name : $productName.' - '.$variant->name;
    }
}
