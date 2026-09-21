<?php

/**
 * Teks Riwayat Sesi Kas dan Koreksi Transaksi Offline.
 *
 * Kedua halaman ini membaca pekerjaan kasir, jadi namanya harus sama dengan
 * yang dilihat kasir di layarnya sendiri.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `CatalogCopyTest`.
 */
function cashSessionPageSource(): string
{
    return file_get_contents(resource_path('js/Pages/Owner/CashDrawers/Index.vue'));
}

function offlineReviewSources(): array
{
    return [
        'halaman' => file_get_contents(resource_path('js/Pages/Owner/OfflineReview/Index.vue')),
        'controller' => file_get_contents(app_path('Http/Controllers/Owner/OfflineReviewController.php')),
    ];
}

test('riwayat sesi kas memakai nama angka yang sama dengan layar kasir', function () {
    $source = cashSessionPageSource();

    expect($source)->toContain('>Seharusnya</th>')
        ->and($source)->toContain('>Uang fisik</th>')
        // Layar Tutup Kas dan Rekap Kas milik kasir memakai kedua nama itu.
        ->and($source)->not->toContain('>Expected</th>');
});

test('lencana status sesi kas berbahasa Indonesia', function () {
    expect(cashSessionPageSource())->not->toContain("'Closed' : 'Open'")
        ->and(cashSessionPageSource())->toContain("'Ditutup' : 'Berjalan'");
});

test('kata "anomali" tidak sampai ke layar pemilik', function () {
    // Di komentar kode ia tetap boleh; yang dijaga hanya kalimat yang tampil.
    expect(offlineReviewSources()['halaman'])->not->toContain('anomali')
        ->and(offlineReviewSources()['controller'])->not->toContain("'Anomali sudah tidak terdeteksi")
        ->and(offlineReviewSources()['controller'])->toContain('Selisihnya sudah tidak ada');
});

test('alasan koreksi dipecah, bukan disambung tanda pisah', function () {
    // Daftar alasan dibaca sebagai daftar periksa, satu baris per temuan.
    $controller = offlineReviewSources()['controller'];

    expect($controller)->toContain('perlu opname fisik.')
        ->and($controller)->not->toContain('— perlu opname fisik')
        ->and($controller)->not->toContain('— periksa harga yang ditagih');
});
