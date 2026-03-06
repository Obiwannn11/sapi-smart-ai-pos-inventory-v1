<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('owner can view categories', function () {
    Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Makanan',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/categories')
        ->assertStatus(200);
});

test('owner can create category', function () {
    $this->actingAs($this->owner)
        ->post('/owner/categories', [
            'name' => 'Minuman',
        ])
        ->assertSessionHas('success');

    $this->assertDatabaseHas('categories', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Minuman',
    ]);
});

test('owner can update category', function () {
    $category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Old Name',
    ]);

    $this->actingAs($this->owner)
        ->put("/owner/categories/{$category->id}", [
            'name' => 'New Name',
        ])
        ->assertSessionHas('success');

    expect($category->fresh()->name)->toBe('New Name');
});

test('owner can delete category (soft delete)', function () {
    $category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->owner)
        ->delete("/owner/categories/{$category->id}")
        ->assertSessionHas('success');

    $this->assertSoftDeleted('categories', ['id' => $category->id]);
});

test('deleting category nullifies product category_id', function () {
    $category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'category_id' => $category->id,
    ]);

    $this->actingAs($this->owner)
        ->delete("/owner/categories/{$category->id}");

    // DB constraint nullOnDelete sets category_id to NULL
    expect($product->fresh()->category_id)->toBeNull();
});
