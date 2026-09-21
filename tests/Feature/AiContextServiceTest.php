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

/**
 * Helper: sama seperti seedSale(), tapi terhadap varian mana pun.
 */
function seedSaleFor(\App\Models\ProductVariant $variant, int $qty, int $subtotal): Transaction
{
    $transaction = Transaction::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $subtotal,
        'code' => 'TRX-'.fake()->unique()->numerify('########'),
    ]);

    $transaction->items()->create([
        'product_variant_id' => $variant->id,
        'variant_name' => $variant->name,
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
        ->and($top->product_name)->toBe($this->product->name)
        ->and((int) $top->qty)->toBe(3);
});

test('top_products keeps two products apart when their variants share a name', function () {
    // Nama varian yang sama pada dua produk berbeda — "Hot" milik Cafe Latte
    // dan "Hot" milik Kopi Susu Gula Aren adalah kejadian sehari-hari di
    // katalog F&B mana pun. Sebelum ini keduanya menyatu jadi satu baris
    // bernama "Hot", dan model menalar di atas qty gabungan itu.
    $latte = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Cafe Latte']);
    $latteHot = ProductVariant::factory()->create([
        'product_id' => $latte->id,
        'name' => 'Hot',
        'price' => 20000,
        'cost_price' => 8000,
    ]);

    $aren = Product::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Kopi Susu Gula Aren']);
    $arenHot = ProductVariant::factory()->create([
        'product_id' => $aren->id,
        'name' => 'Hot',
        'price' => 22000,
        'cost_price' => 9000,
    ]);

    seedSaleFor($latteHot, qty: 4, subtotal: 80000);
    seedSaleFor($arenHot, qty: 6, subtotal: 132000);

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());

    $hotRows = collect($context['top_products'])->where('variant_name', 'Hot');

    expect($hotRows)->toHaveCount(2)
        ->and($hotRows->firstWhere('product_name', 'Cafe Latte')->qty)->toEqual(4)
        ->and($hotRows->firstWhere('product_name', 'Kopi Susu Gula Aren')->qty)->toEqual(6);
});

test('buildContext excludes PII fields from the context', function () {
    seedSale(qty: 2, subtotal: 50000, customerName: 'Budi Santoso', tableNumber: '12');

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());

    $encoded = json_encode($context);

    expect($encoded)->not->toContain('Budi Santoso')
        ->and($encoded)->not->toContain('customer_name')
        ->and($encoded)->not->toContain('table_number');
});

/**
 * Helper: satu varian tambahan beserta satu penjualan untuknya.
 *
 * Qty-nya jadi parameter karena urutan `profit_by_item` adalah qty menurun —
 * itulah yang menentukan varian mana yang muat dan mana yang jatuh ke
 * ringkasan.
 */
function seedVariantSale(string $name, int $qty, int $unitPrice = 10000, int $costPrice = 6000): void
{
    $variant = ProductVariant::factory()->create([
        'product_id' => test()->product->id,
        'name' => $name,
        'price' => $unitPrice,
        'cost_price' => $costPrice,
        'stock' => 100,
    ]);

    $transaction = Transaction::factory()->create([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $qty * $unitPrice,
        'code' => 'TRX-'.fake()->unique()->numerify('########'),
    ]);

    $transaction->items()->create([
        'product_variant_id' => $variant->id,
        'variant_name' => $name,
        'qty' => $qty,
        'unit_price' => $unitPrice,
        'subtotal' => $qty * $unitPrice,
    ]);
}

test('profit_by_item carries every variant when the catalogue fits the cap', function () {
    config(['ai.context.profit_by_item_limit' => 5]);

    seedVariantSale('Varian A', qty: 9);
    seedVariantSale('Varian B', qty: 8);

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());

    expect($context['profit_by_item']['shown'])->toBe(2)
        ->and($context['profit_by_item']['total'])->toBe(2)
        ->and($context['profit_by_item']['others'])->toBeNull()
        ->and($context['profit_by_item']['items'])->toHaveCount(2);
});

test('profit_by_item stops at the cap and rolls the rest into one aggregate', function () {
    config(['ai.context.profit_by_item_limit' => 3]);

    // Qty menurun, supaya urutannya pasti: A..E muat berurutan, D dan E jatuh.
    seedVariantSale('Varian A', qty: 50);
    seedVariantSale('Varian B', qty: 40);
    seedVariantSale('Varian C', qty: 30);
    seedVariantSale('Varian D', qty: 20);
    seedVariantSale('Varian E', qty: 10);

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());
    $profit = $context['profit_by_item'];

    expect($profit['shown'])->toBe(3)
        ->and($profit['total'])->toBe(5)
        ->and($profit['items'])->toHaveCount(3)
        ->and(collect($profit['items'])->pluck('variant_name')->all())
        ->toBe(['Varian A', 'Varian B', 'Varian C']);

    // Sisanya tidak hilang: 20 + 10 unit @ 10.000 jual, 6.000 modal.
    expect($profit['others']['variants'])->toBe(2)
        ->and($profit['others']['qty'])->toBe(30)
        ->and($profit['others']['revenue'])->toBe(300000.0)
        ->and($profit['others']['cogs'])->toBe(180000.0)
        ->and($profit['others']['margin'])->toBe(120000.0)
        ->and($profit['others']['margin_pct'])->toBe(40.0);
});

test('profit_by_item payload stays bounded as the catalogue grows', function () {
    config(['ai.context.profit_by_item_limit' => 20]);

    foreach (range(1, 60) as $i) {
        seedVariantSale('Varian '.str_pad((string) $i, 3, '0', STR_PAD_LEFT), qty: 100 - $i);
    }

    $context = $this->service->buildContext($this->tenant, now()->subDay(), now()->addDay());

    // Inilah pagar ongkosnya (`[BL-069]`): 60 varian, payload tetap 20 baris.
    expect($context['profit_by_item']['items'])->toHaveCount(20)
        ->and($context['profit_by_item']['total'])->toBe(60)
        ->and($context['profit_by_item']['others']['variants'])->toBe(40);
});
