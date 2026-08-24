<?php

/*
|--------------------------------------------------------------------------
| Penjaga Pemisahan Bundel Halaman Publik — `[BL-091]` butir (d)
|--------------------------------------------------------------------------
|
| Keempat Blade publik pernah memuat `resources/js/app.js` lewat `@vite`,
| padahal tak satu pun punya `#app`/`@inertia`. Yang terlihat hanya dua error
| konsol; yang mahal adalah muatannya — `import.meta.glob(..., eager: true)`
| menjadikan 56 halaman Vue satu bundel 1,1 MB, dan bundel itu diunduh setiap
| pengunjung halaman depan yang belum punya akun.
|
| Halamannya tidak pernah rusak karenanya, dan itu justru masalahnya: tidak ada
| yang menagih. Dua di antara empat Blade sempat bersih sendiri tanpa tercatat,
| lalu landing bertahan berbulan-bulan. Satu `@vite` yang disalin dari
| `app.blade.php` akan mengembalikannya dengan cara yang sama diam-diamnya —
| persis yang sudah terjadi pada `[BL-083]` dan `[BL-089]`.
|
| Penjaga ini menagihnya.
|
*/

use function Pest\Laravel\get;

/**
 * Entry point aplikasi — bundel Inertia yang memuat SELURUH halaman Vue.
 *
 * Halaman publik tidak boleh menyentuhnya. `resources/css/app.css` justru
 * sebaliknya: gaya Tailwind-nya memang dipakai halaman publik, jadi ia WAJIB
 * tetap ada dan sengaja tidak ikut dilarang di sini.
 */
const APPLICATION_JS_ENTRY = 'resources/js/app.js';

/**
 * Seluruh Blade di bawah `resources/views/public/`, beserta isinya.
 *
 * @return array<string, string>
 */
function publicBladeSources(): array
{
    $root = realpath(resource_path('views/public'));

    $sources = [];

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $file) {
        if ($file->isDir() || ! str_ends_with($file->getFilename(), '.blade.php')) {
            continue;
        }

        $relative = str_replace(DIRECTORY_SEPARATOR, '/', substr($file->getPathname(), strlen($root) + 1));

        $sources['public/'.$relative] = file_get_contents($file->getPathname());
    }

    return $sources;
}

test('tidak ada Blade publik yang memuat entry JavaScript aplikasi', function () {
    $sources = publicBladeSources();

    // Kalau pemindaiannya tidak menemukan apa pun, penjaga ini lulus tanpa
    // memeriksa apa pun — dan itu kegagalan yang menyamar jadi keberhasilan.
    expect($sources)->not->toBeEmpty();

    $offenders = collect($sources)
        ->filter(fn (string $source): bool => str_contains($source, APPLICATION_JS_ENTRY))
        ->keys()
        ->all();

    expect($offenders)->toBe([], implode("\n", [
        'Blade publik memuat '.APPLICATION_JS_ENTRY.': '.implode(', ', $offenders),
        'Halaman publik tidak punya elemen mount, jadi bundel itu hanya diunduh,',
        'diurai, lalu gagal. Pakai @vite([\'resources/css/app.css\']) saja.',
        'Bila halaman publik benar-benar butuh JS terbundel, beri ia entry point',
        'SENDIRI di vite.config.js — jangan menumpang entry aplikasi.',
    ]));
});

test('HTML halaman publik tidak menyertakan bundel aplikasi', function (string $url) {
    // Pemindaian sumber di atas bisa dilewati lewat layout yang di-include dari
    // luar `views/public/`. Yang ini memeriksa hasil akhirnya: apa yang benar-
    // benar sampai ke peramban pengunjung.
    $html = get($url)->assertStatus(200)->getContent();

    preg_match_all('/<script[^>]+src="([^"]+)"/i', $html, $matches);

    // Dua bentuk, tergantung ada tidaknya `public/hot`. Saat dev server hidup
    // Vite merender URL mentahnya (`.../resources/js/app.js`); saat memakai
    // hasil build ia merender berkas ber-hash (`/build/assets/app-*.js`).
    // Penjaga yang hanya tahu satu bentuk akan lulus diam-diam di lingkungan
    // yang lain — dan justru itu yang hampir terjadi saat penjaga ini ditulis.
    $offenders = collect($matches[1])
        ->filter(fn (string $src): bool => str_contains($src, APPLICATION_JS_ENTRY)
            || (bool) preg_match('#/build/assets/app-[^"]*\.js$#', $src))
        ->values()
        ->all();

    expect($offenders)->toBe([], implode("\n", [
        $url.' menyertakan bundel aplikasi: '.implode(', ', $offenders),
        'Berkas itu berisi seluruh halaman Vue aplikasi (± 1,1 MB) dan tidak',
        'satu pun dipakai halaman publik. Cabut resources/js/app.js dari',
        'Blade-nya.',
    ]));
})->with([
    'landing' => '/',
    'api-docs' => '/api-docs',
    'dokumentasi' => '/dokumentasi',
]);
