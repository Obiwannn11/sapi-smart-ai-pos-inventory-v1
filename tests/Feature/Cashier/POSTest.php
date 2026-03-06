<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

$tenant = null;
$cashier = null;
$product = null;
$variant = null;
$paymentMethod = null;

beforeEach(function () use (&$tenant, &$cashier, &$product, &$variant, &$paymentMethod) {
    $tenant = Tenant::factory()->create();
    $cashier = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
    ]);
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 50,
    ]);
    $paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'cash',
    ]);
});

test('cashier is redirected to cash drawer if no open session', function () use (&$cashier) {
    actingAs($cashier);

    get('/cashier/pos')->assertRedirect(route('cashier.cash-drawer.index'));
});

test('cashier can access POS with open cash drawer', function () use (&$tenant, &$cashier) {
    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    actingAs($cashier);

    get('/cashier/pos')->assertStatus(200);
});

test('checkout creates transaction and deducts stock', function () use (&$tenant, &$cashier, &$variant, &$paymentMethod) {
    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    actingAs($cashier);

    post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'variant_name' => $variant->name,
                    'qty' => 2,
                    'unit_price' => $variant->price,
                    'modifiers' => [],
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $paymentMethod->id,
                    'amount' => 50000,
                ],
            ],
        ])
        ->assertSessionHas('success');

    // Verify transaction created
    expect(Transaction::query()->where([
        'tenant_id' => $tenant->id,
        'status' => 'completed',
        'total_amount' => 50000,
    ])->exists())->toBeTrue();

    // Verify stock deducted
    expect($variant->fresh()->stock)->toBe(48);

    // Verify stock movement
    expect(StockMovement::query()->where([
        'product_variant_id' => $variant->id,
        'type' => 'sale',
        'qty' => -2,
    ])->exists())->toBeTrue();
});

test('checkout fails when stock is insufficient', function () use (&$tenant, &$cashier, &$variant, &$paymentMethod) {
    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    actingAs($cashier);

    post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'variant_name' => $variant->name,
                    'qty' => 999,
                    'unit_price' => $variant->price,
                    'modifiers' => [],
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $paymentMethod->id,
                    'amount' => 999 * 25000,
                ],
            ],
        ])
        ->assertSessionHas('error');

    // Stock unchanged
    expect($variant->fresh()->stock)->toBe(50);
});

test('checkout with modifiers includes modifier extra price', function () use (&$tenant, &$cashier, &$variant, &$paymentMethod) {
    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    $modifierGroup = \App\Models\ModifierGroup::factory()->create([
        'tenant_id' => $tenant->id,
    ]);
    $modifier = \App\Models\Modifier::factory()->create([
        'modifier_group_id' => $modifierGroup->id,
        'name' => 'Extra Cheese',
        'extra_price' => 5000,
    ]);

    actingAs($cashier);

    post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'variant_name' => $variant->name,
                    'qty' => 1,
                    'unit_price' => $variant->price,
                    'modifiers' => [
                        [
                            'id' => $modifier->id,
                            'name' => $modifier->name,
                            'extra_price' => $modifier->extra_price,
                        ],
                    ],
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $paymentMethod->id,
                    'amount' => 30000, // 25000 + 5000
                ],
            ],
        ])
        ->assertSessionHas('success');

    // Total = unit_price (25000) + modifier (5000) = 30000
    expect(Transaction::query()->where([
        'tenant_id' => $tenant->id,
        'total_amount' => 30000,
    ])->exists())->toBeTrue();
});
