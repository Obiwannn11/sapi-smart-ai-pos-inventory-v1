<?php

namespace App\Services\Upsell\Strategies;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\UpsellEvent;
use App\Services\DiscountService;
use App\Services\Upsell\CartLevelStrategy;
use App\Services\Upsell\SellableVariantQuery;
use App\Services\Upsell\Suggestion;
use Illuminate\Database\Eloquent\Builder;

/**
 * Barang tertekan — varian mendekati kedaluwarsa atau tak terjual sebulan.
 *
 * Satu-satunya jenis saran yang TIDAK butuh pemicu: ia relevan begitu keranjang
 * tidak kosong, apa pun isinya. Karena itu tempatnya di `cart_level`, bukan
 * dipetakan per varian.
 *
 * Ambangnya sengaja sama dengan BadgeHelperService supaya owner tidak melihat
 * dua definisi "mendekati kedaluwarsa" yang berbeda di dua layar.
 *
 * Yang ditawarkan adalah harga EFEKTIF — sudah termasuk potongan dinamis bila
 * varian ini sedang punya aturannya ([BL-018] menyediakan tempat sahnya).
 * Sebelum [BL-103] butir 1, strip ini mengutip harga katalog sementara grid
 * katalog sudah memakai harga berdiskon: barang yang sama masuk keranjang
 * dengan dua harga berbeda tergantung tombol mana yang ditekan kasir.
 *
 * Bundling berdiskon — potongan yang lahir dari KOMBINASI barang — masih belum
 * ada wujudnya; tempatnya `[BL-103]` butir 2, bukan di sini.
 */
class PressedStockStrategy implements CartLevelStrategy
{
    public function __construct(private DiscountService $discounts) {}

    public function suggest(Tenant $tenant): array
    {
        $nearExpiryDays = (int) config('upsell.pressed_stock.near_expiry_days', 7);
        $deadStockDays = (int) config('upsell.pressed_stock.dead_stock_days', 30);
        $limit = (int) config('upsell.pressed_stock_candidates', 4);

        $deadStockSince = now()->subDays($deadStockDays);

        $candidates = SellableVariantQuery::for($tenant)
            ->where(function (Builder $query) use ($nearExpiryDays, $deadStockSince) {
                $query
                    ->where(function (Builder $inner) use ($nearExpiryDays) {
                        $inner->whereNotNull('expiry_date')
                            ->where('expiry_date', '<=', now()->addDays($nearExpiryDays));
                    })
                    ->orWhereDoesntHave('transactionItems.transaction', function ($inner) use ($deadStockSince) {
                        $inner->where('transactions.created_at', '>=', $deadStockSince)
                            ->where('transactions.status', Transaction::STATUS_COMPLETED);
                    });
            })
            ->with('product:id,name')
            ->get();

        // Satu kueri untuk seluruh kandidat, bukan satu per varian — jalur ini
        // ikut dibangun ulang tiap kali props POS dirakit.
        $rules = $this->discounts->rulesFor($tenant, $candidates->pluck('id')->all());

        $suggestions = [];

        foreach ($candidates as $variant) {
            [$reason, $note, $score] = $this->classify($variant, $nearExpiryDays, $deadStockDays);

            // Baris baru di keranjang, jadi "tambahan"-nya adalah harga barang
            // itu sendiri — harga yang benar-benar akan ditagih, bukan katalog.
            $price = $this->discounts->effectivePrice($variant, $tenant, $rules);

            $suggestions[] = new Suggestion(
                type: UpsellEvent::TYPE_PRESSED_STOCK,
                reason: $reason,
                label: $this->displayName($variant),
                note: $note,
                extraAmount: $price,
                score: $score,
                suggestedVariantId: $variant->id,
                suggestedVariantName: $this->displayName($variant),
                suggestedVariantPrice: $price,
            );
        }

        usort($suggestions, fn (Suggestion $a, Suggestion $b) => $b->score <=> $a->score);

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * Varian bisa sekaligus mendekati kedaluwarsa DAN tak terjual sebulan.
     * Kedaluwarsa menang: barang tak laku merugikan pelan-pelan, barang yang
     * kedaluwarsa hari Kamis merugikan hari Kamis.
     *
     * Punya `expiry_date` saja belum cukup untuk disebut mendekati kedaluwarsa —
     * varian bisa masuk daftar lewat jalur dead stock sambil tanggal
     * kedaluwarsanya masih berbulan-bulan lagi.
     *
     * @return array{0: string, 1: string, 2: float}
     */
    private function classify(ProductVariant $variant, int $nearExpiryDays, int $deadStockDays): array
    {
        $nearExpiry = $variant->expiry_date !== null
            && $variant->expiry_date->startOfDay()->lte(now()->addDays($nearExpiryDays)->startOfDay());

        if ($nearExpiry) {
            $daysLeft = max(0, now()->startOfDay()->diffInDays($variant->expiry_date->startOfDay(), false));

            return [
                UpsellEvent::REASON_NEAR_EXPIRY,
                $daysLeft === 0 ? 'Kedaluwarsa hari ini' : "Kedaluwarsa {$daysLeft} hari lagi",
                100.0 - ($daysLeft * 5),
            ];
        }

        return [
            UpsellEvent::REASON_DEAD_STOCK,
            "Belum terjual {$deadStockDays} hari",
            40.0,
        ];
    }

    private function displayName(ProductVariant $variant): string
    {
        $productName = $variant->relationLoaded('product') && $variant->product !== null
            ? $variant->product->name
            : null;

        return $productName === null ? $variant->name : $productName.' - '.$variant->name;
    }
}
