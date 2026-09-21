<?php

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Restock menuntut tanggal pada varian yang pernah bertanggal ([BL-111]).
 *
 * Sejak stok disimpan per batch, restock tanpa tanggal melahirkan batch tanpa
 * tanggal: dijual paling akhir dan tidak pernah dianggap kedaluwarsa. Salah satu
 * "Alternatif murah" `[BL-107]` — menandai varian yang PERNAH bertanggal lalu
 * menuntutnya lagi — dipasang di sini, di server.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

function restockExpiryVariant(Tenant $tenant, string $productName, array $attributes = []): ProductVariant
{
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => $productName]);

    return ProductVariant::factory()->create(array_merge([
        'product_id' => $product->id,
        'stock' => 0,
        'expiry_date' => null,
    ], $attributes));
}

test('a dated variant refuses a restock without a date', function () {
    $variant = restockExpiryVariant($this->tenant, 'Croissant', [
        'stock' => 5,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);

    actingAs($this->owner)
        ->post("/owner/stock/{$variant->id}/restock", ['qty' => 10])
        ->assertSessionHasErrors('expiry_date');

    expect($variant->fresh()->stock)->toBe(5);
});

test('a dated variant accepts an undated delivery once it is declared', function () {
    $variant = restockExpiryVariant($this->tenant, 'Croissant', [
        'stock' => 5,
        'expiry_date' => now()->addDays(3)->toDateString(),
    ]);

    // Tanggal yang sempat terisi sebelum kotaknya dicentang tidak ikut tersimpan.
    actingAs($this->owner)
        ->post("/owner/stock/{$variant->id}/restock", [
            'qty' => 10,
            'no_expiry' => true,
            'expiry_date' => now()->addDays(9)->toDateString(),
        ])
        ->assertSessionHas('success');

    $undated = ProductStockBatch::where('product_variant_id', $variant->id)
        ->whereNull('expiry_date')
        ->sum('qty_remaining');

    expect($variant->fresh()->stock)->toBe(15)
        ->and((int) $undated)->toBe(10);
});

test('a variant whose dated batches ran out still asks for a date', function () {
    $variant = restockExpiryVariant($this->tenant, 'Roti Sobek');

    // Batch bertanggal yang sudah habis terjual; varian tidak memegang tanggal.
    ProductStockBatch::factory()->create([
        'product_variant_id' => $variant->id,
        'expiry_date' => now()->subDays(10)->toDateString(),
        'qty_received' => 4,
        'qty_remaining' => 0,
    ]);

    actingAs($this->owner)
        ->post("/owner/stock/{$variant->id}/restock", ['qty' => 6])
        ->assertSessionHasErrors('expiry_date');
});

test('a variant that never had a date restocks without being asked', function () {
    $variant = restockExpiryVariant($this->tenant, 'Gelas Plastik', ['stock' => 20]);

    actingAs($this->owner)
        ->post("/owner/stock/{$variant->id}/restock", ['qty' => 50])
        ->assertSessionHas('success');

    expect($variant->fresh()->stock)->toBe(70);
});

test('the stock list tells the restock form which variants track expiry', function () {
    restockExpiryVariant($this->tenant, 'Susu Segar', [
        'sku' => 'TE-DATED',
        'stock' => 3,
        'expiry_date' => now()->addDays(5)->toDateString(),
    ]);
    restockExpiryVariant($this->tenant, 'Susu Bubuk', ['sku' => 'TE-PLAIN', 'stock' => 3]);

    actingAs($this->owner)
        ->get('/owner/stock?q=Susu')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('stock', function (Assert $reload) {
            $rows = collect($reload->toArray()['props']['variants']['data'])->keyBy('sku');

            expect($rows['TE-DATED']['tracks_expiry'])->toBeTrue()
                ->and($rows['TE-PLAIN']['tracks_expiry'])->toBeFalse();
        }));
});
