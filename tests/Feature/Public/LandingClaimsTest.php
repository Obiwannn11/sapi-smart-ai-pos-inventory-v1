<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::query()->delete();
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
});

test('landing tidak menjanjikan masuk lewat Google', function () {
    // Tidak ada Socialite, tidak ada rute OAuth, dan `AuthController@register`
    // hanya menerima email + kata sandi. Tombol "Lanjutkan dengan Google" yang
    // dipajang sebelumnya adalah jalur masuk yang tidak pernah ada.
    $html = get('/')->assertStatus(200)->getContent();

    // Google Fonts boleh; yang dilarang adalah janji autentikasinya.
    expect($html)->not->toContain('Lanjutkan dengan Google')
        ->and($html)->not->toContain('akun Google');
});

test('landing tidak menjanjikan katalog yang dibuatkan otomatis', function () {
    // Registrasi membuat tenant + langganan masa coba + user owner. Tidak ada
    // kategori, menu, maupun saran stok yang disemai — `[BL-034]` mencatat
    // justru sebaliknya: tiap tenant baru mendarat di aplikasi paling kosong.
    get('/')
        ->assertStatus(200)
        ->assertDontSee('otomatis membuatkan')
        ->assertDontSee('daftar menu populer')
        ->assertDontSee('saran stok awal');
});

test('landing tidak mengklaim jumlah pengguna yang tidak bisa dibuktikan', function () {
    get('/')
        ->assertStatus(200)
        ->assertDontSee('ribuan UMKM');
});

test('landing menyebut kapabilitas yang benar-benar ada', function () {
    // Dihitung dari isi berkasnya, `[BL-032]` menemukan nol sebutan untuk
    // sebagian besar yang sudah dikirim sejak Mei. Test ini mengunci sebutannya
    // tetap ada, bukan mengunci kalimatnya.
    $html = get('/')->assertStatus(200)->getContent();

    foreach (['tagihan terbuka', 'sesi kas', 'modifier', 'antrian', 'saran jual', 'pesan mandiri', 'MCP'] as $capability) {
        expect(mb_strtolower($html))->toContain(mb_strtolower($capability));
    }
});
