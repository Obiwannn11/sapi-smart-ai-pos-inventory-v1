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

/**
 * Fondasi biaya layanan ([BL-097] Tahap 1) — kolomnya ada dan bawaannya mati.
 *
 * Tidak ada pasangan `serviceChargeLocked()` yang diuji di sini, dan itu
 * bukan yang terlewat: biaya layanan sengaja TIDAK dikunci. Ia pilihan
 * komersial pemilik toko, bukan kewajiban hukum, dan riwayat yang berlubang
 * di sana tidak melanggar apa pun.
 */
test('tenant baru lahir tanpa biaya layanan', function () {
    $tenant = Tenant::factory()->create();

    expect($tenant->service_charge_enabled)->toBeFalse()
        ->and((float) $tenant->service_charge_rate)->toBe(0.0)
        // Bukan 'Biaya Layanan'. Kata yang tercetak di struk tidak ditebak.
        ->and($tenant->service_charge_label)->toBeNull();
});

test('transaksi tanpa biaya layanan menyimpan nol dan konteks kosong', function () {
    $tenant = Tenant::factory()->create();
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $transaction = Transaction::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'code' => 'TRX-SC-1',
        'status' => Transaction::STATUS_COMPLETED,
        'subtotal_amount' => 50000,
        'total_amount' => 50000,
    ]);

    expect((float) $transaction->service_charge_amount)->toBe(0.0)
        ->and($transaction->service_charge_rate)->toBeNull()
        ->and($transaction->service_charge_label)->toBeNull();
});

test('transaksi berbiaya layanan menyimpan keempat angkanya utuh', function () {
    $tenant = Tenant::factory()->create([
        'service_charge_enabled' => true,
        'service_charge_rate' => 5,
        'service_charge_label' => 'Biaya Layanan',
    ]);
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $transaction = Transaction::create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'code' => 'TRX-SC-2',
        'status' => Transaction::STATUS_COMPLETED,
        'subtotal_amount' => 10000,
        'service_charge_amount' => 500,
        'tax_amount' => 1155,
        'total_amount' => 11655,
        'tax_rate' => 11,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_label' => 'PPN',
        'service_charge_rate' => 5,
        'service_charge_label' => 'Biaya Layanan',
    ]);

    $stored = $transaction->fresh();

    expect((float) $stored->subtotal_amount
        + (float) $stored->service_charge_amount
        + (float) $stored->tax_amount)
        ->toBe((float) $stored->total_amount);
});
