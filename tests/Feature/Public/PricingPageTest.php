<?php

use App\Models\Plan;
use App\Models\PricingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::query()->delete();
    PricingRule::query()->delete();
});

test('halaman harga terbuka untuk umum, tanpa sesi', function () {
    // Syarat yang paling mudah dilanggar diam-diam: halaman ini dibaca orang
    // yang belum punya akun. Satu pemanggilan yang butuh tenant akan membuatnya
    // meledak justru bagi pembaca yang dituju.
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('Harga Tetap')
        ->assertSee('Harga Adaptif');
});

test('tarif jalur Harga Tetap dibacakan dari paket yang benar-benar ada', function () {
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
    Plan::factory()->create(['slug' => 'paid-2', 'name' => 'Paid 2', 'base_price' => 150_000]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('Paid 1')
        ->assertSee('Rp 100.000')
        ->assertSee('Rp 150.000');
});

test('tangga Harga Adaptif dipajang beserta rentang omzet dan tarifnya', function () {
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000, 15_000_000)->create(['label' => 'B', 'price' => 25_000]);

    get('/harga')
        ->assertStatus(200)
        // Kata-katanya mengikuti `Billing/Adaptive.vue`, bukan bentuk baru.
        ->assertSee('Rp 0 – di bawah Rp 2.000.000')
        ->assertSee('Rp 2.000.000 – di bawah Rp 15.000.000')
        ->assertSee('Rp 10.000')
        ->assertSee('Rp 25.000');
});

test('ambang kelayakan disebut angkanya bila tangganya berujung', function () {
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000, 50_000_000)->create(['label' => 'B', 'price' => 25_000]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('sampai omzet di bawah');
});

test('tangga tanpa ujung tidak memunculkan ambang palsu', function () {
    // `ceiling` null berarti tangganya tidak berujung, BUKAN ambang nol.
    // Halaman yang menukar keduanya akan memberi tahu SETIAP pengunjung bahwa
    // omzetnya terlalu tinggi untuk Harga Adaptif — arah gagal `[BL-048]`.
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000)->create(['label' => 'B', 'price' => 25_000]);

    get('/harga')
        ->assertStatus(200)
        ->assertDontSee('sampai omzet di bawah')
        ->assertDontSee('di bawah Rp 0');
});

test('tanpa satu pun aturan tarif, bagian adaptif tidak dipajang kosong', function () {
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);

    // Seluruh halaman tetap berdiri; yang hilang hanya bagian yang tidak punya
    // isi. Tabel tangga tanpa satu baris pun lebih buruk daripada tidak ada.
    get('/harga')
        ->assertStatus(200)
        ->assertSee('Jalur Harga Tetap')
        ->assertDontSee('Kelas');
});

test('syarat perpindahan jalur disebut angkanya, dibaca dari config', function () {
    config(['subscription.track_switch_minimum_months' => 3]);
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('setiap <strong>3 bulan</strong> sekali', false);
});

test('harga yang berubah di basis data ikut berubah di halaman harga', function () {
    $plan = Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
    get('/harga')->assertSee('Rp 100.000');

    $plan->update(['base_price' => 125_000]);
    get('/harga')->assertSee('Rp 125.000')->assertDontSee('Rp 100.000');
});

test('halaman harga memakai kerangka dan wordmark yang sama dengan permukaan publik lain', function () {
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);

    get('/harga')
        ->assertStatus(200)
        ->assertSee('>SAPI</span>', false)
        ->assertSee('>POS</span>', false)
        ->assertSee('--green-cta:     oklch(0.51 0.12 162)', false)
        ->assertDontSee('cdn.tailwindcss.com');
});

test('landing menautkan halaman harga', function () {
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee(route('pricing'));
});
