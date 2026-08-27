<?php

/**
 * Penjaga daya tahan antrean offline — `[BL-016]` Bagian B.
 *
 * Dua perbaikan yang dijaga di sini tidak menutup lubang apa pun; keduanya
 * memperkecil jendela kehilangan penjualan sambil menunggu lapisan native:
 *
 *   B.1  Background Sync. `flush()` hanya jalan selama halaman POS terbuka,
 *        jadi kasir yang menutup tab saat tutup toko meninggalkan uang
 *        menggantung di IndexedDB. Service worker kini bisa mengirimkannya
 *        tanpa satu tab pun terbuka.
 *   B.2  `navigator.storage.persist()`. Tanpa itu, IndexedDB berstatus
 *        "sebisanya" — "Hapus data penjelajahan" atau tekanan penyimpanan
 *        membuangnya tanpa jejak, dan outbox adalah SATU-SATUNYA data di
 *        aplikasi ini yang belum punya salinan di server.
 *
 * Kenapa penjaga berbasis teks sumber, bukan uji perilaku: aplikasi ini tidak
 * punya pelari uji JavaScript, dan `public/sw.js` adalah berkas tulisan tangan
 * di luar bundel — ia tidak meng-`import` apa pun dari `resources/js`, jadi
 * setiap nama yang dipakai bersama disalin, bukan dibagi. Persis itulah yang
 * dijaga di bawah: nama yang meleset di satu sisi TIDAK menimbulkan error,
 * hanya membuat sinkronisasinya diam-diam tidak pernah jalan. Pola yang sama
 * sudah dipakai `StaticAssetBudgetTest` dan `OfflineColdStartTest`.
 *
 * Uji yang sesungguhnya menuntut peramban sungguhan: jual offline, tutup SEMUA
 * tab, nyalakan jaringan, lalu tunggu permintaannya datang. Itu tidak bisa
 * dijalankan dari sini, dan dicatat apa adanya di `[BL-016]` D4.
 *
 * Catatan bagi yang menyunting berkas ini: beberapa matcher Pest memakai
 * argumen kedua untuk sesuatu selain pesan kegagalan — `toContain()` membacanya
 * sebagai jarum tambahan, `toHaveKey()` sebagai nilai yang diharapkan. Keduanya
 * lolos diam-diam lalu gagal dengan pesan yang membingungkan. Karena itu setiap
 * pemeriksaan di sini ditulis sebagai `toBe(...)`, bentuk yang memang membawa
 * pesannya.
 */

use App\Http\Requests\SyncOfflineTransactionsRequest;

function swSource(): string
{
    return file_get_contents(public_path('sw.js'));
}

function backgroundSyncSource(): string
{
    return file_get_contents(resource_path('js/services/backgroundSync.js'));
}

function offlineDbSource(): string
{
    return file_get_contents(resource_path('js/services/offlineDb.js'));
}

/**
 * Ambil nilai sebuah konstanta string JavaScript (`const NAMA = 'nilai';`).
 */
function jsConstant(string $source, string $name): ?string
{
    preg_match('/(?:const|export const) '.preg_quote($name, '/')."\s*=\s*'([^']*)'/", $source, $matches);

    return $matches[1] ?? null;
}

test('service worker mendengarkan peristiwa sync', function () {
    // Inti B.1. Tanpa pendengar ini, `registration.sync.register()` di sisi
    // halaman hanya mendaftarkan tag yang tidak ada yang menunggunya — dan
    // peramban tidak mengeluhkan apa pun.
    expect(str_contains(swSource(), "addEventListener('sync'"))->toBe(true, implode("\n", [
        'public/sw.js tidak lagi mendengarkan peristiwa `sync`.',
        'Selama ini terjadi, penjualan offline kembali hanya bisa terkirim',
        'selagi tab POS terbuka — persis lubang yang [BL-016] B.1 tulis.',
    ]));
});

test('tag Background Sync sama persis di halaman dan di service worker', function () {
    // Penjaga terpenting di berkas ini. Kedua sisi menuliskan tag yang sama
    // sebagai literal karena sw.js di luar bundel dan tidak bisa meng-import
    // konstanta. Salah ketik di salah satunya tidak menimbulkan error sama
    // sekali: halaman mendaftar, peramban menerima, lalu tidak ada yang
    // pernah menyala. Kegagalan paling mahal justru yang paling sunyi.
    $page = jsConstant(backgroundSyncSource(), 'OUTBOX_SYNC_TAG');
    $worker = jsConstant(swSource(), 'OUTBOX_SYNC_TAG');

    expect($page)->not->toBeNull('OUTBOX_SYNC_TAG hilang dari services/backgroundSync.js.');
    expect($worker)->not->toBeNull('OUTBOX_SYNC_TAG hilang dari public/sw.js.');

    expect($worker)->toBe($page, implode("\n", [
        "Tag Background Sync berbeda: halaman '{$page}' vs service worker '{$worker}'.",
        'Ini gagal tanpa satu pun pesan error — halaman mendaftarkan tag yang',
        'tidak didengar siapa pun, dan antrean offline berhenti terkirim di',
        'latar tanpa ada yang tahu. Lihat [BL-016] B.1.',
    ]));
});

