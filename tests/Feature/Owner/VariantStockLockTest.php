<?php

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockService;

use function Pest\Laravel\actingAs;

/**
 * Formulir edit varian tidak lagi menulis stok atau tanggal kedaluwarsa ([BL-111]).
 *
 * Keduanya hidup di batch. Menulisnya dari formulir ini melewati catatan mutasi
 * stok (tidak ada baris `stock_movements` sama sekali) dan mengganti tanggal
 * batch tanpa jejak. Tempatnya halaman Stok. Varian BARU tetap mengisi stok
 * awalnya, yang jadi batch pembuka.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Croissant']);
});

test('editing a variant updates its name and price but never its stock or expiry', function () {
    $variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'name' => 'Plain',
        'price' => 20000,
        'cost_price' => 12000,
        'stock' => 0,
    ]);

    app(StockService::class)->restock($variant, 10, 'Kiriman', now()->addDays(4)->toDateString());
    $movementsBefore = StockMovement::where('product_variant_id', $variant->id)->count();

    actingAs($this->owner)
        ->put(route('owner.products.variants.update', [$this->product->id, $variant->id]), [
            'name' => 'Plain Besar',
            'sku' => 'CRS-PB',
            'price' => 25000,
            'cost_price' => 13000,
            'stock' => 999,
            'expiry_date' => now()->addYear()->toDateString(),
        ])
        ->assertSessionHas('success');

    $fresh = $variant->fresh();

    expect($fresh->name)->toBe('Plain Besar')
        ->and((float) $fresh->price)->toBe(25000.0)
        ->and($fresh->stock)->toBe(10)
        ->and($fresh->expiry_date->toDateString())->toBe(now()->addDays(4)->toDateString())
        ->and(StockMovement::where('product_variant_id', $variant->id)->count())->toBe($movementsBefore);
});

test('a variant added from the product form still opens its first batch', function () {
    $expiry = now()->addDays(6)->toDateString();

    actingAs($this->owner)
        ->post(route('owner.products.variants.store', $this->product->id), [
            'name' => 'Coklat',
            'price' => 22000,
            'cost_price' => 12500,
            'stock' => 12,
            'expiry_date' => $expiry,
        ])
        ->assertSessionHas('success');

    $variant = ProductVariant::where('product_id', $this->product->id)->where('name', 'Coklat')->firstOrFail();
    $batch = ProductStockBatch::where('product_variant_id', $variant->id)->sole();

    expect($variant->stock)->toBe(12)
        ->and($batch->qty_remaining)->toBe(12)
        ->and($batch->expiry_date->toDateString())->toBe($expiry)
        ->and($batch->source)->toBe(ProductStockBatch::SOURCE_OPENING);
});
