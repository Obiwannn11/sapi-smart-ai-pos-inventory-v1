<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\BadgeHelperService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->actingAs($this->user);
    $this->service = app(BadgeHelperService::class);
});

test('detects low stock variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 3,
    ]);

    $badges = $this->service->generate($this->tenant);
    $lowStock = collect($badges)->firstWhere('type', 'low_stock');

    expect($lowStock)->not->toBeNull();
    expect($lowStock['count'])->toBe(1);
});

test('does not flag low stock for stock above 5', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 10,
    ]);

    $badges = $this->service->generate($this->tenant);
    $lowStock = collect($badges)->firstWhere('type', 'low_stock');

    expect($lowStock)->toBeNull();
});

test('detects out of stock variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 0,
    ]);

    $badges = $this->service->generate($this->tenant);
    $outOfStock = collect($badges)->firstWhere('type', 'out_of_stock');

    expect($outOfStock)->not->toBeNull();
    expect($outOfStock['count'])->toBe(1);
});

test('detects near expiry variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 10,
        'expiry_date' => now()->addDays(3),
    ]);

    $badges = $this->service->generate($this->tenant);
    $nearExpiry = collect($badges)->firstWhere('type', 'near_expiry');

    expect($nearExpiry)->not->toBeNull();
    expect($nearExpiry['count'])->toBe(1);
});

test('does not flag near expiry when expiry is far away', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 10,
        'expiry_date' => now()->addDays(30),
    ]);

    $badges = $this->service->generate($this->tenant);
    $nearExpiry = collect($badges)->firstWhere('type', 'near_expiry');

    expect($nearExpiry)->toBeNull();
});

test('detects already expired variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 10,
        'expiry_date' => now()->subDays(1),
    ]);

    $badges = $this->service->generate($this->tenant);
    $expired = collect($badges)->firstWhere('type', 'expired');

    expect($expired)->not->toBeNull();
    expect($expired['count'])->toBe(1);
});

test('detects dead stock variants', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 20,
    ]);

    // No transaction items for this variant in last 30 days

    $badges = $this->service->generate($this->tenant);
    $deadStock = collect($badges)->firstWhere('type', 'dead_stock');

    expect($deadStock)->not->toBeNull();
    expect($deadStock['count'])->toBe(1);
});

test('does not flag dead stock if variant has recent sales', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 20,
    ]);

    // Create a recent transaction with this variant
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->user->id,
        'created_at' => now()->subDays(5),
    ]);

    TransactionItem::create([
        'transaction_id' => $transaction->id,
        'product_variant_id' => $variant->id,
        'variant_name' => $variant->name,
        'qty' => 1,
        'unit_price' => $variant->price,
        'subtotal' => $variant->price,
    ]);

    $badges = $this->service->generate($this->tenant);
    $deadStock = collect($badges)->firstWhere('type', 'dead_stock');

    expect($deadStock)->toBeNull();
});

test('does not show badges for other tenants', function () {
    $otherTenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 0, // out of stock, but belongs to OTHER tenant
    ]);

    $badges = $this->service->generate($this->tenant);

    // Should be empty — no products for $this->tenant
    expect($badges)->toBeEmpty();
});

test('near expiry does not flag variants with zero stock', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 0,
        'expiry_date' => now()->addDays(3),
    ]);

    $badges = $this->service->generate($this->tenant);
    $nearExpiry = collect($badges)->firstWhere('type', 'near_expiry');

    expect($nearExpiry)->toBeNull();
});
