<?php

use function Pest\Laravel\get;

/**
 * Ketiga permukaan publik harus terasa satu produk: satu wordmark, satu palet,
 * satu set ikon. Test ini menjaga ketiganya tidak berpencar lagi.
 */
$surfaces = [
    'landing' => '/',
    'api-docs' => '/api-docs',
    'hub dokumentasi' => '/dokumentasi',
    'halaman panduan' => '/dokumentasi/panduan',
];

test('setiap permukaan publik memakai wordmark SAPI POS', function (string $url) {
    $page = get($url)->assertStatus(200);

    // Wordmark bertingkat: "SAPI" tebal + "POS" kecil berhuruf besar.
    $page->assertSee('>SAPI</span>', false)
        ->assertSee('>POS</span>', false);
})->with($surfaces);

test('setiap permukaan publik memuat palet bersama, bukan salinannya sendiri', function (string $url) {
    // Token penanda dari `public/partials/theme`. Bila sebuah halaman berhenti
    // meng-include partialnya, ia akan kehilangan seluruh warnanya di sini.
    get($url)
        ->assertStatus(200)
        ->assertSee('--green-cta:     oklch(0.51 0.12 162)', false)
        ->assertSee('--text-dim:      oklch(0.62 0.010 155)', false);
})->with($surfaces);

test('setiap permukaan publik memakai ikon yang sama dengan aplikasi', function (string $url) {
    get($url)
        ->assertStatus(200)
        ->assertSee('/icons/favicon-32.png', false)
        ->assertSee('/icons/apple-touch-icon.png', false);
})->with($surfaces);

test('tidak ada permukaan publik yang memuat Tailwind dari CDN', function (string $url) {
    // Aplikasi ini memakai Tailwind v4 lewat Vite. CDN-nya v3, jadi ia bukan
    // sekadar permintaan pihak ketiga yang memblokir render — ia build kedua
    // dengan arti kelas yang berbeda, dan ia menang karena dimuat belakangan.
    get($url)
        ->assertStatus(200)
        ->assertDontSee('cdn.tailwindcss.com');
})->with($surfaces);
