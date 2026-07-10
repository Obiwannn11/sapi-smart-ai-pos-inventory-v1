<?php

namespace App\Services;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;

/**
 * Sumber tunggal katalog produk aktif milik tenant.
 *
 * Dipakai bersama oleh API consumer/mobile (butuh varian bertok saja) dan
 * MCP Server (menu penuh). Ter-scope tenant otomatis via TenantScope
 * (butuh konteks auth).
 */
class ProductCatalogService
{
    /**
     * Produk aktif beserta varian & kategorinya.
     *
     * @param  bool  $inStockOnly  true → hanya varian dengan stok > 0 (untuk parsing order)
     * @return Collection<int, Product>
     */
    public function activeMenu(bool $inStockOnly = false): Collection
    {
        return Product::where('is_active', true)
            ->with([
                'variants' => function ($query) use ($inStockOnly) {
                    $query->select('id', 'product_id', 'name', 'price', 'stock');

                    if ($inStockOnly) {
                        $query->where('stock', '>', 0);
                    }
                },
                'category:id,name',
            ])
            ->get(['id', 'name', 'category_id']);
    }
}
