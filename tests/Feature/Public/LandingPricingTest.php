<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::query()->delete();
});

test('bagian harga di landing memajang paket yang benar-benar ada', function () {
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Free')
        ->assertSee('Paid 1')
        ->assertSee('Rp 100.000');
});

test('dua paket karangan yang dulu dipajang sudah tidak ada di mana pun', function () {
    // Angka ini tidak pernah ada di sistem: calon klien yang membacanya lalu
    // mendaftar akan mendapati tagihan yang sama sekali lain. Test ini menjaga
    // keduanya tidak diam-diam kembali lewat penyuntingan berikutnya.
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);

    get('/')
        ->assertStatus(200)
        ->assertDontSee('Rp 149k')
        ->assertDontSee('Rp 299k')
        ->assertDontSee('Core POS<')
        ->assertDontSee('Smart SAPI');
});

test('paket gratis dipajang sebagai gratis, dengan lama masa gratisnya', function () {
    config(['subscription.trial_months' => 2]);
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Gratis')
        ->assertSee('2 bulan pertama');
});

test('harga yang berubah di basis data ikut berubah di landing', function () {
    // Inti butir (2): halaman harga yang bisa berbeda dari tagihan sungguhan
    // adalah cacat terburuk yang bisa dimiliki halaman harga.
    $plan = Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
    get('/')->assertSee('Rp 100.000');

    $plan->update(['base_price' => 125_000]);
    get('/')->assertSee('Rp 125.000')->assertDontSee('Rp 100.000');
});

test('paket nonaktif tidak muncul di landing', function () {
    Plan::factory()->create(['slug' => 'dijual', 'name' => 'Masih Dijual', 'base_price' => 100_000]);
    Plan::factory()->inactive()->create(['slug' => 'ditarik', 'name' => 'Sudah Ditarik', 'base_price' => 50_000]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Masih Dijual')
        ->assertDontSee('Sudah Ditarik');
});

test('tombol paket menuju pendaftaran, bukan halaman masuk', function () {
    // `/register` sudah ada dan itulah tujuan yang dimaksud tombolnya.
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);

    $html = get('/')->assertStatus(200)->getContent();
    $section = substr($html, strpos($html, 'id="pricing"'), 6000);

    expect($section)->toContain(route('register'))
        ->and($section)->not->toContain('href="/login"');
});

test('CTA landing menyebut lama masa gratis, bukan sekadar "gratis"', function () {
    // `[BL-071]`(3): "Daftar Gratis" tidak salah, tapi ia menyembunyikan bagian
    // yang paling menentukan — masa itu berakhir dengan perpindahan ke paket
    // berbayar. Angkanya dari config, jadi mengubah kebijakan tidak menuntut
    // menyunting Blade.
    config(['subscription.trial_months' => 3]);
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);

    get('/')
        ->assertStatus(200)
        ->assertSee('Coba Gratis 3 Bulan')
        ->assertDontSee('Daftar Gratis');
});

test('CTA pendaftaran di luar bagian harga juga menuju pendaftaran', function () {
    // Sisa `[BL-032]`: tiga CTA di hero, nav seluler, dan footer masih menunjuk
    // `/login`. Yang boleh tetap ke sana hanyalah tombol yang memang berbunyi
    // "Login"/"Masuk".
    Plan::factory()->create(['slug' => 'free', 'name' => 'Free', 'base_price' => 0]);

    $html = get('/')->assertStatus(200)->getContent();

    preg_match_all('/<a[^>]+href="\/login"[^>]*>(.*?)<\/a>/s', $html, $matches);

    expect($matches[1])->not->toBeEmpty();

    foreach ($matches[1] as $label) {
        expect(trim(strip_tags($label)))->toBeIn(['Login', 'Masuk']);
    }
});