test('nama tempat kredensial sync sama persis di kedua sisi', function () {
    // Alasannya sama dengan tag: sw.js menyalin nama-nama ini, tidak
    // mengimpornya. Selisih satu huruf membuat service worker mencari
    // kredensial di tempat yang tidak pernah diisi, lalu pulang diam-diam.
    $page = backgroundSyncSource();
    $worker = swSource();

    foreach (['SYNC_META_CACHE', 'SYNC_CREDENTIALS_KEY'] as $name) {
        $expected = jsConstant($page, $name);

        expect($expected)->not->toBeNull("{$name} hilang dari services/backgroundSync.js.");

        expect(jsConstant($worker, $name))->toBe($expected, implode("\n", [
            "{$name} di public/sw.js tidak lagi sama dengan di services/backgroundSync.js.",
            'Service worker membacanya lewat nama, bukan lewat import, jadi ia akan',
            'mencari di tempat yang tidak pernah diisi dan pulang tanpa mengirim',
            'apa pun. Lihat [BL-016] B.1.',
        ]));
    }

    // Nama store outbox dipakai bersama dengan cara yang sama persis.
    $expected = jsConstant(offlineDbSource(), 'OUTBOX_STORE');

    expect($expected)->not->toBeNull('OUTBOX_STORE hilang dari services/offlineDb.js.');

    expect(jsConstant($worker, 'OUTBOX_STORE'))->toBe($expected, implode("\n", [
        'OUTBOX_STORE di public/sw.js tidak lagi sama dengan di services/offlineDb.js.',
        'Service worker akan membaca antrean kosong dan tidak mengirim apa pun.',
    ]));
});

test('sapuan activate tidak ikut membuang kredensial sync', function () {
    // Jebakan yang sangat mudah terlewat. Handler `activate` menghapus SETIAP
    // cache yang tidak ada di daftar simpan, jadi cache kredensial yang lupa
    // didaftarkan akan terhapus tiap kali service worker baru menyala — dan
    // Background Sync mati diam-diam sampai ada penjualan berikutnya yang
    // mengisinya lagi. Persis bentuk kegagalan yang tidak akan ada yang
    // melaporkannya.
    $worker = swSource();

    preg_match('/\.filter\(\s*\(key\) =>(.*?)\)\s*\.map\(/s', $worker, $matches);

    expect(array_key_exists(1, $matches))->toBe(true, implode("\n", [
        'Daftar simpan cache di handler activate public/sw.js tidak ditemukan.',
    ]));

    expect(str_contains($matches[1], 'SYNC_META_CACHE'))->toBe(true, implode("\n", [
        'SYNC_META_CACHE tidak lagi ada di daftar cache yang dikecualikan dari',
        'sapuan `activate` di public/sw.js. Kredensial sync akan terhapus setiap',
        'kali service worker baru menyala, dan pengiriman di latar berhenti tanpa',
        'satu pun pesan. Lihat [BL-016] B.1.',
    ]));
});

test('service worker tidak pernah menulis ke outbox', function () {
    // Ini yang menjaga rancangannya tetap "kirim lalu lupakan". Begitu sw.js
    // boleh menulis, seluruh aturan antrean (MAX_ATTEMPTS, parkir `failed`,
    // vonis per baris) punya salinan kedua — di berkas yang tidak dijangkau
    // pelari uji mana pun. `client_uuid` membuat pengiriman ulang gratis, jadi
    // pembukuannya tidak perlu ada dua.
    $source = swSource();

    expect(str_contains($source, 'readwrite'))->toBe(false, implode("\n", [
        'public/sw.js membuka transaksi IndexedDB `readwrite`.',
        'Handler sync dirancang hanya MEMBACA outbox lalu mem-POST isinya;',
        'pembukuan (attempts, status, penghapusan baris) tetap milik',
        'useOfflineQueue seorang diri. Lihat [BL-016] B.1.',
    ]));
});

