<?php

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;

/**
 * Fondasi pajak ([BL-065] Langkah 1) — kolomnya ada, bawaannya mati, dan
 * penguncian tahu kapan ia mengunci.
 *
 * Belum ada perilaku pajak apa pun di sini: yang diuji cuma bahwa tenant
 * lahir tanpa pajak dan bahwa `taxLocked()` menjawab dari transaksi, bukan
 * dari sakelarnya.
 */
test('tenant baru lahir tanpa pajak', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->tax_enabled)->toBeFalse()
        ->and($tenant->tax_mode)->toBe(Tenant::TAX_MODE_EXCLUSIVE)
        ->and((float) $tenant->tax_rate)->toBe(0.0)
        // Bukan 'PPN'. Label yang tercetak di struk tidak pernah ditebak.
        ->and($tenant->tax_label)->toBeNull();
});

test('transaksi tanpa pajak menyimpan subtotal sama dengan total', function () {
    $tenant = Tenant::factory()->create();
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $transaction = Transaction::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'code' => 'TRX-TAX-1',
        'status' => Transaction::STATUS_COMPLETED,
        'subtotal_amount' => 50000,
        'total_amount' => 50000,
    ]);

    expect((float) $transaction->subtotal_amount)->toBe(50000.0)
        ->and((float) $transaction->tax_amount)->toBe(0.0)
        // Konteks NULL berarti "lahir sebelum pajak ada" — berbeda artinya
        // dari "pajaknya nol persen".
        ->and($transaction->tax_mode)->toBeNull()
        ->and($transaction->tax_rate)->toBeNull()
        ->and($transaction->tax_label)->toBeNull();
});

test('penguncian menunggu transaksi berpajak, bukan sakelarnya', function () {
    $tenant = Tenant::factory()->create();
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    // Menyalakan pajak saja TIDAK mengunci: tenant yang berubah pikiran
    // sebelum menjual apa pun harus tetap bisa membatalkannya.
    $tenant->update([
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => 'PPN',
    ]);

    expect($tenant->taxLocked())->toBeFalse();

    Transaction::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'code' => 'TRX-TAX-2',
        'status' => Transaction::STATUS_COMPLETED,
        'subtotal_amount' => 50000,
        'tax_amount' => 5500,
        'total_amount' => 55500,
        'tax_rate' => 11,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_label' => 'PPN',
    ]);

    expect($tenant->fresh()->taxLocked())->toBeTrue();
});

test('transaksi tanpa konteks pajak tidak mengunci apa pun', function () {
    $tenant = Tenant::factory()->create();
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    Transaction::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'code' => 'TRX-TAX-3',
        'status' => Transaction::STATUS_COMPLETED,
        'subtotal_amount' => 20000,
        'total_amount' => 20000,
    ]);

    expect($tenant->taxLocked())->toBeFalse();
});
