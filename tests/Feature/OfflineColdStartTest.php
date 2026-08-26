<?php

/**
 * Penjaga cold start offline — `[BL-095]` dan `[BL-096]`.
 *
 * Keduanya ditemukan saat spike Tahap 1 `[BL-016]` (2026-08-26), dengan server
 * benar-benar dimatikan sampai menjawab *connection refused*. Gejalanya sama
 * dan sama-sama menipu: POS tampil utuh — cangkang, kategori, keranjang, tombol
 * BAYAR — lalu tidak bisa dipakai menjual apa pun.
 *
 * Kenapa ini penjaga berbasis teks sumber, bukan uji perilaku: aplikasi ini
 * tidak punya pelari uji JavaScript (tidak ada Vitest maupun Jest di
 * `package.json`), dan cacatnya hidup di berkas Vue serta HTML statis. Pola
 * yang sama sudah dipakai `StaticAssetBudgetTest` untuk menjaga `sw.js`. Uji
 * yang sesungguhnya menuntut peramban sungguhan dengan servernya dimatikan —
 * dicatat di `[BL-095]` butir (d), dan tidak bisa dijalankan dari sini.
 *
 * Catatan bagi yang menyunting berkas ini: `toContain()` milik Pest menerima
 * BANYAK JARUM, bukan pesan kegagalan. Karena itu setiap pemeriksaan di bawah
 * ditulis sebagai `toBe(true|false, $pesan)` — satu-satunya bentuk yang benar
 * membawa pesannya.
 */
function posSource(): string
{
    return file_get_contents(resource_path('js/Pages/Cashier/POS.vue'));
}

test('halaman offline menyediakan jalan menuju kasir', function () {
    // `[BL-096]`. offline.html adalah cadangan TERAKHIR service worker untuk
    // setiap rute yang tidak tercache — akar situs termasuk. Tanpa tautan ini
    // kasir yang menyalakan aplikasi dalam keadaan offline mendarat di halaman
    // buntu berisi satu tombol "Coba lagi" yang memuat ulang halaman yang sama,
    // padahal /cashier/pos ada di cache dan siap dibuka.
    $source = file_get_contents(public_path('offline.html'));

    expect(str_contains($source, '/cashier/pos'))->toBe(true, implode("\n", [
        'public/offline.html tidak lagi menautkan ke /cashier/pos.',
        'Selama ini terjadi, cold start offline di rute mana pun yang tidak',
        'tercache berakhir buntu — POS-nya ada di cache tapi tak ada jalan',
        'ke sana. Lihat [BL-096].',
    ]));
});

test('POS menandai dirinya offline saat permintaan Inertia gagal', function () {
    // `[BL-095]`. Ini mata rantai yang putus. `navigator.onLine` tetap `true`
    // saat yang mati hanya servernya, jadi tanpa penanda ini `isOnline` tidak
    // pernah jatuh, `watch(isOnline)` tidak pernah menyala, dan katalog cadangan
    // di IndexedDB tidak pernah dibaca — meski isinya lengkap.
    $source = posSource();

    expect(str_contains($source, "router.on('exception'"))->toBe(true, implode("\n", [
        'POS.vue tidak lagi mendengarkan peristiwa `exception` Inertia.',
        'Tanpa itu, kegagalan prop tertunda `products` berlalu diam-diam dan',
        'rak produk menahan kerangka selamanya saat cold start offline.',
        'Lihat [BL-095].',
    ]));

    expect(str_contains($source, 'markOffline()'))->toBe(true, implode("\n", [
        'POS.vue tidak lagi memanggil markOffline(). Lihat [BL-095].',
    ]));
});

