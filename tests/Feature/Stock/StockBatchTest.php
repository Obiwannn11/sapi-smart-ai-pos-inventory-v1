<?php

use App\Models\Product;
use App\Models\ProductStockBatch;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\ExpiredStockRecorder;
use App\Services\StockService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertDatabaseHas;

/**
 * Buku batch stok ([BL-111]).
 *
 * Berkas ini dulu bernama `StockBatchGapTest` dan membuktikan CELAHNYA: restock
 * kedua menimpa tanggal kedaluwarsa batch pertama, dan penjualan tidak tahu
 * batch mana yang berkurang. Sesuai pesan yang ditinggalkannya, ia ditulis ulang
 * untuk memverifikasi model batch yang menutup celah itu — dua skenario aslinya
 * tetap jadi dua uji pertama di sini, dengan jawaban yang sekarang benar.
 *
 * Hari toko dibekukan di 15 Mei 2026, jadi tanggal April sudah basi dan tanggal
 * Juni ke atas masih baik.
 */
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-05-15 10:00:00'));

    $this->stock = app(StockService::class);
});

function batchLedgerVariant(array $attributes = []): ProductVariant
{
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Croissant']);

    return ProductVariant::factory()->create(array_merge([
        'product_id' => $product->id,
        'name' => 'Plain',
        'stock' => 0,
        'expiry_date' => null,
        'cost_price' => 1000,
    ], $attributes));
}

function batchLedgerSale(ProductVariant $variant): int
{
    $tenantId = $variant->product->tenant_id;

    return Transaction::factory()->create([
        'tenant_id' => $tenantId,
        'user_id' => User::factory()->create(['tenant_id' => $tenantId])->id,
    ])->id;
}

/**
 * Isi rak per batch yang masih bersisa, urut jual: [[tanggal, sisa], ...].
 *
 * @return list<array{0: string|null, 1: int}>
 */
function batchLedgerShelf(ProductVariant $variant): array
{
    return ProductStockBatch::where('product_variant_id', $variant->id)
        ->remaining()
        ->orderByRaw('CASE WHEN expiry_date IS NULL THEN 1 ELSE 0 END')
        ->orderBy('expiry_date')
        ->orderBy('id')
        ->get()
        ->map(fn (ProductStockBatch $batch) => [$batch->expiry_date?->toDateString(), $batch->qty_remaining])
        ->all();
}

test('restock kedua menyimpan tanggalnya sendiri dan tidak menimpa batch pertama', function () {
    $variant = batchLedgerVariant();

    $this->stock->restock($variant, 20, 'Kedatangan batch 1', '2026-06-10');
    $this->stock->restock($variant, 30, 'Kedatangan batch 2', '2026-08-10');

    $fresh = $variant->fresh();

    expect($fresh->stock)->toBe(50)
        ->and(batchLedgerShelf($fresh))->toBe([['2026-06-10', 20], ['2026-08-10', 30]])
        // Tanggal di varian kini turunan: yang paling cepat basi di antara yang
        // tersisa, bukan yang terakhir datang.
        ->and($fresh->expiry_date->toDateString())->toBe('2026-06-10');
});

test('penjualan mengambil batch yang paling cepat basi lebih dulu, lintas batch bila perlu', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 20, null, '2026-06-10');
    $this->stock->restock($variant, 30, null, '2026-08-10');

    $this->stock->deduct($variant->fresh(), 15, batchLedgerSale($variant));

    expect(batchLedgerShelf($variant))->toBe([['2026-06-10', 5], ['2026-08-10', 30]]);

    $this->stock->deduct($variant->fresh(), 10, batchLedgerSale($variant));

    $fresh = $variant->fresh();

    expect($fresh->stock)->toBe(25)
        ->and(batchLedgerShelf($fresh))->toBe([['2026-08-10', 25]])
        ->and($fresh->expiry_date->toDateString())->toBe('2026-08-10');
});

test('batch tanpa tanggal kedaluwarsa dijual paling akhir', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 10);
    $this->stock->restock($variant, 10, null, '2026-07-01');

    $this->stock->deduct($variant->fresh(), 12, batchLedgerSale($variant));

    expect(batchLedgerShelf($variant))->toBe([[null, 8]]);
});

test('barang yang sudah basi tidak ikut terjual tanpa konfirmasi', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 20, null, '2026-04-10');
    $this->stock->restock($variant, 30, null, '2026-08-10');

    // Yang baik cukup: terjual tanpa ditanya, dan yang basi tidak tersentuh.
    $taken = $this->stock->deduct($variant->fresh(), 30, batchLedgerSale($variant));

    expect($taken['expired_qty'])->toBe(0)
        ->and(batchLedgerShelf($variant))->toBe([['2026-04-10', 20]]);

    // Yang baik sudah habis: sisa yang basi tidak boleh keluar diam-diam.
    expect(fn () => $this->stock->deduct($variant->fresh(), 1, batchLedgerSale($variant)))
        ->toThrow(Exception::class, 'Isi alasan untuk tetap menjual');

    expect($variant->fresh()->stock)->toBe(20)
        ->and(batchLedgerShelf($variant))->toBe([['2026-04-10', 20]]);
});

test('dengan konfirmasi, kekurangan barang baik diambil dari batch basi dan dilaporkan', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 20, null, '2026-04-10');
    $this->stock->restock($variant, 30, null, '2026-08-10');

    $taken = $this->stock->deduct($variant->fresh(), 35, batchLedgerSale($variant), allowExpired: true);

    expect($taken)->toBe(['expired_qty' => 5, 'earliest_expired' => '2026-04-10'])
        ->and(batchLedgerShelf($variant))->toBe([['2026-04-10', 15]]);
});

