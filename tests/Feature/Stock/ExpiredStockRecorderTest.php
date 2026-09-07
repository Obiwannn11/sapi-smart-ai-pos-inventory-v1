<?php

use App\Models\ExpiredStockRecord;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\ExpiredStockRecorder;

use function Pest\Laravel\artisan;
use function Pest\Laravel\assertDatabaseCount;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Pencatat harian barang basi ([BL-105] butir 2).
 *
 * Yang paling penting diuji di sini bukan "apakah ia mencatat", tapi **apakah
 * ia mencatat DUA KALI** — pencatat yang menggandakan barisnya tiap sapuan akan
 * melaporkan kerugian berlipat tanpa satu pun tanda, dan cacatnya baru terlihat
 * berbulan-bulan kemudian saat angkanya sudah dipercaya.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Roti Tawar',
    ]);

    $this->recorder = app(ExpiredStockRecorder::class);
});

function expiringVariant(int $productId, string $expiry, int $stock = 6, int $cost = 4000): ProductVariant
{
    return ProductVariant::factory()->withExpiry($expiry)->create([
        'product_id' => $productId,
        'name' => 'Gandum',
        'stock' => $stock,
        'cost_price' => $cost,
    ]);
}

it('stamps a variant that passed its expiry with stock left', function () {
    $variant = expiringVariant($this->product->id, '2026-09-01', stock: 6, cost: 4000);

    expect($this->recorder->record('2026-09-02'))->toBe(1);

    assertDatabaseHas('expired_stock_records', [
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $variant->id,
        'label' => 'Roti Tawar - Gandum',
        'expiry_date' => '2026-09-01',
        'recorded_on' => '2026-09-02',
        'qty' => 6,
        'value' => 24000,
        'source' => ExpiredStockRecord::SOURCE_RECORDER,
    ]);
});

it('never stamps the same expiry twice', function () {
    expiringVariant($this->product->id, '2026-09-01');

    expect($this->recorder->record('2026-09-02'))->toBe(1);

    // Sapuan berikutnya, dan yang sesudahnya: barangnya masih di rak, masih
    // basi, dan tidak boleh melahirkan baris kedua.
    expect($this->recorder->record('2026-09-03'))->toBe(0)
        ->and($this->recorder->record('2026-09-04'))->toBe(0);

    assertDatabaseCount('expired_stock_records', 1);
});

it('leaves a variant alone on its own expiry date', function () {
    // Tanggal X barangnya masih SAH DIJUAL — potongan near_expiry justru paling
    // dalam hari itu. Ia baru basi pada X+1.
    expiringVariant($this->product->id, '2026-09-02');

    expect($this->recorder->record('2026-09-02'))->toBe(0);

    assertDatabaseCount('expired_stock_records', 0);
});

it('leaves out variants with nothing left on the shelf', function () {
    expiringVariant($this->product->id, '2026-09-01', stock: 0);

    expect($this->recorder->record('2026-09-02'))->toBe(0);
});

it('leaves out variants that have been deleted', function () {
    expiringVariant($this->product->id, '2026-09-01')->delete();

    expect($this->recorder->record('2026-09-02'))->toBe(0);
});

it('stamps again when a restock brings a new expiry date', function () {
    $variant = expiringVariant($this->product->id, '2026-09-01', stock: 6, cost: 4000);

    expect($this->recorder->record('2026-09-02'))->toBe(1);

    // Direstok, tanggal kedaluwarsanya baru. Menyaring per varian saja akan
    // membuat tiap varian hanya bisa basi sekali seumur hidupnya — dan barang
    // yang paling sering basi justru yang paling sering direstok.
    $variant->update(['expiry_date' => '2026-09-05', 'stock' => 10]);

    expect($this->recorder->record('2026-09-06'))->toBe(1);

    assertDatabaseCount('expired_stock_records', 2);
    assertDatabaseHas('expired_stock_records', [
        'product_variant_id' => $variant->id,
        'expiry_date' => '2026-09-05',
        'qty' => 10,
        'value' => 40000,
    ]);
});

it('sweeps every shop in one pass, with no session to lean on', function () {
    expiringVariant($this->product->id, '2026-09-01', stock: 2, cost: 5000);

    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    expiringVariant($otherProduct->id, '2026-09-01', stock: 3, cost: 5000);

    // Dipanggil tanpa siapa pun yang login — TenantScope mati, dan memang
    // harus: ini sapuan lintas tenant, pola yang sama dengan open-bills:expire.
    expect($this->recorder->record('2026-09-02'))->toBe(2);

    assertDatabaseHas('expired_stock_records', ['tenant_id' => $this->tenant->id, 'value' => 10000]);
    assertDatabaseHas('expired_stock_records', ['tenant_id' => $otherTenant->id, 'value' => 15000]);
});

it('counts without writing on a dry run', function () {
    expiringVariant($this->product->id, '2026-09-01');

    expect($this->recorder->record('2026-09-02', dryRun: true))->toBe(1);

    assertDatabaseCount('expired_stock_records', 0);
});

it('runs from the scheduled command', function () {
    expiringVariant($this->product->id, '2026-09-01');

    artisan('stock:record-expired', ['--date' => '2026-09-02'])
        ->expectsOutputToContain('1 varian dicatat')
        ->assertSuccessful();

    assertDatabaseCount('expired_stock_records', 1);
});

it('holds back the recorder on goods that expired before it existed', function () {
    $variant = expiringVariant($this->product->id, '2026-07-13', stock: 18, cost: 10000);

    // Baris penanda yang ditulis migrasi: barangnya memang basi, tapi berapa
    // yang tersisa SAAT ia basi tidak diketahui.
    ExpiredStockRecord::factory()->preExisting()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $variant->id,
        'expiry_date' => '2026-07-13',
    ]);

    // Tanpa penanda itu, sapuan hari ini akan menstempelnya dengan stok HARI INI
    // di bawah tanggal Juli — jumlah karangan di ember periode yang salah.
    expect($this->recorder->record('2026-09-07'))->toBe(0);

    assertDatabaseCount('expired_stock_records', 1);
});

it('keeps unmeasured rows out of the measured scope', function () {
    ExpiredStockRecord::factory()->create(['tenant_id' => $this->tenant->id]);
    ExpiredStockRecord::factory()->preExisting()->create(['tenant_id' => $this->tenant->id]);

    expect(ExpiredStockRecord::query()->count())->toBe(2)
        ->and(ExpiredStockRecord::query()->measured()->count())->toBe(1);
});
