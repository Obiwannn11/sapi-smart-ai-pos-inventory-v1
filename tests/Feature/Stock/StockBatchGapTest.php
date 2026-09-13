<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Services\StockService;

/**
 * Penjaga celah `[BL-111]` — satu varian tidak bisa punya lebih dari satu
 * tanggal kedaluwarsa, karena `stock` dan `expiry_date` adalah kolom TUNGGAL
 * di `product_variants`, bukan baris per kedatangan barang (batch).
 *
 * Uji ini SENGAJA membuktikan perilaku SEKARANG (yang salah untuk kasus
 * multi-batch), bukan perilaku yang diinginkan. Begitu `[BL-111]` dikerjakan
 * (tabel `product_stock_batches` + FEFO di `StockService`), uji ini akan
 * gagal — itu tandanya harus DITULIS ULANG untuk memverifikasi perilaku
 * batch yang baru, bukan tanda ada regresi.
 */
function makeBatchGapVariant(): ProductVariant
{
    $tenant = Tenant::factory()->create();
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 0,
        'expiry_date' => null,
    ]);
}

test('restock kedua menimpa tanggal kedaluwarsa batch pertama tanpa jejak', function () {
    $variant = makeBatchGapVariant();
    $service = app(StockService::class);

    // Kedatangan pertama: 20 unit, kedaluwarsa 2026-04-10 (bulan 1).
    $service->restock($variant, 20, 'Kedatangan batch 1', '2026-04-10');

    expect($variant->fresh())
        ->stock->toBe(20)
        ->expiry_date->not->toBeNull();

    // Kedatangan kedua: 30 unit, kedaluwarsa 2026-08-10 (bulan 5).
    $service->restock($variant, 30, 'Kedatangan batch 2', '2026-08-10');

    $fresh = $variant->fresh();

    // Total stok memang benar dijumlahkan ...
    expect($fresh->stock)->toBe(50);

    // ... tapi tanggal kedaluwarsa batch PERTAMA (2026-04-10) sudah HILANG.
    // Satu-satunya tanggal yang tersisa di database adalah batch kedua.
    // Tidak ada satu pun kolom atau tabel yang menyimpan bahwa 20 dari 50
    // unit itu sudah kedaluwarsa 2026-04-10, bulan sebelum batch kedua tiba.
    expect($fresh->expiry_date->toDateString())->toBe('2026-08-10', implode(' ', [
        'Kalau assert ini gagal karena tanggal batch pertama (2026-04-10)',
        'masih tersimpan di suatu tempat, [BL-111] mungkin sudah dikerjakan',
        'sebagian — tulis ulang uji ini untuk memverifikasi model batch yang',
        'baru, jangan hanya menghapusnya.',
    ]));
});

test('pengurangan stok saat penjualan tidak tahu batch mana yang berkurang', function () {
    $variant = makeBatchGapVariant();
    $service = app(StockService::class);

    // Dua batch dengan tanggal kedaluwarsa berbeda digabung jadi satu angka.
    $service->restock($variant, 20, 'Batch lama, akan basi duluan', '2026-04-10');
    $service->restock($variant, 30, 'Batch baru, masih lama', '2026-08-10');

    expect($variant->fresh()->stock)->toBe(50);

    // Jual 15 unit. Idealnya (FEFO) ini mengurangi batch 2026-04-10 lebih
    // dulu karena ia akan basi duluan. Tapi StockService::deduct() hanya
    // mengurangi kolom `stock` tunggal — tidak ada cara memverifikasi dari
    // batch mana 15 unit itu diambil, karena batch itu sendiri tidak
    // direpresentasikan di database.
    $service->deduct($variant->fresh(), 15, transactionId: 1);

    $fresh = $variant->fresh();

    expect($fresh->stock)->toBe(35);

    // `expiry_date` tetap menunjuk batch KEDUA (2026-08-10) — sistem tidak
    // pernah "tahu" batch mana yang baru saja berkurang, sehingga tidak bisa
    // menegakkan FEFO maupun melaporkan sisa batch pertama yang sebenarnya
    // sudah lebih dekat kedaluwarsa.
    expect($fresh->expiry_date->toDateString())->toBe('2026-08-10');
});