test('void mengembalikan unit ke batch asalnya, termasuk ke batch basi', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 20, null, '2026-04-10');
    $this->stock->restock($variant, 30, null, '2026-08-10');
    $sale = batchLedgerSale($variant);

    $this->stock->deduct($variant->fresh(), 35, $sale, allowExpired: true);
    $this->stock->restore($variant->fresh(), 35, $sale);

    // Tanpa jejak batch, 5 unit basi itu akan kembali sebagai stok tanpa
    // tanggal — lalu dijual lagi tanpa ditanya.
    expect($variant->fresh()->stock)->toBe(50)
        ->and(batchLedgerShelf($variant))->toBe([['2026-04-10', 20], ['2026-08-10', 30]]);
});

test('koreksi turun membuang batch basi lebih dulu', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 20, null, '2026-04-10');
    $this->stock->restock($variant, 30, null, '2026-08-10');

    $this->stock->adjust($variant->fresh(), -20, 'Buang croissant basi');

    $fresh = $variant->fresh();

    expect(batchLedgerShelf($fresh))->toBe([['2026-08-10', 30]])
        ->and($fresh->expiry_date->toDateString())->toBe('2026-08-10');
});

test('koreksi naik menumpang batch yang terakhir datang', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 20, null, '2026-06-10');
    $this->stock->restock($variant, 30, null, '2026-08-10');

    $this->stock->adjust($variant->fresh(), 3, 'Temuan hitung ulang');

    expect(batchLedgerShelf($variant))->toBe([['2026-06-10', 20], ['2026-08-10', 33]]);
});

test('restock sesudah stok minus karena penjualan offline menelan selisihnya', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 5, null, '2026-06-01');

    $locked = ProductVariant::lockForUpdate()->find($variant->id);
    $this->stock->deductOffline($locked, 8, batchLedgerSale($variant), $variant->product->tenant_id, '2026-05-15');

    expect($variant->fresh()->stock)->toBe(-3)
        ->and(batchLedgerShelf($variant))->toBe([]);

    $this->stock->restock($variant->fresh(), 10, null, '2026-09-01');

    // Tiga unit kiriman baru sudah terjual sebelum catatannya datang.
    expect($variant->fresh()->stock)->toBe(7)
        ->and(batchLedgerShelf($variant))->toBe([['2026-09-01', 7]]);
});

test('varian baru dengan stok awal langsung punya batch pembuka', function () {
    $variant = batchLedgerVariant(['stock' => 12, 'expiry_date' => '2026-06-01']);

    assertDatabaseHas('product_stock_batches', [
        'product_variant_id' => $variant->id,
        'qty_received' => 12,
        'qty_remaining' => 12,
        'source' => ProductStockBatch::SOURCE_OPENING,
    ]);

    expect(batchLedgerShelf($variant))->toBe([['2026-06-01', 12]]);
});

test('stok yang ditulis tanpa StockService disusulkan sebelum mutasi berikutnya', function () {
    $variant = batchLedgerVariant();

    // Penulis yang melewati pintu: seeder, perintah manual, kode lama.
    ProductVariant::whereKey($variant->id)->update(['stock' => 12, 'expiry_date' => '2026-07-01']);

    $this->stock->restock($variant->fresh(), 5, null, '2026-09-01');

    expect(batchLedgerShelf($variant))->toBe([['2026-07-01', 12], ['2026-09-01', 5]]);

    assertDatabaseHas('product_stock_batches', [
        'product_variant_id' => $variant->id,
        'qty_remaining' => 12,
        'source' => ProductStockBatch::SOURCE_RECONCILE,
    ]);
});

test('pencatat harian menstempel tiap tanggal kedaluwarsa dengan unitnya sendiri', function () {
    $variant = batchLedgerVariant(['cost_price' => 4000]);
    $this->stock->restock($variant, 20, null, '2026-05-01');
    $this->stock->restock($variant, 30, null, '2026-05-10');
    $this->stock->restock($variant, 50, null, '2026-08-10');

    // Sebelum batch ada, varian ini tidak tercatat basi sama sekali sampai
    // Agustus — lalu tercatat 100 unit sekaligus.
    expect(app(ExpiredStockRecorder::class)->record('2026-05-15'))->toBe(2);

    assertDatabaseHas('expired_stock_records', [
        'product_variant_id' => $variant->id,
        'expiry_date' => '2026-05-01',
        'qty' => 20,
        'value' => 80000,
    ]);
    assertDatabaseHas('expired_stock_records', [
        'product_variant_id' => $variant->id,
        'expiry_date' => '2026-05-10',
        'qty' => 30,
        'value' => 120000,
    ]);
});

test('formulir varian yang mengubah tanggal memberi tanggal baru pada batch yang ditampilkannya', function () {
    $variant = batchLedgerVariant();
    $this->stock->restock($variant, 20, null, '2026-06-10');
    $this->stock->restock($variant, 30, null, '2026-08-10');

    $owner = User::factory()->create([
        'tenant_id' => $variant->product->tenant_id,
        'role' => 'owner',
    ]);

    $fresh = $variant->fresh();

    actingAs($owner)
        ->put(route('owner.products.variants.update', [$fresh->product_id, $fresh->id]), [
            'name' => $fresh->name,
            'sku' => $fresh->sku,
            'price' => $fresh->price,
            'cost_price' => $fresh->cost_price,
            'stock' => 50,
            'expiry_date' => '2026-06-20',
        ])
        ->assertSessionHas('success');

    expect(batchLedgerShelf($variant))->toBe([['2026-06-20', 20], ['2026-08-10', 30]]);
});
