<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionEditService;
use App\Services\TransactionService;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
    $this->cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);

    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variantA = ProductVariant::factory()->create([
        'product_id' => $this->product->id, 'price' => 25000, 'stock' => 100,
    ]);
    $this->variantB = ProductVariant::factory()->create([
        'product_id' => $this->product->id, 'price' => 10000, 'stock' => 50,
    ]);

    $this->cash = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->qris = PaymentMethod::factory()->qris()->create(['tenant_id' => $this->tenant->id]);

    $this->service = app(TransactionEditService::class);
});

/**
 * Buat transaksi completed via checkout (stok A terpotong), acting sebagai $creator.
 */
function makeCompletedTransaction($test, int $qtyA, ?User $creator = null): Transaction
{
    $creator ??= $test->cashier;
    $test->actingAs($creator);

    return app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => $test->variantA->id,
            'variant_name' => 'Variant A',
            'qty' => $qtyA,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $test->cash->id,
            'amount' => $qtyA * 25000,
        ]],
    ]);
}

test('naik qty menurunkan stok sebesar delta dan mencatat movement edit negatif', function () {
    $tx = makeCompletedTransaction($this, 2); // stok A: 100 → 98

    $this->service->edit($tx, [
        'items' => [[
            'variant_id' => $this->variantA->id,
            'qty' => 5,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->cash->id,
            'amount' => 125000,
        ]],
    ], $this->owner);

    // delta = 5 - 2 = 3 → stok turun 3 lagi: 98 → 95
    expect($this->variantA->fresh()->stock)->toBe(95);
    expect($tx->fresh()->total_amount)->toBe('125000.00');

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variantA->id,
        'type' => 'edit',
        'qty' => -3,
        'reference_id' => $tx->id,
    ]);
});

test('turun qty menaikkan stok dan mencatat movement edit positif', function () {
    $tx = makeCompletedTransaction($this, 5); // stok A: 100 → 95

    $this->service->edit($tx, [
        'items' => [[
            'variant_id' => $this->variantA->id,
            'qty' => 2,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->cash->id,
            'amount' => 50000,
        ]],
    ], $this->owner);

    // delta = 2 - 5 = -3 → stok kembali 3: 95 → 98
    expect($this->variantA->fresh()->stock)->toBe(98);

    $this->assertDatabaseHas('stock_movements', [
        'product_variant_id' => $this->variantA->id,
        'type' => 'edit',
        'qty' => 3,
        'reference_id' => $tx->id,
    ]);
});

test('tambah item baru mengurangi stoknya dan hapus item mengembalikan stok', function () {
    $tx = makeCompletedTransaction($this, 2); // A: 98, B: 50

    // Ganti isi: hapus A (kembali 2), tambah B qty 4
    $this->service->edit($tx, [
        'items' => [[
            'variant_id' => $this->variantB->id,
            'qty' => 4,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->cash->id,
            'amount' => 40000,
        ]],
    ], $this->owner);

    expect($this->variantA->fresh()->stock)->toBe(100); // A dikembalikan
    expect($this->variantB->fresh()->stock)->toBe(46);  // B berkurang 4
    expect($tx->fresh()->total_amount)->toBe('40000.00');
});

test('ganti pembayaran menghapus payment lama menyimpan baru dan menghitung change', function () {
    $tx = makeCompletedTransaction($this, 2); // total 50000, cash

    $this->service->edit($tx, [
        'items' => [[
            'variant_id' => $this->variantA->id,
            'qty' => 2,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->qris->id,
            'amount' => 60000,
        ]],
    ], $this->owner);

    $tx->refresh()->load('payments');
    expect($tx->payments)->toHaveCount(1);
    expect($tx->payments->first()->payment_method_id)->toBe($this->qris->id);
    expect($tx->change_amount)->toBe('10000.00');
});

test('tolak edit yang menaikkan qty melebihi stok tersisa dan rollback', function () {
    $tx = makeCompletedTransaction($this, 2); // A: 98

    expect(fn () => $this->service->edit($tx, [
        'items' => [[
            'variant_id' => $this->variantA->id,
            'qty' => 500,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->cash->id,
            'amount' => 500 * 25000,
        ]],
    ], $this->owner))->toThrow(\Exception::class);

    // Rollback: stok & items tidak berubah
    expect($this->variantA->fresh()->stock)->toBe(98);
    expect($tx->fresh()->items()->sum('qty'))->toBe(2);
});

