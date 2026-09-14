<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\BusinessClock;
use App\Services\StockService;
use App\Services\TransactionEditService;
use App\Services\TransactionService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Gerbang penjualan barang kedaluwarsa ([BL-108]).
 *
 * Berkas ini dulu membuktikan celahnya: `Croissant - Plain` yang basi 57 hari
 * lolos checkout pada harga penuh, tanpa peringatan dan tanpa jejak. Sekarang
 * ia membuktikan keputusan pemilik — 2026-09-08: izinkan, tapi dengan
 * konfirmasi yang tercatat; 2026-09-15: kasir boleh mengonfirmasi dengan alasan
 * tertulis, dan pemilik meninjaunya sesudahnya.
 */
function makeExpiredSaleContext(): array
{
    $tenant = Tenant::factory()->create();

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    $cashier = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
    ]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 10,
        'expiry_date' => now()->subDays(57)->toDateString(),
    ]);

    $paymentMethod = PaymentMethod::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'cash',
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    return compact('tenant', 'owner', 'cashier', 'variant', 'paymentMethod');
}

function expiredSaleLine(ProductVariant $variant, int $qty = 1, ?string $reason = null): array
{
    return [
        'variant_id' => $variant->id,
        'variant_name' => $variant->name,
        'qty' => $qty,
        'unit_price' => (float) $variant->price,
        'modifiers' => [],
        'expired_confirmation_reason' => $reason,
    ];
}

function expiredSalePayload(array $ctx, int $qty = 1, ?string $reason = null): array
{
    return [
        'items' => [expiredSaleLine($ctx['variant'], $qty, $reason)],
        'payments' => [[
            'payment_method_id' => $ctx['paymentMethod']->id,
            'amount' => (float) $ctx['variant']->price * $qty,
        ]],
    ];
}

test('checkout menolak barang kedaluwarsa tanpa alasan, dan stoknya tidak bergerak', function () {
    $ctx = makeExpiredSaleContext();

    actingAs($ctx['cashier']);

    // Reproduksi TRX-20260908-001: sekarang berhenti di server, bukan di layar.
    post('/cashier/transactions', expiredSalePayload($ctx))
        ->assertSessionHas('error', fn (string $message) => str_contains($message, 'Isi alasan untuk tetap menjual'));

    expect($ctx['variant']->fresh()->stock)->toBe(10)
        ->and(Transaction::where('tenant_id', $ctx['tenant']->id)->exists())->toBeFalse();
});

test('dengan alasan, penjualan tercatat bersama siapa, tanggal basinya, dan alasannya', function () {
    $ctx = makeExpiredSaleContext();
    $expiredOn = $ctx['variant']->expiry_date->toDateString();

    actingAs($ctx['cashier']);

    post('/cashier/transactions', expiredSalePayload($ctx, reason: 'Pelanggan tetap minta, sudah diberi tahu'))
        ->assertSessionHas('success');

    $item = TransactionItem::where('product_variant_id', $ctx['variant']->id)->sole();

    expect($item->expired_qty)->toBe(1)
        ->and($item->expiry_date_at_sale->toDateString())->toBe($expiredOn)
        ->and($item->expired_sale_confirmed_by)->toBe($ctx['cashier']->id)
        ->and($item->expired_sale_reason)->toBe('Pelanggan tetap minta, sudah diberi tahu')
        ->and($ctx['variant']->fresh()->stock)->toBe(9);
});

test('barang yang belum basi tetap terjual tanpa ditanya walau batch basi masih ada di rak', function () {
    $ctx = makeExpiredSaleContext();

    app(StockService::class)->restock($ctx['variant'], 5, 'Kiriman baru', now()->addDays(30)->toDateString());

    actingAs($ctx['cashier']);

    post('/cashier/transactions', expiredSalePayload($ctx, qty: 3, reason: 'Dikirim tanpa perlu'))
        ->assertSessionHas('success');

    $item = TransactionItem::where('product_variant_id', $ctx['variant']->id)->sole();

    // Alasan yang dikirim untuk baris yang tidak menyentuh barang basi tidak
    // ditulis: kolomnya hanya menyala pada penjualan yang benar-benar terjadi.
    expect($item->expired_qty)->toBe(0)
        ->and($item->expired_sale_reason)->toBeNull();

    // Sepuluh unit basi masih utuh, menunggu dibuang.
    $expiredLeft = ProductStockBatch::where('product_variant_id', $ctx['variant']->id)
        ->expiredOn(BusinessClock::today())
        ->sum('qty_remaining');

    expect((int) $expiredLeft)->toBe(10);
});

