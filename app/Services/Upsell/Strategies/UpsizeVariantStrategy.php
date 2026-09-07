<?php

namespace App\Services\Upsell\Strategies;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellEvent;
use App\Services\DiscountService;
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
 *
 * Dua harga dipakai di sini, dan pembagiannya disengaja ([BL-103] butir 1):
 * TANGGA ukuran tetap ditentukan harga KATALOG, karena urutan Small → Medium →
 * Large adalah sifat produknya dan tidak boleh berubah gara-gara potongan hari
 * ini; sedangkan angka yang DIKUTIP ke kasir — harga varian besarnya dan
 * selisih yang dibayar pelanggan — memakai harga EFEKTIF, karena itulah yang
 * akan ditagih.
 */
class UpsizeVariantStrategy implements SuggestionStrategy
{
    public function __construct(private DiscountService $discounts) {}

    public function suggestFor(Tenant $tenant, Collection $sellableVariants): array
    {
        $maxGapRatio = (float) config('upsell.upsize.max_price_gap_ratio', 0.6);

        $byProduct = $sellableVariants->groupBy('product_id');

        // Satu kueri untuk seluruh varian yang bisa dijual, bukan satu per
        // pasangan pemicu-langkah.
        $rules = $this->discounts->rulesFor($tenant, $sellableVariants->pluck('id')->all());

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

            $triggerPrice = $this->discounts->effectivePrice($variant, $tenant, $rules);
            $stepPrice = $this->discounts->effectivePrice($step, $tenant, $rules);

            // Selisih antara dua harga EFEKTIF — inilah yang benar-benar keluar
            // dari kantong pelanggan bila tawarannya diambil.
            $extra = $stepPrice - $triggerPrice;

            // Rasionya ditahan di nol saat varian besarnya sedang berdiskon
            // sampai lebih murah daripada pemicunya. Selisih negatif akan
            // melempar skor ke luar pita 30–40 yang dipakai seluruh strategi
            // mesin, dan tawaran yang lebih murah memang layak di puncak pita
            // itu — bukan di atasnya.
            $gapRatio = $triggerPrice > 0 ? max($extra, 0.0) / $triggerPrice : 0.0;

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
                    suggestedVariantPrice: $stepPrice,
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
