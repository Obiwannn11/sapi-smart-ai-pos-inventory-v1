<?php

use App\Models\Plan;
use App\Models\PricingRule;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::query()->delete();
    PricingRule::query()->delete();
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
});

test('landing menjelaskan cara tarif dihitung dalam tiga langkah', function () {
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Bagaimana Harga Anda Dihitung')
        ->assertSee('Omzet bulan lalu dihitung')
        ->assertSee('Angkanya jatuh ke satu kelas')
        ->assertSee('Tarif bulan itu mengikuti');
});

test('tangga kelas di landing memakai angka dari pricing_rules', function () {
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000, 15_000_000)->create(['label' => 'B', 'price' => 25_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Rp 10.000')
        ->assertSee('Rp 25.000')
        ->assertSee('Rp 0 – di bawah Rp 2.000.000');
});

test('aturan tarif yang berubah ikut berubah di landing', function () {
    // Inti butir (c): halaman yang menjelaskan mekanisme dengan angka yang
    // berbeda dari mesinnya lebih buruk daripada halaman yang diam.
    $rule = PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    get('/')->assertSee('Rp 10.000');

    $rule->update(['price' => 12_000]);
    get('/')->assertSee('Rp 12.000')->assertDontSee('Rp 10.000');
});

test('landing menyebut ambang kelayakan bila tangganya berujung', function () {
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000, 50_000_000)->create(['label' => 'B', 'price' => 25_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Di atas Rp 50.000.000');
});

test('tanpa aturan tarif, bagian penjelasan tidak muncul kosong', function () {
    get('/')
        ->assertStatus(200)
        ->assertDontSee('Bagaimana Harga Anda Dihitung');
});

test('landing menuliskan apa yang TIDAK dilihat platform', function () {
    // `[BL-066]`(b): tiga kalimat ini menjawab keberatan yang pasti muncul
    // lebih baik daripada satu halaman fitur.
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Isi transaksi tidak dilihat')
        ->assertSee('Tidak ada laporan mandiri')
        ->assertSee('total omzet dan jumlah transaksi');
});

test('landing TIDAK menjanjikan tarif terkunci selamanya', function () {
    // `[BL-066]`(b) meminta menuliskan "tarif yang sudah dibayar terkunci
    // (`price_locked`)". Itu tidak benar: `issueDuePeriodInvoices()` selalu
    // menghitung ulang lewat `resolveFor()` dan tidak pernah membaca
    // `price_locked` — `[BL-041]` sudah mengoreksinya 2026-08-07. Menjanjikannya
    // di landing berarti menjanjikan yang tidak dilakukan sistem.
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);

    get('/')
        ->assertStatus(200)
        ->assertDontSee('tarif Anda terkunci')
        ->assertDontSee('terkunci selamanya')
        // Yang benar, dan yang ditulis sebagai gantinya.
        ->assertSee('Tagihan terbit tidak berubah surut');
});

test('permukaan publik tidak memakai istilah "dynamic pricing"', function () {
    // `[BL-066]`(e): nama itu bertabrakan dengan `[BL-018]`, yang memakai
    // "harga dinamis" untuk diskon barang mendekati kedaluwarsa — hal yang
    // sama sekali berbeda.
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);

    foreach (['/', '/harga'] as $url) {
        get($url)
            ->assertStatus(200)
            ->assertDontSee('dynamic pricing', false)
            ->assertDontSee('Dynamic Pricing', false)
            ->assertDontSee('harga dinamis');
    }
});

test('landing menautkan panduan langganan sebagai versi panjangnya', function () {
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee(route('docs.show', ['track' => 'panduan', 'page' => 'langganan']));
});

test('panduan langganan menjelaskan tangga, arah turun-naiknya, dan ambangnya', function () {
    get('/dokumentasi/panduan/langganan')
        ->assertStatus(200)
        ->assertSee('Bagaimana tarif Harga Adaptif dihitung')
        ->assertSee('Kalau omzet saya turun bulan depan')
        ->assertSee('Di atas ambang');
});

test('panduan langganan memakai istilah Harga Tetap dan Harga Adaptif', function () {
    $page = get('/dokumentasi/panduan/langganan')->assertStatus(200);

    $page->assertSee('Harga Tetap')->assertSee('Harga Adaptif');
});

test('panduan langganan menyebut masa coba dua bulan, bukan 30 hari', function () {
    // `trial_months` = 2. Panduan sebelumnya menulis "30 hari pertama".
    get('/dokumentasi/panduan/langganan')
        ->assertStatus(200)
        ->assertSee('2 bulan pertama')
        ->assertDontSee('30 hari pertama');
});
