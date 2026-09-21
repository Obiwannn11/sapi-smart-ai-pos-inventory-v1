<?php

/*
|--------------------------------------------------------------------------
| Penjaga Pemecahan Bundel Halaman Aplikasi — `[BL-094]`
|--------------------------------------------------------------------------
|
| `import.meta.glob('./Pages/**\/*.vue', { eager: true })` menjadikan 56 halaman
| Vue satu bundel entry 1,1 MB. `[BL-091]` sudah membebaskan pengunjung yang
| belum punya akun dari muatan itu; yang tersisa ditanggung pengguna yang sudah
| masuk — kasir yang seharian hanya membuka satu layar tetap mengunduh panel
| platform, laporan, dan langganan pada muat pertama.
|
| Entri ini menggantinya dengan glob malas + `resolvePageComponent`. Yang perlu
| dijaga bukan angkanya, melainkan bentuknya: satu kata `eager: true` yang
| disalin kembali ke `resolve` akan mengembalikan bundel 1,1 MB itu tanpa satu
| pun error, tanpa satu pun halaman rusak, dan tanpa seorang pun menagihnya —
| persis cara ia bertahan selama ini.
|
| Penjaga pertama membaca sumbernya dan selalu berjalan. Dua sisanya membaca
| hasil build yang nyata, dan sengaja MELEWATI diri bila `public/build` belum
| ada (`.gitignore` mengecualikannya, jadi checkout bersih tidak punya apa-apa
| untuk diperiksa) — dengan alasan yang tercetak, bukan diam-diam lulus.
|
*/

/**
 * Anggaran ukuran bundel entry aplikasi.
 *
 * Diturunkan dari pengukuran, bukan dikarang: sesudah pemecahan, entry-nya
 * 270 KB (dari 1.136 KB). 400 KB memberi ruang tumbuh yang wajar untuk
 * kerangka bersama — Vue, Inertia, layout — sambil tetap menangkap satu-satunya
 * bentuk kemunduran yang penting di sini, yaitu 56 halaman masuk kembali ke
 * dalamnya sekaligus.
 */
const MAX_APP_ENTRY_BYTES = 400 * 1024;

/**
 * Manifest Vite hasil build terakhir, atau `null` bila belum pernah dibuild.
 *
 * @return array<string, array{file: string, imports?: array<int, string>}>|null
 */
function viteBuildManifest(): ?array
{
    $path = public_path('build/manifest.json');

    if (! file_exists($path)) {
        return null;
    }

    return json_decode(file_get_contents($path), true);
}

test('app.js memuat halaman secara malas, bukan eager', function () {
    $source = file_get_contents(resource_path('js/app.js'));

    expect($source)->toContain('resolvePageComponent');

    // Yang dilarang spesifik: glob halaman yang eager. `eager: true` untuk glob
    // LAIN (mis. ikon atau lokal) bukan urusan entri ini dan tidak dilarang.
    preg_match('/import\.meta\.glob\(\s*[\'"`]\.\/Pages\/[^\'"`]+[\'"`]\s*(,[^)]*)?\)/', $source, $matches);

    expect($matches)->not->toBeEmpty(
        'Glob halaman ./Pages/**/*.vue tidak ditemukan di resources/js/app.js. '.
        'Bila cara memuat halaman diganti, penjaga ini harus ikut diperbarui — '.
        'penjaga yang tidak menemukan apa pun tidak menjaga apa pun.',
    );

    expect($matches[1] ?? '')->not->toMatch('/eager\s*:\s*true/', implode("\n", [
        'Glob halaman di resources/js/app.js kembali eager.',
        'Itu menyatukan seluruh 56 halaman Vue ke dalam bundel entry (± 1,1 MB)',
        'dan setiap pengguna mengunduh semuanya pada muat pertama. Pakai glob',
        'malas + resolvePageComponent dari laravel-vite-plugin/inertia-helpers.',
    ]));
});

test('setiap halaman Vue jadi chunk-nya sendiri di hasil build', function () {
    $manifest = viteBuildManifest();

    if ($manifest === null) {
        $this->markTestSkipped('public/build/manifest.json belum ada — jalankan `npm run build` untuk memeriksa hasil build.');
    }

    $chunked = collect($manifest)
        ->keys()
        ->filter(fn (string $key): bool => str_starts_with($key, 'resources/js/Pages/'))
        ->count();

    // Saat eager, TIDAK ADA satu pun halaman yang punya entri manifest sendiri:
    // semuanya lebur ke dalam entry. Jadi jumlah ini adalah pemeriksaannya.
    expect($chunked)->toBeGreaterThan(0, implode("\n", [
        'Tidak ada satu pun halaman Vue yang punya chunk sendiri di manifest.',
        'Itu tanda glob halaman kembali eager: Vite melebur semuanya ke entry.',
    ]));
});

test('bundel entry aplikasi tidak melewati anggarannya', function () {
    $manifest = viteBuildManifest();

    if ($manifest === null) {
        $this->markTestSkipped('public/build/manifest.json belum ada — jalankan `npm run build` untuk memeriksa hasil build.');
    }

    expect($manifest)->toHaveKey('resources/js/app.js');

    $entry = public_path('build/'.$manifest['resources/js/app.js']['file']);

    expect(file_exists($entry))->toBeTrue();

    $size = filesize($entry);

    expect($size)->toBeLessThanOrEqual(MAX_APP_ENTRY_BYTES, implode("\n", [
        'Bundel entry '.round($size / 1024).' KB melewati anggaran '.round(MAX_APP_ENTRY_BYTES / 1024).' KB.',
        'Periksa lebih dulu apakah halaman kembali ikut terbundel di sana.',
        'Menaikkan MAX_APP_ENTRY_BYTES adalah keputusan tersendiri — anggaran',
        'yang dinaikkan tiap kali dilanggar bukan anggaran.',
    ]));
});
