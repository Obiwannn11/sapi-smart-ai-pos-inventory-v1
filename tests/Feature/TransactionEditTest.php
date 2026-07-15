<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;

use function Pest\Laravel\actingAs;

/**
 * @return array{tenant: Tenant, owner: User, cashier: User, variant: ProductVariant, cash: PaymentMethod, transaction: Transaction}
 */
function makeEditContext(int $qty = 2): array
{
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 25000, 'stock' => 100,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    actingAs($cashier);
    $transaction = app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => 'Variant',
            'qty' => $qty,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $cash->id,
            'amount' => $qty * 25000,
        ]],
    ]);

    return compact('tenant', 'owner', 'cashier', 'variant', 'cash', 'transaction');
}

test('owner dapat mengedit transaksi via PUT', function () {
    ['owner' => $owner, 'variant' => $variant, 'cash' => $cash, 'transaction' => $tx] = makeEditContext();

    actingAs($owner)
        ->put(route('cashier.transactions.update', $tx->id), [
            'items' => [['variant_id' => $variant->id, 'qty' => 5]],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 125000]],
            'reason' => 'koreksi qty',
        ])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect($tx->fresh()->total_amount)->toBe('125000.00');
    expect($variant->fresh()->stock)->toBe(95);
});

test('kasir dengan laci terbuka dapat mengedit transaksi shiftnya', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash, 'transaction' => $tx] = makeEditContext();

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'opened_at' => now()->subHour(),
    ]);

    actingAs($cashier)
        ->put(route('cashier.transactions.update', $tx->id), [
            'items' => [['variant_id' => $variant->id, 'qty' => 3]],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 75000]],
        ])
        ->assertSessionHas('success');

    expect($tx->fresh()->total_amount)->toBe('75000.00');
});

test('kasir tanpa laci terbuka mendapat error dan transaksi tidak berubah', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash, 'transaction' => $tx] = makeEditContext();

    actingAs($cashier)
        ->put(route('cashier.transactions.update', $tx->id), [
            'items' => [['variant_id' => $variant->id, 'qty' => 3]],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 75000]],
        ])
        ->assertSessionHas('error');

    expect($tx->fresh()->total_amount)->toBe('50000.00');
});

test('edit tanpa item ditolak validasi', function () {
    ['owner' => $owner, 'cash' => $cash, 'transaction' => $tx] = makeEditContext();

    actingAs($owner)
        ->put(route('cashier.transactions.update', $tx->id), [
            'items' => [],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 1000]],
        ])
        ->assertSessionHasErrors('items');
});

test('transaksi tenant lain menghasilkan 404 lewat route binding', function () {
    ['transaction' => $tx] = makeEditContext();

    $otherOwner = User::factory()->create(['role' => 'owner']); // tenant berbeda

    actingAs($otherOwner)
        ->put(route('cashier.transactions.update', $tx->id), [
            'items' => [['variant_id' => 1, 'qty' => 1]],
            'payments' => [['payment_method_id' => 1, 'amount' => 1000]],
        ])
        ->assertNotFound();
});
