<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'stock' => 50,
    ]);
});

test('owner can view stock management', function () {
    $this->actingAs($this->owner)
        ->get('/owner/stock')
        ->assertStatus(200);
});

test('owner can restock variant', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/restock", [
            'qty' => 20,
            'notes' => 'Restock dari supplier',
        ])
        ->assertSessionHas('success');

    expect($this->variant->fresh()->stock)->toBe(70);
});

test('owner can adjust stock positively', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/adjust", [
            'qty' => 5,
            'notes' => 'Temuan audit',
        ])
        ->assertSessionHas('success');

    expect($this->variant->fresh()->stock)->toBe(55);
});

test('owner can adjust stock negatively', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/adjust", [
            'qty' => -3,
            'notes' => 'Barang rusak',
        ])
        ->assertSessionHas('success');

    expect($this->variant->fresh()->stock)->toBe(47);
});

test('stock adjustment cannot make stock negative', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/adjust", [
            'qty' => -999,
            'notes' => 'Too many',
        ])
        ->assertSessionHas('error');

    expect($this->variant->fresh()->stock)->toBe(50);
});
