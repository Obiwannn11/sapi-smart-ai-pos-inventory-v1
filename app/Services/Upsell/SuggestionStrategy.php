<?php

namespace App\Services\Upsell;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Collection;

/**
 * Strategi yang butuh PEMICU — sarannya baru masuk akal setelah tahu apa yang
 * sudah ada di keranjang (add-on, naik ukuran).
 */
interface SuggestionStrategy
{
    /**
     * @param  Collection<int, \App\Models\ProductVariant>  $sellableVariants  kandidat yang sudah lolos SellableVariantQuery
     * @return array<int, list<Suggestion>> dipetakan per id varian pemicu
     */
    public function suggestFor(Tenant $tenant, Collection $sellableVariants): array;
}