test('POS punya jalan pulang dari keadaan offline yang ditandai sendiri', function () {
    // Pasangan dari uji di atas, dan alasannya bukan kerapian. Sebelum
    // `[BL-095]`, `markOnline()` diekspor tapi TIDAK PERNAH dipanggil dari mana
    // pun — sekali `isOnline` ditandai offline, hanya peristiwa `online` milik
    // peramban yang bisa memulihkannya. Menambah pemicu offline tanpa menambah
    // pemulihnya akan menukar satu cacat dengan cacat lain: kasir terkunci di
    // mode tunai padahal sinyalnya sudah kembali.
    $source = posSource();

    expect(str_contains($source, "router.on('success'"))->toBe(true, implode("\n", [
        'POS.vue tidak lagi mendengarkan peristiwa `success` Inertia.',
        'Tanpa itu, penandaan offline tidak punya jalan pulang dan kasir bisa',
        'terkunci di mode tunai walau servernya sudah terjangkau lagi.',
        'Lihat [BL-095].',
    ]));

    expect(str_contains($source, 'markOnline()'))->toBe(true, implode("\n", [
        'POS.vue tidak lagi memanggil markOnline(). Lihat [BL-095].',
    ]));
});

test('pendengar peristiwa Inertia dilepas saat POS ditinggalkan', function () {
    // Dokumentasi Inertia memperingatkan hal ini secara khusus: pendengar yang
    // didaftarkan di dalam komponen menumpuk dan menyala berkali-kali kalau
    // tidak dilepas. Pada layar kasir yang dibuka-tutup sepanjang hari, itu
    // berarti satu kegagalan jaringan memanggil markOffline() belasan kali.
    $source = posSource();

    expect(str_contains($source, 'stopExceptionListener()'))->toBe(true, implode("\n", [
        'Pendengar `exception` tidak lagi dilepas di onUnmounted.',
        'Lihat [BL-095] dan peringatan penumpukan pendengar di dokumen Inertia.',
    ]));

    expect(str_contains($source, 'stopSuccessListener()'))->toBe(true, implode("\n", [
        'Pendengar `success` tidak lagi dilepas di onUnmounted.',
        'Lihat [BL-095] dan peringatan penumpukan pendengar di dokumen Inertia.',
    ]));
});

test('POS menyelidiki sendiri apakah server sudah kembali', function () {
    // `[BL-095]`, dan ini bukan kemewahan. Penandaan offline yang dilakukan
    // sendiri tidak punya peristiwa pemulih: `online` milik peramban hanya
    // menyala kalau antarmuka jaringan berubah, sedangkan pada kasus yang
    // melahirkan entri ini antarmuka tidak pernah putus — yang mati servernya.
    // Tanpa penyelidik berkala, kasir terkunci di mode tunai sampai ia
    // kebetulan berpindah halaman.
    $source = posSource();

    expect(str_contains($source, "router.reload({ only: ['products', 'upsell'] })"))->toBe(true, implode("\n", [
        'Penyelidik pemulihan berkala di POS.vue hilang.',
        'Tanpa itu, sekali `isOnline` ditandai offline sementara',
        '`navigator.onLine` tetap `true`, tidak ada yang pernah memeriksa',
        'apakah server sudah kembali. Lihat [BL-095].',
    ]));
});

test('POS membaca snapshot katalog tanpa menunggu status daring', function () {
    // `[BL-095]` butir (c). Pembacaan ini dulu dibungkus `if (!isOnline.value)`,
    // dan justru itu yang melewatkan keadaan paling genting: cold start dengan
    // server tak terjangkau, saat `navigator.onLine` masih `true`. Penjaga ini
    // memastikan pembungkus itu tidak diam-diam kembali.
    $source = posSource();

    expect(str_contains($source, 'if (!isOnline.value) loadSnapshot();'))->toBe(false, implode("\n", [
        'loadSnapshot() kembali digantungkan pada `!isOnline.value` di onMounted.',
        'Bentuk itulah yang melahirkan [BL-095]: pada cold start offline',
        '`navigator.onLine` masih `true`, jadi cabang ini tidak pernah dimasuki',
        'dan katalog cadangan tidak pernah dibaca.',
    ]));

    expect(str_contains($source, 'loadSnapshot();'))->toBe(true, implode("\n", [
        'POS.vue tidak lagi memanggil loadSnapshot() sama sekali. Lihat [BL-095].',
    ]));
});
