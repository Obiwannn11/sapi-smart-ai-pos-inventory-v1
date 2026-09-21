<?php

use function Pest\Laravel\get;

test('hub dokumentasi terbuka untuk umum dan menampilkan kedua jalur', function () {
    get('/dokumentasi')
        ->assertStatus(200)
        ->assertSee('Panduan Penggunaan')
        ->assertSee('Dokumentasi Developer');
});

test('landing page menautkan dokumentasi', function () {
    // Sebelum ini, /api-docs sudah ada tapi tidak ditautkan dari mana pun —
    // dokumentasi yang tidak bisa ditemukan sama saja dengan tidak ada.
    get('/')
        ->assertStatus(200)
        ->assertSee(route('docs.index'))
        ->assertSee(route('api-docs'));
});

test('jalur tanpa halaman membuka halaman pertamanya', function () {
    get('/dokumentasi/panduan')
        ->assertStatus(200)
        ->assertSee('Memulai');

    get('/dokumentasi/developer')
        ->assertStatus(200)
        ->assertSee('Pengantar Integrasi');
});

test('setiap halaman terdaftar bisa dibuka dan merender markdownnya', function () {
    foreach (config('docs.tracks') as $track => $meta) {
        foreach (array_keys($meta['pages']) as $page) {
            get("/dokumentasi/{$track}/{$page}")
                ->assertStatus(200)
                // <h1> hanya muncul bila markdown-nya benar-benar dirender,
                // bukan dikirim mentah.
                ->assertSee('<article class="prose"><h1>', false);
        }
    }
});

test('jalur yang tidak dikenal jatuh ke 404', function () {
    get('/dokumentasi/rahasia')->assertNotFound();
});

test('halaman yang tidak terdaftar jatuh ke 404 meski berkasnya ada', function () {
    // Daftar di config yang jadi gerbangnya, bukan keberadaan berkas. Kalau
    // dibalik, `{page}` dari URL berubah jadi jalan menyusuri sistem berkas.
    get('/dokumentasi/panduan/mulai/../../../composer')->assertNotFound();
    get('/dokumentasi/panduan/tidak-ada')->assertNotFound();
});

test('setiap halaman di manifes punya berkas markdownnya', function () {
    $hilang = [];

    foreach (config('docs.tracks') as $track => $meta) {
        foreach (array_keys($meta['pages']) as $page) {
            if (! is_file(resource_path("docs/{$track}/{$page}.md"))) {
                $hilang[] = "{$track}/{$page}.md";
            }
        }
    }

    // Mendaftarkan halaman tanpa menulis berkasnya menghasilkan 404 yang
    // tertaut dari sidebar — cacat yang hanya terlihat kalau ada yang mengklik.
    expect($hilang)->toBeEmpty();
});

test('halaman terakhir tiap jalur tidak menawarkan tautan berikutnya', function () {
    get('/dokumentasi/panduan/langganan')
        ->assertStatus(200)
        ->assertSee('Sebelumnya')
        ->assertDontSee('>Berikutnya<', false);
});

test('dokumentasi tidak menuntut login', function () {
    // Calon pelanggan membacanya sebelum punya akun; menggerbangnya akan
    // membalik urutan yang wajar.
    expect(app('router')->getRoutes()->getByName('docs.index')->gatherMiddleware())
        ->not->toContain('auth');
});
