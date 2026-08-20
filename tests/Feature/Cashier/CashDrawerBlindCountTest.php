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

function cashDrawerClosePageSource(): string
{
    return file_get_contents(resource_path('js/Pages/Cashier/CashDrawerClose.vue'));
}

/**
 * Panel "Sesi Kas Aktif" — dari judulnya sampai tombol menuju POS. Sengaja
 * dipotong: angka yang sama BOLEH muncul di halaman tutup kas, yang hanya
 * dibuka kasir yang memang berniat menutup lacinya.
 */
function activeSessionPanelSource(): string
{
    $page = cashDrawerPageSource();

    $start = strpos($page, 'Sesi Kas Aktif');
    $end = strpos($page, 'Tombol ke POS');

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
    // penyebutan `cash_in`/`change_out` di panel ini harus berada di dalam blok
    // `showExpected`.
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

test('halaman sesi tidak lagi memegang alur tutup kas', function () {
    // `[BL-086]` butir 2: dua pekerjaan yang terpisah beberapa jam tidak
    // menumpang satu layar. Yang tersisa di halaman sesi hanyalah tautan.
    $page = cashDrawerPageSource();

    expect($page)->toContain('/cashier/cash-drawer/close')
        ->and($page)->not->toContain('closing_amount')
        ->and($page)->not->toContain('Ringkasan Tutup Kas');
});

test('ringkasan tutup kas membuka semuanya', function () {
    // Sesudah hitungan fisik disetorkan, menyembunyikannya tidak melindungi
    // apa pun dan hanya membuat kasir tidak bisa mempertanggungjawabkan
    // selisihnya. `[BL-028]` Tahap A butir 4 tetap berlaku di sini.
    $close = cashDrawerClosePageSource();

    expect($close)->toContain('Seharusnya di laci')
        ->and($close)->toContain('Selisih')
        ->and($close)->toContain('tidak masuk laci')
        ->and($close)->not->toContain('showExpected');
});
