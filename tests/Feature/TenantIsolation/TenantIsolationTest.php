<?php

use App\Models\CashDrawer;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;

beforeEach(function () {
    // Tenant A
    $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
    $this->ownerA = User::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'role' => 'owner',
    ]);

    // Tenant B
    $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
    $this->ownerB = User::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'role' => 'owner',
    ]);

    // Data Tenant A
    $this->categoryA = Category::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Cat A',
    ]);
    $this->productA = Product::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'name' => 'Prod A',
    ]);

    // Data Tenant B
    $this->categoryB = Category::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Cat B',
    ]);
    $this->productB = Product::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'name' => 'Prod B',
    ]);
});

// --- Global Scope Tests ---

test('user A only sees categories from tenant A', function () {
    $this->actingAs($this->ownerA);

    $categories = Category::all();

    expect($categories)->toHaveCount(1);
    expect($categories->first()->name)->toBe('Cat A');
});

test('user B only sees categories from tenant B', function () {
    $this->actingAs($this->ownerB);

    $categories = Category::all();

    expect($categories)->toHaveCount(1);
    expect($categories->first()->name)->toBe('Cat B');
});

test('user A only sees products from tenant A', function () {
    $this->actingAs($this->ownerA);

    $products = Product::all();

    expect($products)->toHaveCount(1);
    expect($products->first()->name)->toBe('Prod A');
});

test('user B only sees products from tenant B', function () {
    $this->actingAs($this->ownerB);

    $products = Product::all();

    expect($products)->toHaveCount(1);
    expect($products->first()->name)->toBe('Prod B');
});

// --- Auto-assign tenant_id ---

test('new category automatically gets tenant_id from logged user', function () {
    $this->actingAs($this->ownerA);

    $category = Category::create(['name' => 'New Cat']);

    expect($category->tenant_id)->toBe($this->tenantA->id);
});

test('new product automatically gets tenant_id from logged user', function () {
    $this->actingAs($this->ownerB);

    $product = Product::create([
        'name' => 'New Product',
        'is_active' => true,
    ]);

    expect($product->tenant_id)->toBe($this->tenantB->id);
});

// --- API endpoint isolation ---

test('user A cannot access tenant B products via edit route', function () {
    $this->actingAs($this->ownerA)
        ->get("/owner/products/{$this->productB->id}/edit")
        ->assertStatus(404);
});

test('user A cannot delete tenant B category', function () {
    $this->actingAs($this->ownerA)
        ->delete("/owner/categories/{$this->categoryB->id}")
        ->assertStatus(404);
});

test('user B cannot access tenant A products via edit route', function () {
    $this->actingAs($this->ownerB)
        ->get("/owner/products/{$this->productA->id}/edit")
        ->assertStatus(404);
});

test('user B cannot delete tenant A category', function () {
    $this->actingAs($this->ownerB)
        ->delete("/owner/categories/{$this->categoryA->id}")
        ->assertStatus(404);
});

// --- Payment Method isolation ---

test('user A only sees own payment methods', function () {
    PaymentMethod::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'Cash A']);
    PaymentMethod::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'Cash B']);

    $this->actingAs($this->ownerA);
    $methods = PaymentMethod::all();

    expect($methods)->toHaveCount(1);
    expect($methods->first()->name)->toBe('Cash A');
});

// --- Modifier Group isolation ---

test('user A only sees own modifier groups', function () {
    ModifierGroup::factory()->create(['tenant_id' => $this->tenantA->id, 'name' => 'MG A']);
    ModifierGroup::factory()->create(['tenant_id' => $this->tenantB->id, 'name' => 'MG B']);

    $this->actingAs($this->ownerA);
    $groups = ModifierGroup::all();

    expect($groups)->toHaveCount(1);
    expect($groups->first()->name)->toBe('MG A');
});

// --- Transaction isolation ---

test('user A only sees own transactions', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
    ]);
    Transaction::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->ownerB->id,
    ]);

    $this->actingAs($this->ownerA);
    $transactions = Transaction::all();

    expect($transactions)->toHaveCount(1);
    expect($transactions->first()->tenant_id)->toBe($this->tenantA->id);
});

// --- CashDrawer isolation ---

test('user A only sees own cash drawers', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
    ]);
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->ownerB->id,
    ]);

    $this->actingAs($this->ownerA);
    $drawers = CashDrawer::all();

    expect($drawers)->toHaveCount(1);
    expect($drawers->first()->tenant_id)->toBe($this->tenantA->id);
});

// --- Comprehensive scope test for all models ---

test('all models with BelongsToTenant are properly scoped', function () {
    // Create records for each tenant
    $productA = Product::factory()->create(['tenant_id' => $this->tenantA->id]);
    $productB = Product::factory()->create(['tenant_id' => $this->tenantB->id]);
    $variantA = ProductVariant::factory()->create(['product_id' => $productA->id]);
    $variantB = ProductVariant::factory()->create(['product_id' => $productB->id]);

    ModifierGroup::factory()->create(['tenant_id' => $this->tenantA->id]);
    ModifierGroup::factory()->create(['tenant_id' => $this->tenantB->id]);

    PaymentMethod::factory()->create(['tenant_id' => $this->tenantA->id]);
    PaymentMethod::factory()->create(['tenant_id' => $this->tenantB->id]);

    Transaction::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
    ]);
    Transaction::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->ownerB->id,
    ]);

    StockMovement::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'product_variant_id' => $variantA->id,
    ]);
    StockMovement::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'product_variant_id' => $variantB->id,
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $this->tenantA->id,
        'user_id' => $this->ownerA->id,
    ]);
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenantB->id,
        'user_id' => $this->ownerB->id,
    ]);

    // Login as owner A
    $this->actingAs($this->ownerA);

    // Each model should only return tenant A records
    $modelsToTest = [
        Category::class,
        Product::class,
        ModifierGroup::class,
        PaymentMethod::class,
        Transaction::class,
        StockMovement::class,
        CashDrawer::class,
    ];

    foreach ($modelsToTest as $modelClass) {
        $results = $modelClass::all();
        expect($results->every(fn ($r) => $r->tenant_id === $this->tenantA->id))->toBeTrue(
            "Tenant isolation failed for {$modelClass}"
        );
    }
});
