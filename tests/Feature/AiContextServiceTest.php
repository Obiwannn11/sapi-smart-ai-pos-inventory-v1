<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AiContextService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Kopi Senja']);
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'name' => 'Kopi Susu',
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 100,
    ]);

    $this->service = app(AiContextService::class);
});

/**
 * Helper: transaksi completed + item terhadap varian utama.
 */
function seedSale(int $qty, int $subtotal, ?string $customerName = null, ?string $tableNumber = null): Transaction
{
    $transaction = Transaction::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $subtotal,
        'code' => 'TRX-'.fake()->unique()->numerify('########'),
        'customer_name' => $customerName,
        'table_number' => $tableNumber,
    ]);

    $transaction->items()->create([
        'product_variant_id' => test()->variant->id,
        'variant_name' => test()->variant->name,
        'qty' => $qty,
        'unit_price' => $subtotal / $qty,
        'subtotal' => $subtotal,
    ]);

    return $transaction;
}

test('buildContext returns the full aggregate structure', function () {
    seedSale(qty: 2, subtotal: 50000);

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());

    expect($context)->toHaveKeys([
        'business', 'sales', 'profit', 'projection',
        'profit_by_item', 'top_products', 'daily_trend', 'inventory',
    ]);

    expect($context['business']['name'])->toBe('Kopi Senja');
});

test('buildContext aggregates sales, profit and top products from seeded data', function () {
    seedSale(qty: 2, subtotal: 50000);
    seedSale(qty: 1, subtotal: 25000);

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());

    expect($context['sales']['revenue'])->toBe(75000.0)
        ->and($context['sales']['transaction_count'])->toBe(2)
        ->and($context['sales']['average_ticket'])->toBe(37500.0);

    // 3 unit terjual @ subtotal total 75000; cogs 3 × 15000 = 45000.
    expect($context['profit']['revenue'])->toBe(75000.0)
        ->and($context['profit']['cogs'])->toBe(45000.0)
        ->and($context['profit']['gross_profit'])->toBe(30000.0);

    $top = $context['top_products']->first();
    expect($top->variant_name)->toBe('Kopi Susu')
        ->and((int) $top->qty)->toBe(3);
});

test('buildContext excludes PII fields from the context', function () {
    seedSale(qty: 2, subtotal: 50000, customerName: 'Budi Santoso', tableNumber: '12');

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());

    $encoded = json_encode($context);

    expect($encoded)->not->toContain('Budi Santoso')
        ->and($encoded)->not->toContain('customer_name')
        ->and($encoded)->not->toContain('table_number');
});
