<?php

/**
 * Teks bagian atas dashboard owner: langganan, metrik, dan Penyelamat Stok.
 *
 * Yang dijaga: kalimat tanpa tanda pisah, tanpa pengulangan ("tidak ada yang
 * perlu diajukan" setelah "sudah memakai"), dan tanpa kalimat penutup yang
 * tidak menambah fakta ("jadi pindah jalur belum menguntungkan Anda").
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `CashierCopyTest`.
 */
function ownerDashboardCopySource(): string
{
    return file_get_contents(resource_path('js/Pages/Owner/Dashboard.vue'));
}

test('teks langganan dan metrik dashboard sudah dipadatkan', function (string $phrase) {
    expect(ownerDashboardCopySource())->not->toContain($phrase);
})->with([
    'Ringkasan bisnis Anda hari ini',
    'bukan yang sedang berjalan.',
    'dibuka kepada kami',
    'jadi pindah jalur belum menguntungkan Anda',
    'Jalur yang berlaku bagi Anda adalah paket berbayar penuh',
    'tidak ada yang perlu diajukan',
    'Berlaku sendiri bila Anda tidak memilih apa pun',
    'modal sedang tertekan',
    'saran diambil kasir',
]);

test('saran barang tertekan dihitung sebagai keputusan pelanggan', function () {
    // Tombol kasir berbunyi "Diterima": yang menerima pelanggan, bukan kasir.
    expect(ownerDashboardCopySource())->toContain('saran diterima pelanggan');
});