test('pembayaran kurang dari total membuat seluruh edit rollback', function () {
    $tx = makeCompletedTransaction($this, 2); // A: 98

    expect(fn () => $this->service->edit($tx, [
        'items' => [[
            'variant_id' => $this->variantA->id,
            'qty' => 4,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->cash->id,
            'amount' => 10000, // kurang dari 100000
        ]],
    ], $this->owner))->toThrow(\Exception::class);

    expect($this->variantA->fresh()->stock)->toBe(98); // tidak berubah
    expect($tx->fresh()->total_amount)->toBe('50000.00');
});

test('kasir dengan laci terbuka bisa edit transaksi dalam shiftnya', function () {
    $drawer = CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opened_at' => now()->subHour(),
    ]);

    $tx = makeCompletedTransaction($this, 2); // dibuat sekarang (>= opened_at)

    $this->service->edit($tx, [
        'items' => [['variant_id' => $this->variantA->id, 'qty' => 3, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 75000]],
    ], $this->cashier);

    expect($tx->fresh()->total_amount)->toBe('75000.00');
});

test('kasir tidak bisa edit transaksi sebelum laci dibuka', function () {
    $tx = makeCompletedTransaction($this, 2);
    // Transaksi dibuat lebih dulu, laci dibuka setelahnya
    $tx->created_at = now()->subHours(3);
    $tx->save();

    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opened_at' => now()->subHour(),
    ]);

    expect(fn () => $this->service->edit($tx, [
        'items' => [['variant_id' => $this->variantA->id, 'qty' => 3, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 75000]],
    ], $this->cashier))->toThrow(\Exception::class, 'shift');
});

test('kasir tanpa laci terbuka tidak bisa edit', function () {
    $tx = makeCompletedTransaction($this, 2);

    expect(fn () => $this->service->edit($tx, [
        'items' => [['variant_id' => $this->variantA->id, 'qty' => 3, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 75000]],
    ], $this->cashier))->toThrow(\Exception::class, 'Buka shift');
});

test('owner bisa edit transaksi lampau tanpa laci', function () {
    $tx = makeCompletedTransaction($this, 2);
    $tx->created_at = now()->subDays(10);
    $tx->save();

    $this->service->edit($tx, [
        'items' => [['variant_id' => $this->variantA->id, 'qty' => 3, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 75000]],
    ], $this->owner);

    expect($tx->fresh()->total_amount)->toBe('75000.00');
});

test('tidak bisa edit transaksi voided atau pending', function () {
    $tx = makeCompletedTransaction($this, 2);
    $tx->update(['status' => Transaction::STATUS_VOIDED]);

    expect(fn () => $this->service->edit($tx, [
        'items' => [['variant_id' => $this->variantA->id, 'qty' => 3, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 75000]],
    ], $this->owner))->toThrow(\Exception::class, 'completed');
});

test('user dari tenant lain ditolak', function () {
    $tx = makeCompletedTransaction($this, 2);
    $otherOwner = User::factory()->create(['role' => 'owner']); // tenant berbeda

    expect(fn () => $this->service->edit($tx, [
        'items' => [['variant_id' => $this->variantA->id, 'qty' => 3, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 75000]],
    ], $otherOwner))->toThrow(\Exception::class, 'outlet');
});

test('setiap edit membuat satu baris audit dengan before after dan edited_by', function () {
    $tx = makeCompletedTransaction($this, 2);

    $this->service->edit($tx, [
        'items' => [['variant_id' => $this->variantA->id, 'qty' => 4, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 100000]],
        'reason' => 'salah input qty',
    ], $this->owner);

    $tx->refresh();
    expect($tx->edits()->count())->toBe(1);
    expect($tx->edited_by)->toBe($this->owner->id);
    expect($tx->edited_at)->not->toBeNull();

    $edit = $tx->edits()->first();
    expect($edit->reason)->toBe('salah input qty');
    expect($edit->before['total_amount'])->toBe('50000.00');
    expect($edit->after['total_amount'])->toBe('100000.00');
});
