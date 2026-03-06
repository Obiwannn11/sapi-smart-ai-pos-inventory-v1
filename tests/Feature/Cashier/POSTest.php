<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 50,
    ]);
    $this->paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'cash',
    ]);
});

test('cashier is redirected to cash drawer if no open session', function () {
    $this->actingAs($this->cashier)
        ->get('/cashier/pos')
        ->assertRedirect(route('cashier.cash-drawer.index'));
});

test('cashier can access POS with open cash drawer', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->get('/cashier/pos')
        ->assertStatus(200);
});

test('checkout creates transaction and deducts stock', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'variant_name' => $this->variant->name,
                    'qty' => 2,
                    'unit_price' => $this->variant->price,
                    'modifiers' => [],
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->paymentMethod->id,
                    'amount' => 50000,
                ],
            ],
        ])
        ->assertSessionHas('success');

    // Verify transaction created
    $this->assertDatabaseHas('transactions', [
        'tenant_id' => $this->tenant->id,
        'status' => 'completed',
        'total_amount' => 50000,
    ]);

    // Verify stock deducted
    $this->assertEquals(48, $this->variant->fresh()->stock);

    // Verify stock movement
    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'sale',
        'qty' => -2,
    ]);
});

test('checkout fails when stock is insufficient', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'variant_name' => $this->variant->name,
                    'qty' => 999,
                    'unit_price' => $this->variant->price,
                    'modifiers' => [],
                ],
            ],
            'payments' => [
                [
                    'payment_method_id' => $this->paymentMethod->id,
                    'amount' => 999 * 25000,
                ],
            ],
        ])
        ->assertSessionHas('error');

    // Stock unchanged
    $this->assertEquals(50, $this->variant->fresh()->stock);
});

test('checkout with modifiers includes modifier extra price', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $modifierGroup = \App\Models\ModifierGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $modifier = \App\Models\Modifier::factory()->create([
        'modifier_group_id' => $modifierGroup->id,
        'name' => 'Extra Cheese',
        'extra_price' => 5000,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/transactions', [
            'items' => [
                [
                    'variant_id' => $this->variant->id,
                    'variant_name' => $this->variant->name,
                    'qty' => 1,
                    'unit_price' => $this->variant->price,
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
                    'payment_method_id' => $this->paymentMethod->id,
                    'amount' => 30000, // 25000 + 5000
                ],
            ],
        ])
        ->assertSessionHas('success');

    // Total = unit_price (25000) + modifier (5000) = 30000
    $this->assertDatabaseHas('transactions', [
        'tenant_id' => $this->tenant->id,
        'total_amount' => 30000,
    ]);
});
