<?php

namespace App\Services\Upsell;

use App\Models\Tenant;

/**
 * Strategi yang TIDAK butuh pemicu — sarannya relevan begitu keranjang tidak
 * kosong, apa pun isinya (barang tertekan).
 */
interface CartLevelStrategy
{
    /**
     * @return list<Suggestion>
     */
    public function suggest(Tenant $tenant): array;
}
