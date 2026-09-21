<?php

/**
 * Teks halaman Langganan & Tagihan.
 *
 * Halaman ini dibaca sambil memutuskan pembelian, jadi paragraf penjelasnya
 * sengaja panjang dan dibiarkan apa adanya. Yang dijaga hanya satu hal: nama
 * barang yang dibeli.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `PlatformCopyTest`.
 */
function billingPageSource(): string
{
    return file_get_contents(resource_path('js/Pages/Billing/Show.vue'));
}

test('panel kapasitas menamai yang dibeli "pengguna", bukan "kursi"', function (string $phrase) {
    // Tagihannya berbunyi "Tambah pengguna" dan konsol platform menulis
    // "Batas pengguna"; kalimat penjelas di sini sempat memakai "kursi".
    expect(billingPageSource())->not->toContain($phrase);
})->with([
    'Kursi Anda sudah penuh',
    'Lepas kursi',
    'per kursi',
    '/kursi/bulan',
    'kursi tambahan yang Anda beli',
    'Penambahan kursi',
    'Pelepasan kursi',
    '}} kursi sekarang',
    '}} kursi',
]);

test('panel kapasitas memakai kata yang sama dengan tagihannya', function () {
    $source = billingPageSource();

    expect($source)->toContain('Jatah pengguna Anda sudah penuh.')
        ->and($source)->toContain('Lepas pengguna')
        ->and($source)->toContain('{{ subscription.seats }} pengguna')
        // Baris tagihan yang terbit untuk penambahan.
        ->and($source)->toContain("'Tambah pengguna' : 'Langganan'");
});
