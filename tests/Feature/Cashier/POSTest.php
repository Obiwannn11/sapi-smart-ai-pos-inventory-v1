<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockMovement;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{tenant: Tenant, cashier: User, variant: ProductVariant, paymentMethod: PaymentMethod}
 */
function makePOSContext(): array
{
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

    return [
        'tenant' => $tenant,
        'cashier' => $cashier,
        'variant' => $variant,
        'paymentMethod' => $paymentMethod,
    ];
}

test('cashier is redirected to cash drawer if no open session', function () {
    ['cashier' => $cashier] = makePOSContext();

    actingAs($cashier);

    get('/cashier/pos')->assertRedirect(route('cashier.cash-drawer.index'));
});

test('cashier can access POS with open cash drawer', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = makePOSContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    actingAs($cashier);

    get('/cashier/pos')->assertStatus(200);
});

test('checkout creates transaction and deducts stock', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makePOSContext();

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

test('checkout fails when stock is insufficient', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makePOSContext();

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

test('checkout is idempotent for a repeated client_uuid', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makePOSContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    actingAs($cashier);

    $clientUuid = (string) Str::uuid();

    $payload = [
        'client_uuid' => $clientUuid,
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
    ];

    post('/cashier/transactions', $payload)->assertSessionHas('success');
    post('/cashier/transactions', $payload)->assertSessionHas('success');

    // Only one transaction persisted for this client_uuid
    expect(Transaction::query()->where('client_uuid', $clientUuid)->count())->toBe(1);

    // Stock deducted exactly once (50 - 2), not twice
    expect($variant->fresh()->stock)->toBe(48);
});

test('checkout rejects a malformed client_uuid', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makePOSContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    actingAs($cashier);

    post('/cashier/transactions', [
        'client_uuid' => 'not-a-uuid',
        'items' => [
            [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => $variant->price,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 25000,
            ],
        ],
    ])->assertSessionHasErrors('client_uuid');

    expect(Transaction::query()->count())->toBe(0);
});

test('checkout with modifiers includes modifier extra price', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makePOSContext();

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

/**
 * [BL-022] StoreTransactionRequest menghitung "cukup bayar" dari `unit_price`
 * kiriman klien. Perangkat yang memakai katalog offline basi mengirim harga
 * lama, lolos validasi, lalu checkout menghitung ulang dari harga DB — dan
 * dulu tetap menyelesaikannya sebagai `completed` dengan kurang bayar.
 */
test('checkout rejects payment that is short against DB prices, not client prices', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makePOSContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    // Owner menaikkan harga setelah perangkat memanen katalognya.
    $variant->update(['price' => 40000]);

    actingAs($cashier);

    post('/cashier/transactions', [
        'items' => [
            [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => 25000, // harga basi dari snapshot
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 25000,
            ],
        ],
    ])->assertSessionHas('error');

    expect(Transaction::query()->where('status', Transaction::STATUS_COMPLETED)->count())->toBe(0);
});

test('checkout still succeeds when payment covers the DB price', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $paymentMethod] = makePOSContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    $variant->update(['price' => 40000]);

    actingAs($cashier);

    post('/cashier/transactions', [
        'items' => [
            [
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => 40000,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            [
                'payment_method_id' => $paymentMethod->id,
                'amount' => 50000,
            ],
        ],
    ])->assertSessionHas('success');

    $transaction = Transaction::query()->where('status', Transaction::STATUS_COMPLETED)->sole();

    expect((float) $transaction->total_amount)->toBe(40000.0)
        ->and((float) $transaction->change_amount)->toBe(10000.0);
});

/**
 * [BL-021] Split bill. Modal-nya dulu membekukan nominal non-tunai saat metode
 * dipilih, sehingga koreksi pada baris tunai meninggalkan angka QRIS yang basi.
 * Test ini menjaga sisi yang benar-benar tersimpan: tiap metode membawa
 * nominalnya sendiri, bukan hanya totalnya yang kebetulan cocok.
 */
