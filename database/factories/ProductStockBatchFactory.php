<?php

namespace Database\Factories;

use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProductStockBatch>
 */
class ProductStockBatchFactory extends Factory
{
    /**
     * Batch lepas, TANPA menyentuh `product_variants.stock`.
     *
     * Dipakai untuk menyusun keadaan yang tidak bisa dicapai lewat layanan stok
     * dalam satu uji. Siapa pun yang memakainya bertanggung jawab menjaga
     * jumlahnya sama dengan stok variannya — `StockBatchService::reconcile()`
     * akan "memperbaikinya" diam-diam kalau tidak.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $qty = fake()->numberBetween(1, 50);

        return [
            // Varian lebih dulu: tenant_id diturunkan darinya.
            'product_variant_id' => ProductVariant::factory()->state(['stock' => 0]),
            'tenant_id' => fn (array $attributes) => ProductVariant::withTrashed()
                ->with('product')
                ->find($attributes['product_variant_id'])
                ?->product?->tenant_id,
            'expiry_date' => null,
            'qty_received' => $qty,
            'qty_remaining' => $qty,
            'received_at' => now(),
            'source' => ProductStockBatch::SOURCE_RESTOCK,
        ];
    }
}