test('void mengembalikan barang basi ke batch basinya, bukan jadi stok yang bisa dijual lagi', function () {
    $ctx = makeExpiredSaleContext();

    actingAs($ctx['cashier']);

    post('/cashier/transactions', expiredSalePayload($ctx, reason: 'Sudah diberi tahu'))
        ->assertSessionHas('success');

    app(TransactionService::class)->void(Transaction::where('tenant_id', $ctx['tenant']->id)->sole());

    $variant = $ctx['variant']->fresh();

    expect($variant->stock)->toBe(10)
        ->and(app(StockService::class)->freshUnits($variant))->toBe(0);
});

test('penjualan offline barang basi tanpa alasan tetap tersimpan, tapi sampai ke meja owner', function () {
    $ctx = makeExpiredSaleContext();

    $transaction = app(TransactionService::class)->commitOffline([
        'client_uuid' => (string) Str::uuid(),
        'occurred_at' => now()->subMinutes(10)->toIso8601String(),
        'items' => [expiredSaleLine($ctx['variant'])],
        'payments' => [['payment_method_id' => $ctx['paymentMethod']->id, 'amount' => 25000]],
    ], $ctx['cashier']);

    $item = $transaction->items->sole();

    expect($transaction->needsReview())->toBeTrue()
        ->and($item->expired_qty)->toBe(1)
        ->and($item->expired_sale_confirmed_by)->toBeNull();
});

test('penjualan offline barang basi dengan alasan tercatat atas nama kasirnya', function () {
    $ctx = makeExpiredSaleContext();

    $transaction = app(TransactionService::class)->commitOffline([
        'client_uuid' => (string) Str::uuid(),
        'occurred_at' => now()->subMinutes(10)->toIso8601String(),
        'items' => [expiredSaleLine($ctx['variant'], reason: 'Sudah diberi tahu')],
        'payments' => [['payment_method_id' => $ctx['paymentMethod']->id, 'amount' => 25000]],
    ], $ctx['cashier']);

    $item = $transaction->items->sole();

    expect($transaction->needsReview())->toBeFalse()
        ->and($item->expired_sale_confirmed_by)->toBe($ctx['cashier']->id)
        ->and($item->expired_sale_reason)->toBe('Sudah diberi tahu');
});

test('edit transaksi tidak bisa menambah qty dari barang yang sudah basi', function () {
    $ctx = makeExpiredSaleContext();

    actingAs($ctx['cashier']);

    post('/cashier/transactions', expiredSalePayload($ctx, reason: 'Sudah diberi tahu'))
        ->assertSessionHas('success');

    $transaction = Transaction::where('tenant_id', $ctx['tenant']->id)->sole();

    expect(fn () => app(TransactionEditService::class)->edit($transaction, [
        'items' => [['variant_id' => $ctx['variant']->id, 'qty' => 2, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $ctx['paymentMethod']->id, 'amount' => 50000]],
    ], $ctx['owner']))->toThrow(Exception::class, 'hanya bisa dijual dari layar kasir');

    expect($ctx['variant']->fresh()->stock)->toBe(9);
});

test('edit yang tidak mengubah qty mempertahankan jejak barang basinya', function () {
    $ctx = makeExpiredSaleContext();

    actingAs($ctx['cashier']);

    post('/cashier/transactions', expiredSalePayload($ctx, reason: 'Sudah diberi tahu'))
        ->assertSessionHas('success');

    $transaction = Transaction::where('tenant_id', $ctx['tenant']->id)->sole();

    app(TransactionEditService::class)->edit($transaction, [
        'items' => [['variant_id' => $ctx['variant']->id, 'qty' => 1, 'notes' => 'Tanpa kantong', 'modifiers' => []]],
        'payments' => [['payment_method_id' => $ctx['paymentMethod']->id, 'amount' => 25000]],
    ], $ctx['owner']);

    $item = $transaction->fresh()->items->sole();

    expect($item->expired_qty)->toBe(1)
        ->and($item->expired_sale_confirmed_by)->toBe($ctx['cashier']->id)
        ->and($item->expired_sale_reason)->toBe('Sudah diberi tahu');
});

test('pesanan mandiri tidak bisa memesan barang yang sudah basi', function () {
    $ctx = makeExpiredSaleContext();

    actingAs($ctx['cashier']);

    // Tidak ada kasir yang bisa ditanya di pesanan mandiri, jadi barang basi
    // tidak tersedia sama sekali — bukan "tersedia dengan konfirmasi".
    expect(fn () => app(TransactionService::class)->createSelfOrder([
        'items' => [expiredSaleLine($ctx['variant'])],
    ]))->toThrow(Exception::class, 'tidak cukup');
});
