<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::query()->delete();
});

test('halaman harga menyebut pengguna termasuk, kuota AI, dan harga pengguna tambahan', function () {
    Plan::factory()->create([
        'slug' => 'paid-1',
        'name' => 'Paid 1',
        'base_price' => 100_000,
        'included_seats' => 3,
        'extra_seat_price' => 15_000,
        'limits' => ['ai_daily' => 15],
    ]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('Pengguna termasuk')
        ->assertSee('Analisis AI per hari')
        ->assertSee('Pengguna tambahan')
        ->assertSee('Rp 15.000');
});

test('landing menyebut isi paket, bukan hanya harganya', function () {
    Plan::factory()->create([
        'slug' => 'paid-2',
        'name' => 'Paid 2',
        'base_price' => 150_000,
        'included_seats' => 5,
        'extra_seat_price' => 12_500,
        'limits' => ['ai_daily' => 30],
    ]);

    get('/')
        ->assertStatus(200)
        ->assertSee('5 pengguna termasuk')
        ->assertSee('30 analisis AI/hari')
        ->assertSee('Pengguna tambahan Rp 12.500/bulan');
});

test('paket tanpa batas AI tidak dipajang sebagai nol analisis', function () {
    // `null` berarti ikut bawaan platform. Memajangnya "0 analisis/hari" akan
    // menuliskan paket yang tidak menjual AI padahal ia dapat jatah.
    Plan::factory()->create(['slug' => 'ikut-bawaan', 'name' => 'Ikut Bawaan', 'base_price' => 50_000, 'limits' => null]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('Ikut bawaan platform')
        ->assertDontSee('0 analisis');

    get('/')
        ->assertStatus(200)
        ->assertSee('Analisis AI ikut bawaan platform')
        ->assertDontSee('0 analisis AI/hari');
});

test('paket dengan seat tambahan gratis tidak dipajang Rp 0', function () {
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0, 'extra_seat_price' => 0, 'limits' => ['ai_daily' => 5]]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('Gratis')
        ->assertDontSee('Rp 0<', false);
});

test('konsekuensi kuota AI habis dan jalan keluarnya disebut di halaman harga', function () {
    // `[BL-067]`(d): batas yang tidak dijelaskan konsekuensinya akan dibaca
    // sebagai batas keras yang memutus seluruh fitur.
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000, 'limits' => ['ai_daily' => 15]]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('ditolak sampai besok')
        ->assertSee('API key sendiri');
});

test('permukaan publik tidak menjanjikan pembelian kuota AI', function () {
    // `[BL-067]`(e): alur belinya belum berbentuk sama sekali — tidak ada kolom,
    // tidak ada tagihan, tidak ada layar. Menjanjikannya sekarang berarti
    // menjual sesuatu yang tidak bisa dibeli. Menunggu `[BL-069]`.
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000, 'limits' => ['ai_daily' => 15]]);

    foreach (['/', '/harga'] as $url) {
        get($url)
            ->assertStatus(200)
            ->assertDontSee('beli kuota')
            ->assertDontSee('tambah kuota')
            ->assertDontSee('Beli Kuota');
    }
});

test('permukaan publik memakai istilah pengguna, bukan memperkenalkan kata seat', function () {
    // `[BL-067]`(c): jangan memperkenalkan kata ketiga. Layar tenant memakai
    // "pengguna" dan "kursi"; kata "seat" tidak pernah muncul di satu pun,
    // jadi memakainya di permukaan publik justru melanggar syarat itu.
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000, 'included_seats' => 3]);

    foreach (['/', '/harga'] as $url) {
        get($url)
            ->assertStatus(200)
            ->assertSee('engguna')
            ->assertDontSee('seat bawaan')
            ->assertDontSee('Seat tambahan');
    }
});
