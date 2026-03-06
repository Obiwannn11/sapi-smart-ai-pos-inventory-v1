<?php

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\TransactionService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->user = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
    $this->actingAs($this->user);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 100,
    ]);
    $this->paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);
    $this->service = app(TransactionService::class);
});

test('checkout creates complete transaction', function () {
    $transaction = $this->service->checkout([
        'items' => [
            [
                'variant_id' => $this->variant->id,
                'variant_name' => 'Test Variant',
                'qty' => 2,
                'unit_price' => 25000,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 50000,
            ],
        ],
    ]);

    expect($transaction->status)->toBe('completed');
    expect($transaction->total_amount)->toBe('50000.00');
    expect($transaction->items)->toHaveCount(1);
    expect($transaction->payments)->toHaveCount(1);
});

test('checkout deducts stock correctly', function () {
    $this->service->checkout([
        'items' => [
            [
                'variant_id' => $this->variant->id,
                'variant_name' => 'Test Variant',
                'qty' => 5,
                'unit_price' => 25000,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 125000,
            ],
        ],
    ]);

    expect($this->variant->fresh()->stock)->toBe(95);
});

test('checkout creates stock movement record', function () {
    $this->service->checkout([
        'items' => [
            [
                'variant_id' => $this->variant->id,
                'variant_name' => 'Test Variant',
                'qty' => 3,
                'unit_price' => 25000,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 75000,
            ],
        ],
    ]);

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'sale',
        'qty' => -3,
    ]);
});

test('checkout with modifiers calculates total correctly', function () {
    $modifierGroup = ModifierGroup::factory()->create(['tenant_id' => $this->tenant->id]);
    $modifier = Modifier::factory()->create([
        'modifier_group_id' => $modifierGroup->id,
        'name' => 'Extra Cheese',
        'extra_price' => 5000,
    ]);

    $transaction = $this->service->checkout([
        'items' => [
            [
                'variant_id' => $this->variant->id,
                'variant_name' => 'Test Variant',
                'qty' => 2,
                'unit_price' => 25000,
                'modifiers' => [
                    ['id' => $modifier->id, 'name' => 'Extra Cheese', 'extra_price' => 5000],
                ],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 60000,
            ],
        ],
    ]);

    // (25000 + 5000) * 2 = 60000
    expect($transaction->total_amount)->toBe('60000.00');
});

test('checkout calculates change amount', function () {
    $transaction = $this->service->checkout([
        'items' => [
            [
                'variant_id' => $this->variant->id,
                'variant_name' => 'Test Variant',
                'qty' => 1,
                'unit_price' => 25000,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 50000, // bayar 50000 untuk produk 25000
            ],
        ],
    ]);

    expect($transaction->change_amount)->toBe('25000.00');
});

test('transaction code is unique per day per tenant', function () {
    $makeCheckout = fn () => $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 1,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 25000,
        ]],
    ]);

    $tx1 = $makeCheckout();
    $tx2 = $makeCheckout();

    expect($tx1->code)->not->toBe($tx2->code);
    expect($tx1->code)->toMatch('/^TRX-\d{8}-001$/');
    expect($tx2->code)->toMatch('/^TRX-\d{8}-002$/');
});

test('checkout throws exception when stock is insufficient', function () {
    expect(fn () => $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 999,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 999 * 25000,
        ]],
    ]))->toThrow(\Exception::class);

    // Stock unchanged
    expect($this->variant->fresh()->stock)->toBe(100);
});

test('void returns stock and changes status', function () {
    $transaction = $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 5,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 125000,
        ]],
    ]);

    expect($this->variant->fresh()->stock)->toBe(95);

    $voided = $this->service->void($transaction);

    expect($voided->status)->toBe('voided');
    expect($this->variant->fresh()->stock)->toBe(100);
});

test('void creates restore stock movement', function () {
    $transaction = $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 3,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 75000,
        ]],
    ]);

    $this->service->void($transaction);

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variant->id,
        'type' => 'void',
        'qty' => 3,
        'reference_id' => $transaction->id,
    ]);
});

test('void throws exception for non-completed transactions', function () {
    $transaction = $this->service->checkout([
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'V1',
            'qty' => 1,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->paymentMethod->id,
            'amount' => 25000,
        ]],
    ]);

    // Void first time
    $this->service->void($transaction);

    // Void again - should fail because status is now 'voided'
    expect(fn () => $this->service->void($transaction->fresh()))
        ->toThrow(\Exception::class, 'completed');
});

test('checkout uses DB price not client price', function () {
    // Client sends wrong price, but service should use DB price
    $transaction = $this->service->checkout([
        'items' => [
            [
                'variant_id' => $this->variant->id,
                'variant_name' => 'Test Variant',
                'qty' => 1,
                'unit_price' => 99999, // client sends inflated price
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $this->paymentMethod->id,
                'amount' => 99999,
            ],
        ],
    ]);

    // total_amount uses DB price (25000), not client price (99999)
    expect($transaction->total_amount)->toBe('25000.00');
});
