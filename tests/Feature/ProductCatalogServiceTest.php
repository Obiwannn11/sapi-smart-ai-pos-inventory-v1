<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ProductCatalogService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);

    $this->service = app(ProductCatalogService::class);
});

test('activeMenu returns active products with all variants by default', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 10]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 0]);

    $menu = $this->service->activeMenu();

    expect($menu)->toHaveCount(1)
        ->and($menu->first()->variants)->toHaveCount(2);
});

test('activeMenu with inStockOnly excludes out-of-stock variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 10]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 0]);

    $menu = $this->service->activeMenu(inStockOnly: true);

    expect($menu)->toHaveCount(1)
        ->and($menu->first()->variants)->toHaveCount(1)
        ->and($menu->first()->variants->first()->stock)->toBe(10);
});

test('activeMenu excludes inactive products', function () {
    Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => false]);

    expect($this->service->activeMenu())->toHaveCount(0);
});

test('activeMenu is scoped to the authenticated tenant', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'stock' => 5]);

    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id, 'is_active' => true]);
    ProductVariant::factory()->create(['product_id' => $otherProduct->id, 'stock' => 5]);

    $menu = $this->service->activeMenu();

    expect($menu)->toHaveCount(1)
        ->and($menu->first()->id)->toBe($product->id);
});
