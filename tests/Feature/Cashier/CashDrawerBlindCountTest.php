<?php

/**
 * Penghitungan buta di layar sesi kas (`[BL-086]`).
 *
 * Halamannya Vue dan tidak pernah dirender PHP, jadi yang bisa dijaga di sini
 * adalah SUMBERNYA — dan itu justru lapis yang paling perlu dijaga: kebocoran
 * yang diperbaiki entri ini lahir dari satu baris template yang tampak tidak
 * berbahaya, dan akan lahir kembali dengan cara yang sama.
 */
function cashDrawerPageSource(): string
{
    return file_get_contents(resource_path('js/Pages/Cashier/CashDrawer.vue'));
}

/**
 * Panel "Sesi Kas Aktif" — dari judulnya sampai tepat sebelum blok Tutup Kas.
 * Sengaja dipotong: angka yang sama BOLEH muncul di ringkasan tutup kas, yang
 * hanya terlihat setelah kasir menyetorkan hitungan fisiknya.
 */
function activeSessionPanelSource(): string
{
    $page = cashDrawerPageSource();

    $start = strpos($page, 'Sesi Kas Aktif');
    $end = strpos($page, 'Tutup Kas: dua langkah');

    expect($start)->not->toBeFalse();
    expect($end)->not->toBeFalse();

    return substr($page, $start, $end - $start);
}

test('uang seharusnya di laci tersembunyi secara bawaan', function () {
    expect(cashDrawerPageSource())->toContain('const showExpected = ref(false)');
});

test('ketiga angka yang membentuk jawabannya ikut tersembunyi', function () {
    // Menyembunyikan totalnya sambil memajang modal + tunai masuk − kembalian
    // keluar bukan penghitungan buta, itu soal hitungan.
    $panel = activeSessionPanelSource();

    expect($panel)->toContain('v-if="reconciliation && showExpected"')
        ->and($panel)->toContain('showExpected ? formatCurrency(expectedAmount)');

    // Tidak ada satu pun angka rekonsiliasi yang lolos dari gerbangnya: setiap
    // penyebutan `cash_in`/`change_out`/`expectedAmount` di panel ini harus
    // berada di dalam blok `showExpected`.
    $revealed = substr($panel, strpos($panel, 'showExpected'));

    expect(substr_count($panel, 'reconciliation.cash_in'))
        ->toBe(substr_count($revealed, 'reconciliation.cash_in'))
        ->and(substr_count($panel, 'reconciliation.change_out'))
        ->toBe(substr_count($revealed, 'reconciliation.change_out'));
});

test('ada tombol untuk menampilkannya, beserta alasan kenapa ia disembunyikan', function () {
    $panel = activeSessionPanelSource();

    expect($panel)->toContain('Tampilkan uang seharusnya')
        ->and($panel)->toContain('@click="toggleExpected"')
        // Kasir yang tidak tahu kenapa angkanya hilang akan mengira halamannya
        // rusak, lalu melaporkannya sebagai bug.
        ->and($panel)->toContain('Disembunyikan supaya hitungan uang fisik Anda jujur');
});

test('ringkasan tutup kas tetap membuka semuanya', function () {
    // Sesudah hitungan fisik disetorkan, menyembunyikannya tidak melindungi
    // apa pun dan hanya membuat kasir tidak bisa mempertanggungjawabkan
    // selisihnya. `[BL-028]` Tahap A butir 4 tetap berlaku di sini.
    $page = cashDrawerPageSource();
    $summary = substr($page, strpos($page, 'Ringkasan Tutup Kas'));

    expect($summary)->toContain('Seharusnya di laci')
        ->and($summary)->toContain('Selisih')
        ->and($summary)->toContain('tidak masuk laci')
        ->and($summary)->not->toContain('showExpected');
});
