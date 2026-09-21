<?php

/*
|--------------------------------------------------------------------------
| Penjaga Aset Statis `public/` — `[BL-077]` butir (c)
|--------------------------------------------------------------------------
|
| Entri aslinya mengusulkan satu plugin Vite pengonversi gambar. Usulan itu
| DIBATALKAN saat dikerjakan, dan alasannya perlu ditulis di sini supaya tidak
| diusulkan ulang: Vite hanya memproses aset yang di-`import` lewat bundel.
| Berkas di `public/` disalin apa adanya dan TIDAK PERNAH disentuh Vite — jadi
| plugin itu akan menambah dependensi yang tidak menyentuh satu pun berkas yang
| jadi alasan entrinya ditulis.
|
| Yang benar-benar berlaku untuk `public/` adalah pemeriksaan, bukan pipeline.
| Tiga penjaga di bawah menggantikan disiplin manusia yang selama ini menambal
| lubangnya sekali jalan (avatar testimoni `[BL-032]`, lalu lima tangkapan layar
| landing 2026-08-21) — dan selalu setelah seseorang kebetulan memperhatikan.
|
*/

/**
 * Direktori dan berkas yang sengaja di luar pemeriksaan.
 *
 * `icons/` dan `favicon.ico` WAJIB tetap PNG/ICO: `manifest.webmanifest`
 * menuliskan `"type": "image/png"` untuk ketiga ikonnya, dan dukungan WEBP
 * untuk ikon PWA maupun favicon masih timpang antar peramban. Ini pengecualian
 * yang disengaja, bukan kelalaian yang belum sempat dibereskan.
 *
 * `build/` milik Vite, dan `vendor/` milik paket pihak ketiga — keduanya bukan
 * aset yang kita masukkan sendiri.
 *
 * @return array<int, string>
 */
function excludedAssetPaths(): array
{
    return ['build', 'vendor', 'icons', 'favicon.ico'];
}

/**
 * Anggaran ukuran satu berkas gambar di `public/`.
 *
 * Angkanya diturunkan dari kenyataan, bukan dikarang: tangkapan layar landing
 * terbesar hari ini `Dashboard-owner.webp` (± 64 KB), jadi 120 KB memberi
 * ruang gerak yang cukup sambil tetap menangkap PNG mentah dari kamera atau
 * ekspor desain — yang justru selalu jadi bentuk pelanggarannya selama ini.
 */
const MAX_PUBLIC_IMAGE_BYTES = 120 * 1024;

/**
 * Seluruh berkas di `public/` yang bukan bawaan Vite, vendor, atau ikon.
 *
 * @return array<int, array{path: string, relative: string, extension: string, size: int}>
 */
function scannedPublicAssets(): array
{
    $root = realpath(public_path());
    $excluded = excludedAssetPaths();

    $files = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST,
    );

    foreach ($iterator as $file) {
        $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));

        $isExcluded = collect($excluded)->contains(
            fn (string $prefix): bool => $relative === $prefix || str_starts_with($relative, $prefix.'/'),
        );

        if ($isExcluded || $file->isDir()) {
            continue;
        }

        $files[] = [
            'path' => $file->getPathname(),
            'relative' => $relative,
            'extension' => strtolower($file->getExtension()),
            'size' => $file->getSize(),
        ];
    }

    return $files;
}

test('tidak ada gambar raster non-WEBP yang masuk public/', function () {
    // Bentuk pelanggarannya selalu sama: seseorang menaruh PNG hasil ekspor
    // langsung ke `public/` karena itu yang paling cepat, dan tidak ada yang
    // menagihnya sampai ada yang kebetulan membuka halamannya lewat kuota.
    $rasterExtensions = ['png', 'jpg', 'jpeg', 'gif', 'bmp', 'tiff'];

    $offenders = collect(scannedPublicAssets())
        ->filter(fn (array $asset): bool => in_array($asset['extension'], $rasterExtensions, true))
        ->map(fn (array $asset): string => $asset['relative'].' ('.$asset['extension'].')')
        ->values()
        ->all();

    expect($offenders)->toBe([], implode("\n", [
        'Gambar raster non-WEBP ditemukan di public/: '.implode(', ', $offenders),
        'Konversi ke WEBP lebih dulu, atau — bila ia memang wajib PNG seperti',
        'favicon dan ikon PWA — daftarkan pengecualiannya di excludedAssetPaths()',
        'BESERTA alasannya. Pengecualian tanpa alasan akan jadi lubang berikutnya.',
    ]));
});

test('tidak ada aset public/ yang melewati anggaran ukuran', function () {
    $offenders = collect(scannedPublicAssets())
        ->filter(fn (array $asset): bool => in_array($asset['extension'], ['webp', 'png', 'jpg', 'jpeg', 'gif', 'svg'], true))
        ->filter(fn (array $asset): bool => $asset['size'] > MAX_PUBLIC_IMAGE_BYTES)
        ->map(fn (array $asset): string => $asset['relative'].' ('.round($asset['size'] / 1024).' KB)')
        ->values()
        ->all();

    expect($offenders)->toBe([], implode("\n", [
        'Aset melewati anggaran '.round(MAX_PUBLIC_IMAGE_BYTES / 1024).' KB: '.implode(', ', $offenders),
        'Kecilkan berkasnya. Menaikkan MAX_PUBLIC_IMAGE_BYTES adalah keputusan',
        'tersendiri — anggaran yang dinaikkan tiap kali dilanggar bukan anggaran.',
    ]));
});

test('setiap berkas yang diprecache service worker benar-benar ada', function () {
    // Penjaga ini lahir dari `[BL-077]` sendiri: `/sapi-logo.png` hendak dihapus
    // sebagai berkas yatim, padahal ia terdaftar di SHELL_ASSETS. `cache.addAll()`
    // bersifat semua-atau-tidak — satu 404 di sana MENGGAGALKAN SELURUH INSTALL
    // service worker, dan aplikasi kehilangan shell offline-nya tanpa satu pun
    // pesan error yang terlihat pengguna. Halamannya tetap normal selama online,
    // jadi tidak ada yang menagihnya sampai ada kasir yang kehilangan sinyal.
    $source = file_get_contents(public_path('sw.js'));

    expect($source)->toContain('SHELL_ASSETS');

    preg_match('/const SHELL_ASSETS = \[(.*?)\];/s', $source, $matches);

    expect($matches)->toHaveKey(1);

    preg_match_all("/'([^']+)'/", $matches[1], $entries);

    $precached = $entries[1];

    expect($precached)->not->toBeEmpty();

    $missing = collect($precached)
        ->reject(fn (string $asset): bool => file_exists(public_path(ltrim($asset, '/'))))
        ->values()
        ->all();

    expect($missing)->toBe([], implode("\n", [
        'Berkas yang diprecache service worker tidak ada: '.implode(', ', $missing),
        'Selama ini terjadi, install service worker GAGAL SELURUHNYA dan mode',
        'offline mati diam-diam. Kembalikan berkasnya, atau cabut entrinya dari',
        'SHELL_ASSETS di public/sw.js dan naikkan CACHE_VERSION.',
    ]));
});