test('payload sync di service worker sebentuk dengan milik antrean', function () {
    // `toSyncPayload()` di sw.js adalah salinan `toPayload()` di composable.
    // Menambah field di composable tanpa menambahnya di sini akan membuat
    // penjualan yang tersinkron di latar kehilangan field itu — dan hanya
    // penjualan yang tersinkron di latar. Cacat yang muncul-hilang seperti itu
    // hampir mustahil dilacak dari laporan pengguna.
    $composable = file_get_contents(resource_path('js/composables/useOfflineQueue.js'));

    preg_match('/function toPayload\(entry\) \{\s*return \{(.*?)\};/s', $composable, $matches);
    expect(array_key_exists(1, $matches))->toBe(true, 'toPayload() tidak ditemukan di useOfflineQueue.js.');

    preg_match_all('/^\s*(\w+):/m', $matches[1], $fields);
    $expected = $fields[1];

    expect($expected)->not->toBeEmpty();

    $worker = swSource();
    preg_match('/function toSyncPayload\(entry\) \{\s*return \{(.*?)\};/s', $worker, $workerMatches);
    expect(array_key_exists(1, $workerMatches))->toBe(true, 'toSyncPayload() tidak ditemukan di public/sw.js.');

    preg_match_all('/^\s*(\w+):/m', $workerMatches[1], $workerFields);

    expect($workerFields[1])->toBe($expected, implode("\n", [
        'Field payload di public/sw.js dan useOfflineQueue.js sudah berbeda.',
        'Diharapkan: '.implode(', ', $expected),
        'Ditemukan:  '.implode(', ', $workerFields[1]),
        'Akibatnya hanya terasa pada penjualan yang tersinkron lewat Background',
        'Sync — yang tersinkron dari halaman tetap utuh. Lihat [BL-016] B.1.',
    ]));
});

test('batas batch service worker mengikuti batas server', function () {
    // Batch yang lebih besar dari batas server ditolak 422 SELURUHNYA, dan
    // handler sync akan melempar lalu dicoba lagi — berkali-kali, dengan hasil
    // yang sama persis, sampai peramban menyerah.
    $worker = swSource();

    preg_match('/const OUTBOX_BATCH_SIZE = (\d+);/', $worker, $matches);

    expect(array_key_exists(1, $matches))->toBe(true, 'OUTBOX_BATCH_SIZE hilang dari public/sw.js.');

    expect((int) $matches[1])->toBe(SyncOfflineTransactionsRequest::MAX_BATCH_SIZE, implode("\n", [
        'OUTBOX_BATCH_SIZE di public/sw.js melampaui atau meleset dari',
        'SyncOfflineTransactionsRequest::MAX_BATCH_SIZE.',
        'Batch yang kelewat besar ditolak seluruhnya, dan handler sync akan',
        'mengulanginya dengan hasil yang sama sampai peramban menyerah.',
    ]));
});

test('POS meminta penyimpanan yang tidak bisa diusir peramban', function () {
    // `[BL-016]` B.2. Layar kasir adalah satu-satunya tempat yang pantas
    // menanyakannya: outbox terisi dari sini, dan hanya di sini pertanyaannya
    // sepadan (Chrome menjawab diam-diam, Firefox bertanya ke pengguna).
    $source = file_get_contents(resource_path('js/Pages/Cashier/POS.vue'));

    expect(str_contains($source, 'requestPersistentStorage()'))->toBe(true, implode("\n", [
        'POS.vue tidak lagi memanggil requestPersistentStorage().',
        'Tanpa itu IndexedDB kembali berstatus "sebisanya": satu ketukan pada',
        '"Hapus data penjelajahan" membuang antrean penjualan tanpa jejak, dan',
        'itulah satu-satunya data di aplikasi ini yang belum punya salinan di',
        'server. Lihat [BL-016] B.2.',
    ]));
});

test('kredensial sync ikut dibersihkan saat logout', function () {
    // Kredensialnya per-pengguna, dan mesin kasir adalah perangkat bersama.
    // Meninggalkannya berarti service worker terus mencoba atas nama kasir yang
    // sudah pergi — sesinya tidak ada lagi, jadi hasilnya cuma 401 berulang.
    // Barisnya sendiri sengaja TETAP tinggal; itu uang yang belum sampai.
    $source = file_get_contents(resource_path('js/services/offlineSession.js'));

    expect(str_contains($source, 'forgetSyncCredentials()'))->toBe(true, implode("\n", [
        'services/offlineSession.js tidak lagi membersihkan kredensial sync',
        'saat logout. Lihat [BL-016] B.1 dan catatan perangkat bersama di',
        'kepala berkas itu.',
    ]));

    expect(str_contains($source, 'deleteRecord(OUTBOX_STORE'))->toBe(false, implode("\n", [
        'services/offlineSession.js menghapus baris outbox saat logout.',
        'Itu MENGHANCURKAN penjualan yang sudah terjadi di dunia nyata tapi',
        'belum sampai ke server. Yang boleh hilang saat logout hanya yang',
        'punya salinan di server.',
    ]));
});
