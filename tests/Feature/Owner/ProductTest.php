<?php

use App\Models\Category;
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
});

test('owner can view products', function () {
    Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->get('/owner/products')
        ->assertStatus(200);
});

test('owner can view create product form', function () {
    $this->actingAs($this->owner)
        ->get('/owner/products/create')
        ->assertStatus(200);
});

test('owner can create product with variants', function () {
    $category = Category::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->post('/owner/products', [
            'name' => 'Nasi Goreng',
            'category_id' => $category->id,
            'is_active' => true,
            'variants' => [
                [
                    'name' => 'Regular',
                    'sku' => 'NG-REG',
                    'price' => 25000,
                    'cost_price' => 15000,
                    'stock' => 50,
                ],
            ],
        ])
        ->assertRedirect(route('owner.products.index'));

    $this->assertDatabaseHas('products', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Nasi Goreng',
    ]);

    $this->assertDatabaseHas('product_variants', [
        'name' => 'Regular',
        'price' => 25000,
    ]);
});

test('owner can view edit product form', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create(['product_id' => $product->id]);

    $this->actingAs($this->owner)
        ->get("/owner/products/{$product->id}/edit")
        ->assertStatus(200);
});

test('owner can delete product (soft delete)', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->actingAs($this->owner)
        ->delete("/owner/products/{$product->id}")
        ->assertRedirect();

    $this->assertSoftDeleted('products', ['id' => $product->id]);
});