test('split payment records each method with its own amount', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $cash] = makePOSContext();

    $qris = PaymentMethod::factory()->qris()->create(['tenant_id' => $tenant->id]);

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
                'qty' => 4, // 4 x 25000 = 100000
                'unit_price' => $variant->price,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            ['payment_method_id' => $cash->id, 'amount' => 60000],
            ['payment_method_id' => $qris->id, 'amount' => 40000],
        ],
    ])->assertSessionHas('success');

    $transaction = Transaction::query()->where('status', Transaction::STATUS_COMPLETED)->sole();

    expect((float) $transaction->total_amount)->toBe(100000.0)
        ->and((float) $transaction->change_amount)->toBe(0.0)
        ->and((float) $transaction->payments()->where('payment_method_id', $cash->id)->value('amount'))->toBe(60000.0)
        ->and((float) $transaction->payments()->where('payment_method_id', $qris->id)->value('amount'))->toBe(40000.0);
});

test('split payment that falls short of the total is rejected', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $cash] = makePOSContext();

    $qris = PaymentMethod::factory()->qris()->create(['tenant_id' => $tenant->id]);

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
                'qty' => 4,
                'unit_price' => $variant->price,
                'modifiers' => [],
            ],
        ],
        'payments' => [
            ['payment_method_id' => $cash->id, 'amount' => 60000],
            ['payment_method_id' => $qris->id, 'amount' => 30000], // total 90rb dari 100rb
        ],
    ])->assertSessionHasErrors('payments');

    expect(Transaction::query()->count())->toBe(0);
});

test('open bill can be settled with a split payment', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'paymentMethod' => $cash] = makePOSContext();

    $qris = PaymentMethod::factory()->qris()->create(['tenant_id' => $tenant->id]);

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
                'qty' => 4,
                'unit_price' => $variant->price,
                'modifiers' => [],
            ],
        ],
        'payments' => null,
        'is_open_bill' => true,
        'customer_name' => 'Meja 3',
    ])->assertSessionHas('success');

    $bill = Transaction::query()->where('status', Transaction::STATUS_PENDING)->sole();

    post("/cashier/transactions/{$bill->id}/pay", [
        'payments' => [
            ['payment_method_id' => $cash->id, 'amount' => 70000],
            ['payment_method_id' => $qris->id, 'amount' => 30000],
        ],
    ])->assertSessionHas('success');

    $bill->refresh();

    expect($bill->status)->toBe(Transaction::STATUS_COMPLETED)
        ->and($bill->payments)->toHaveCount(2)
        ->and((float) $bill->payments->sum('amount'))->toBe(100000.0);
});

/**
 * [BL-027] Riwayat kasir dulu membuka SELURUH riwayat akun karena filter
 * tanggal bersifat opsional dan tanpa nilai bawaan.
 */
test('history is limited to the cashier open drawer session', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = makePOSContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'opened_at' => now()->subHours(3),
        'closed_at' => null,
    ]);

    $thisShift = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'occurred_at' => now()->subHour(),
    ]);

    $lastWeek = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'occurred_at' => now()->subWeek(),
    ]);

    actingAs($cashier);

    get('/cashier/transactions')
        ->assertInertia(fn ($page) => $page
            ->component('Cashier/TransactionHistory')
            ->where('transactions.data.0.id', $thisShift->id)
            ->count('transactions.data', 1)
            ->where('scope.can_filter_date', false)
        );

    expect($lastWeek->exists)->toBeTrue();
});

test('history falls back to today when the cashier has no open drawer', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = makePOSContext();

    $today = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'occurred_at' => now(),
    ]);

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'occurred_at' => now()->subDays(2),
    ]);

    actingAs($cashier);

    get('/cashier/transactions')
        ->assertInertia(fn ($page) => $page
            ->count('transactions.data', 1)
            ->where('transactions.data.0.id', $today->id)
        );
});

test('cashier cannot widen the history with a date parameter', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = makePOSContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'opened_at' => now()->subHours(2),
        'closed_at' => null,
    ]);

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'occurred_at' => now()->subWeek(),
    ]);

    actingAs($cashier);

    // Tanggal diselundupkan lewat query string — harus diabaikan, bukan
    // sekadar disembunyikan tombolnya di UI.
    get('/cashier/transactions?date='.now()->subWeek()->toDateString())
        ->assertInertia(fn ($page) => $page
            ->count('transactions.data', 0)
            ->where('filters.date', null)
        );
});

test('owner may still pick a date on the cashier history', function () {
    ['tenant' => $tenant] = makePOSContext();

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    $old = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'occurred_at' => now()->subWeek(),
    ]);

    actingAs($owner);

    get('/cashier/transactions?date='.now()->subWeek()->toDateString())
        ->assertInertia(fn ($page) => $page
            ->count('transactions.data', 1)
            ->where('transactions.data.0.id', $old->id)
            ->where('scope.can_filter_date', true)
        );
});
