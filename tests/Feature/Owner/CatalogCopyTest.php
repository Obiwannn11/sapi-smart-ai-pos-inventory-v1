<?php

/**
 * Teks halaman Stok, Produk, dan Aturan Diskon.
 *
 * Yang dijaga: istilah kolom basis data tidak bocor ke layar pemilik
 * ("adjustment", "expired"), dan dialog hapus menyebut AKIBATNYA alih-alih
 * bertanya "apakah Anda yakin" di bawah judul yang sudah bertanya.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `CashierPagesCopyTest`.
 */
function catalogPageSource(string $relative): string
{
    return file_get_contents(resource_path("js/{$relative}"));
}

test('halaman stok memakai "koreksi", bukan "adjustment"', function (string $relative) {
    $source = catalogPageSource($relative);

    expect($source)->not->toContain('Adjustment')
        ->and($source)->not->toContain('adjustment)')
        ->and($source)->not->toContain('adjustment, dan pantau');
})->with([
    'Pages/Owner/Stock/Index.vue',
    'Pages/Owner/Stock/History.vue',
    'Pages/Owner/Stock/Movements.vue',
]);

test('formulir produk memakai kata kedaluwarsa yang sama dengan halaman stok', function (string $relative) {
    expect(catalogPageSource($relative))->not->toContain('Tanggal Expired')
        ->and(catalogPageSource($relative))->toContain('Tanggal Kedaluwarsa');
})->with([
    'Pages/Owner/Products/Form.vue',
    'Components/VariantFormModal.vue',
]);

test('dialog hapus tidak bertanya dua kali', function (string $relative) {
    // Judulnya sudah bertanya; pesannya dipakai untuk menyebut akibat.
    expect(catalogPageSource($relative))->not->toContain('Apakah Anda yakin');
})->with([
    'Pages/Owner/Products/Index.vue',
    'Pages/Owner/Modifiers/Index.vue',
    'Pages/Owner/Categories/Index.vue',
    'Pages/Owner/PaymentMethods/Index.vue',
    'Components/ConfirmDialog.vue',
]);

test('grup modifier memakai satu nama di kedua layarnya', function () {
    expect(catalogPageSource('Pages/Owner/Modifiers/Index.vue'))->toContain('>Grup Modifier</h1>')
        ->and(catalogPageSource('Pages/Owner/Products/Form.vue'))->toContain('>Grup Modifier</h2>')
        ->and(catalogPageSource('Pages/Owner/Products/Form.vue'))->not->toContain('modifier group yang tersedia');
});

test('aturan diskon tidak memakai kiasan teknis atau kata "diam"', function (string $phrase) {
    expect(catalogPageSource('Pages/Owner/DiscountRules/Index.vue'))->not->toContain($phrase);
})->with([
    'mempersenjatai',
    '}} diam',
    'Tetap — alasan saya sendiri',
    'belum diisi — potongan',
]);
