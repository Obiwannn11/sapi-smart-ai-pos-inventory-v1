<?php

use App\Models\Tenant;
use App\Services\TaxCalculator;

/**
 * Aritmetika pajak ([BL-065] Langkah 2).
 *
 * Yang diuji paling keras di sini bukan angkanya melainkan INVARIANNYA:
 * `subtotal + pajak = total`, tepat, di kedua mode, termasuk pada angka yang
 * memaksa pembulatan. Struk yang tidak bisa dijumlahkan ulang oleh pelanggan
 * yang berdiri di depan kasir adalah keluhan yang paling cepat datang.
 */
function taxContext(string $mode, float $rate): array
{
    return ['enabled' => true, 'mode' => $mode, 'rate' => $rate, 'label' => 'PPN'];
}

test('pajak mati membiarkan angkanya apa adanya', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(50000, $calculator->noTax());

    expect($result['subtotal'])->toBe(50000.0)
        ->and($result['tax'])->toBe(0.0)
        ->and($result['total'])->toBe(50000.0);
});

test('tarif nol diperlakukan sama seperti pajak mati', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(50000, taxContext(Tenant::TAX_MODE_EXCLUSIVE, 0));

    expect($result['tax'])->toBe(0.0)
        ->and($result['total'])->toBe(50000.0);
});

test('mode exclusive menambahkan pajak di atas harga', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(10000, taxContext(Tenant::TAX_MODE_EXCLUSIVE, 11));

    // Pendapatan toko tidak berubah; yang dibayar pelanggan yang naik.
    expect($result['subtotal'])->toBe(10000.0)
        ->and($result['tax'])->toBe(1100.0)
        ->and($result['total'])->toBe(11100.0);
});

test('mode inclusive mengurai pajak dari dalam harga', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(10000, taxContext(Tenant::TAX_MODE_INCLUSIVE, 11));

    // Yang dibayar pelanggan TIDAK berubah; pendapatan toko yang turun.
    expect($result['total'])->toBe(10000.0)
        // 10000 x 11/111 = 990,99 -> 991. Kalau rumusnya keliru memakai
        // rate/100, angka ini jadi 1100 dan tokonya menyetor lebih banyak
        // dari yang terutang.
        ->and($result['tax'])->toBe(991.0)
        ->and($result['subtotal'])->toBe(9009.0);
});

test('subtotal ditambah pajak selalu sama dengan total', function (float $base, string $mode, float $rate) {
    $calculator = new TaxCalculator;

    $result = $calculator->apply($base, taxContext($mode, $rate));

    expect($result['subtotal'] + $result['tax'])->toBe($result['total']);
})->with([
    // Angka yang sengaja dipilih karena memaksa pembulatan.
    [13500, Tenant::TAX_MODE_EXCLUSIVE, 11],
    [13500, Tenant::TAX_MODE_INCLUSIVE, 11],
    [9999, Tenant::TAX_MODE_EXCLUSIVE, 11],
    [9999, Tenant::TAX_MODE_INCLUSIVE, 11],
    [33333, Tenant::TAX_MODE_EXCLUSIVE, 10],
    [33333, Tenant::TAX_MODE_INCLUSIVE, 10],
    [7777, Tenant::TAX_MODE_EXCLUSIVE, 12],
    [7777, Tenant::TAX_MODE_INCLUSIVE, 12],
    [1, Tenant::TAX_MODE_INCLUSIVE, 11],
    [0, Tenant::TAX_MODE_EXCLUSIVE, 11],
]);

test('pajak dibulatkan ke rupiah penuh', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(13500, taxContext(Tenant::TAX_MODE_EXCLUSIVE, 11));

    expect($result['tax'])->toBe(1485.0)
        ->and(fmod($result['tax'], 1))->toBe(0.0);
});

test('mode inclusive pada tarif tinggi tetap menjaga invariannya', function () {
    $calculator = new TaxCalculator;

    // PBJT daerah bisa mencapai 10%, dan sebagian Perda memakai angka lain;
    // rumusnya tidak boleh bergantung pada tarif tertentu.
    $result = $calculator->apply(55555, taxContext(Tenant::TAX_MODE_INCLUSIVE, 10));

    expect($result['total'])->toBe(55555.0)
        ->and($result['subtotal'] + $result['tax'])->toBe(55555.0);
});

test('kolom transaksi ikut membekukan konteksnya', function () {
    $calculator = new TaxCalculator;

    $columns = $calculator->columnsFor(10000, taxContext(Tenant::TAX_MODE_EXCLUSIVE, 11));

    expect($columns['subtotal_amount'])->toBe(10000.0)
        ->and($columns['tax_amount'])->toBe(1100.0)
        ->and($columns['total_amount'])->toBe(11100.0)
        ->and($columns['tax_rate'])->toBe(11.0)
        ->and($columns['tax_mode'])->toBe(Tenant::TAX_MODE_EXCLUSIVE)
        ->and($columns['tax_label'])->toBe('PPN');
});

test('penjualan tanpa pajak tidak membekukan konteks apa pun', function () {
    $calculator = new TaxCalculator;

    $columns = $calculator->columnsFor(10000, $calculator->noTax());

    // NULL, bukan 0 — supaya laporan bisa membedakan "lahir sebelum pajak
    // ada" dari "dipajaki nol persen", dan supaya taxLocked() tidak mengunci
    // tenant yang belum pernah memungut apa pun.
    expect($columns['tax_rate'])->toBeNull()
        ->and($columns['tax_mode'])->toBeNull()
        ->and($columns['tax_label'])->toBeNull()
        ->and($columns['subtotal_amount'])->toBe(10000.0)
        ->and($columns['total_amount'])->toBe(10000.0);
});
