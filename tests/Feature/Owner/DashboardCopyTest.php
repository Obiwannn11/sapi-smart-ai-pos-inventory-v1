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

test('bagian bawah dashboard memakai nama yang sama dengan sidebar', function () {
    $source = ownerDashboardCopySource();

    expect($source)->toContain('<h3 class="text-sm font-semibold text-gray-700">Perlu Perhatian</h3>')
        ->and($source)->not->toContain('Alert & Notifikasi')
        ->and($source)->not->toContain('kartu</span>')
        // Daftar ini 5 transaksi selesai terakhir dari tanggal mana pun.
        ->and($source)->not->toContain('Belum ada transaksi hari ini')
        ->and($source)->not->toContain('Riwayat Kas')
        ->and($source)->not->toContain('Kelola Stok')
        ->and(file_get_contents(resource_path('js/Components/DailyChart.vue')))->toContain('Omzet 7 Hari Terakhir');
});

test('kartu perlu perhatian tidak mengulang angka chipnya dan memakai satu istilah', function () {
    $source = file_get_contents(app_path('Services/BadgeHelperService.php'));

    expect($source)->not->toContain("'Sudah Expired'")
        ->and($source)->not->toContain("'Mendekati Expired'")
        ->and($source)->not->toContain("'Perlu Koreksi (sync)'")
        ->and($source)->toContain("'title' => 'Koreksi Offline'")
        // Angkanya sudah di chip, jadi pesan tidak lagi dimulai dengan {$…->count()}.
        ->and($source)->not->toMatch('/\'message\' => "\{\$\w+->count\(\)\}/');
});

test('baris kartu koreksi offline menampilkan transaksinya, bukan varian kosong', function () {
    $source = file_get_contents(resource_path('js/Components/BadgeCard.vue'));

    expect($source)->toContain('<template v-if="item.code">')
        ->and($source)->not->toContain('Exp: ');
});

test('saran barang tertekan dihitung sebagai keputusan pelanggan', function () {
    // Tombol kasir berbunyi "Diterima": yang menerima pelanggan, bukan kasir.
    expect(ownerDashboardCopySource())->toContain('saran diterima pelanggan');
});
