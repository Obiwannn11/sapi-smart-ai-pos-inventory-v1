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

/**
 * Biaya layanan ([BL-097] Tahap 1).
 *
 * Yang diuji paling keras tetap invariannya, sekarang berisi empat angka:
 * `subtotal + biaya layanan + pajak = total`. Dua angka dibulatkan dan satu
 * diturunkan, jadi kesempatan meleset justru bertambah — dan struk yang tidak
 * bisa dijumlahkan ulang oleh pelanggan tetap keluhan yang paling cepat datang.
 */
function serviceContext(float $rate): array
{
    return ['enabled' => true, 'rate' => $rate, 'label' => 'Biaya Layanan'];
}

test('biaya layanan mati tidak mengubah satu angka pun', function () {
    $calculator = new TaxCalculator;

    $withoutArgument = $calculator->apply(10000, taxContext(Tenant::TAX_MODE_EXCLUSIVE, 11));
    $withEmptyContext = $calculator->apply(10000, taxContext(Tenant::TAX_MODE_EXCLUSIVE, 11), $calculator->noServiceCharge());

    // Menambahkan argumen ketiga TIDAK boleh menggeser hasil lama walau satu
    // rupiah: transaksi yang sudah tercatat dihitung ulang lewat jalur ini.
    expect($withoutArgument)->toBe($withEmptyContext)
        ->and($withoutArgument['service_charge'])->toBe(0.0)
        ->and($withoutArgument['subtotal'])->toBe(10000.0)
        ->and($withoutArgument['total'])->toBe(11100.0);
});

test('biaya layanan tanpa pajak hanya menambah di atas subtotal', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(10000, $calculator->noTax(), serviceContext(5));

    expect($result['subtotal'])->toBe(10000.0)
        ->and($result['service_charge'])->toBe(500.0)
        ->and($result['tax'])->toBe(0.0)
        ->and($result['total'])->toBe(10500.0);
});

test('mode exclusive memungut pajak atas subtotal DITAMBAH biaya layanan', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(10000, taxContext(Tenant::TAX_MODE_EXCLUSIVE, 11), serviceContext(5));

    // 10000 + 500 = 10500 adalah dasar pengenaan pajaknya, bukan 10000.
    // Kalau pajak dipungut atas 10000 saja, angkanya 1100 — tenant menyetor
    // 55 rupiah lebih sedikit dari yang terutang, tiap transaksi.
    expect($result['subtotal'])->toBe(10000.0)
        ->and($result['service_charge'])->toBe(500.0)
        ->and($result['tax'])->toBe(1155.0)
        ->and($result['total'])->toBe(11655.0);
});

test('mode inclusive mengurai pajak dari harga katalog ditambah biaya layanan', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(10000, taxContext(Tenant::TAX_MODE_INCLUSIVE, 11), serviceContext(5));

    // Yang dibayar pelanggan = harga katalog + biaya layanan, dan pajak
    // diurai dari dalam angka itu: 10500 x 11/111 = 1040,54 -> 1041.
    expect($result['total'])->toBe(10500.0)
        ->and($result['service_charge'])->toBe(500.0)
        ->and($result['tax'])->toBe(1041.0)
        ->and($result['subtotal'])->toBe(8959.0);
});

test('subtotal ditambah biaya layanan ditambah pajak selalu sama dengan total', function (float $base, string $mode, float $rate, float $serviceRate) {
    $calculator = new TaxCalculator;

    $result = $calculator->apply($base, taxContext($mode, $rate), serviceContext($serviceRate));

    expect($result['subtotal'] + $result['service_charge'] + $result['tax'])->toBe($result['total']);
})->with([
    // Angka yang sengaja dipilih karena memaksa KEDUA pembulatan.
    [13500, Tenant::TAX_MODE_EXCLUSIVE, 11, 5],
    [13500, Tenant::TAX_MODE_INCLUSIVE, 11, 5],
    [9999, Tenant::TAX_MODE_EXCLUSIVE, 11, 7.5],
    [9999, Tenant::TAX_MODE_INCLUSIVE, 11, 7.5],
    [33333, Tenant::TAX_MODE_EXCLUSIVE, 10, 5],
    [33333, Tenant::TAX_MODE_INCLUSIVE, 10, 5],
    [7777, Tenant::TAX_MODE_EXCLUSIVE, 12, 3.33],
    [7777, Tenant::TAX_MODE_INCLUSIVE, 12, 3.33],
    // Angka sekecil ini membulatkan biaya layanannya ke nol; invariannya
    // tetap harus berdiri.
    [1, Tenant::TAX_MODE_INCLUSIVE, 11, 5],
    [0, Tenant::TAX_MODE_EXCLUSIVE, 11, 5],
]);

test('biaya layanan dibulatkan ke rupiah penuh', function () {
    $calculator = new TaxCalculator;

    $result = $calculator->apply(13333, $calculator->noTax(), serviceContext(7.5));

    // 13333 x 7,5% = 999,975 -> 1000
    expect($result['service_charge'])->toBe(1000.0)
        ->and(fmod($result['service_charge'], 1))->toBe(0.0);
});

test('kolom transaksi ikut membekukan konteks biaya layanan', function () {
    $calculator = new TaxCalculator;

    $columns = $calculator->columnsFor(
        10000,
        taxContext(Tenant::TAX_MODE_EXCLUSIVE, 11),
        serviceContext(5),
    );

    expect($columns['service_charge_amount'])->toBe(500.0)
        ->and($columns['service_charge_rate'])->toBe(5.0)
        ->and($columns['service_charge_label'])->toBe('Biaya Layanan')
        ->and($columns['total_amount'])->toBe(11655.0);
});

test('penjualan tanpa biaya layanan tidak membekukan tarifnya', function () {
    $calculator = new TaxCalculator;

    $columns = $calculator->columnsFor(10000, $calculator->noTax(), $calculator->noServiceCharge());

    // NULL, bukan 0 — supaya laporan bisa membedakan "lahir sebelum biaya
    // layanan ada" dari "dipungut nol persen", persis seperti pajak.
    expect($columns['service_charge_rate'])->toBeNull()
        ->and($columns['service_charge_label'])->toBeNull()
        ->and($columns['service_charge_amount'])->toBe(0.0);
});
