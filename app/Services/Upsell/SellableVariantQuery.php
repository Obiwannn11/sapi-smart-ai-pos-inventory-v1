<?php

namespace App\Services\Upsell;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Penjaga kandidat — satu tempat, bukan tersebar di tiap strategi.
 *
 * Tiga aturan yang tidak boleh dilanggar strategi mana pun:
 *
 *   1. Stok harus ada. Menyarankan barang yang stoknya nol adalah cacat yang
 *      langsung terlihat pelanggan.
 *   2. Barang yang SUDAH kedaluwarsa tidak boleh jadi kandidat saran dalam
 *      bentuk apa pun. Itu bukan barang tertekan yang perlu didorong. Yang
 *      dijaga di sini hanya SARANNYA: menjualnya tetap mungkin, lewat kasir
 *      yang menuliskan alasan, dan gerbang itu ada di `StockService::deduct()`
 *      — bukan di kelas ini ([BL-108]). `expiry_date` varian adalah tanggal
 *      batch bersisa paling awal ([BL-111]), jadi varian yang masih menyimpan
 *      satu batch basi tidak disarankan sampai batch itu dibuang.
 *   3. Scoping tenant EKSPLISIT lewat product.tenant_id. ProductVariant tidak
 *      memakai BelongsToTenant, jadi tidak ada scope global yang menolong di
 *      sini (pola yang sama dipakai BadgeHelperService).
 */
class SellableVariantQuery
{
    /**
     * @return Builder<ProductVariant>
     */
    public static function for(Tenant $tenant): Builder
    {
        return ProductVariant::query()
            ->where('stock', '>', 0)
            ->where(function (Builder $query) {
                $query->whereNull('expiry_date')
                    ->orWhere('expiry_date', '>=', now()->startOfDay());
            })
            ->whereHas('product', function (Builder $query) use ($tenant) {
                /** @var Builder<Product> $query */
                $query->where('tenant_id', $tenant->id)
                    ->where('is_active', true);
            });
    }
}
