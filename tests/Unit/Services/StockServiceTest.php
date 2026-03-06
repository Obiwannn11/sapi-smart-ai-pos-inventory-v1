<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\StockService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'stock' => 50,
    ]);
    $this->service = app(StockService::class);
});

test('restock increases stock', function () {
    $this->service->restock($this->variant, 20, 'Restock dari supplier');

    expect($this->variant->fresh()->stock)->toBe(70);
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'restock',
        'qty' => 20,
    ]);
});

test('restock can update expiry date', function () {
    $this->service->restock($this->variant, 10, 'Restock', '2026-06-01');

    expect($this->variant->fresh()->expiry_date->format('Y-m-d'))->toBe('2026-06-01');
});

test('positive adjustment increases stock', function () {
    $this->service->adjust($this->variant, 5, 'Temuan audit');

    expect($this->variant->fresh()->stock)->toBe(55);
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'adjustment',
        'qty' => 5,
    ]);
});

test('negative adjustment decreases stock', function () {
    $this->service->adjust($this->variant, -3, 'Bahan rusak');

    expect($this->variant->fresh()->stock)->toBe(47);
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'adjustment',
        'qty' => -3,
    ]);
});

test('adjustment cannot make stock negative', function () {
    expect(fn () => $this->service->adjust($this->variant, -999, 'Too much'))
        ->toThrow(\Exception::class, 'stok');
});

test('adjustment does not change stock on failure', function () {
    try {
        $this->service->adjust($this->variant, -999, 'Fail');
    } catch (\Exception) {
        // expected
    }

    expect($this->variant->fresh()->stock)->toBe(50);
});

test('deduct decreases stock for sale', function () {
    // Deduct requires a transaction_id, let's use a dummy
    $transaction = \App\Models\Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $this->service->deduct($this->variant, 10, $transaction->id);

    expect($this->variant->fresh()->stock)->toBe(40);
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'sale',
        'qty' => -10,
        'reference_id' => $transaction->id,
    ]);
});

test('deduct fails when stock is insufficient', function () {
    $transaction = \App\Models\Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    expect(fn () => $this->service->deduct($this->variant, 999, $transaction->id))
        ->toThrow(\Exception::class);
});

test('restore increases stock for void', function () {
    $transaction = \App\Models\Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
    ]);

    $this->service->restore($this->variant, 15, $transaction->id);

    expect($this->variant->fresh()->stock)->toBe(65);
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'void',
        'qty' => 15,
        'reference_id' => $transaction->id,
    ]);
});
