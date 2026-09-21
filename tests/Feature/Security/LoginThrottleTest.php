<?php

use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\post;

/**
 * BL-007 — tiap endpoint login harus membatasi laju percobaan.
 *
 * Test ini sengaja ada supaya throttle tidak hilang diam-diam saat rute
 * dirapikan: tanpa penjaga, satu baris `->middleware()` yang terhapus tidak
 * akan membuat apa pun jadi merah, dan celahnya baru ketahuan saat disalahgunakan.
 */
beforeEach(function () {
    RateLimiter::clear('login');
    RateLimiter::clear('platform-login');
    RateLimiter::clear('mobile-login');
});

/**
 * Pesannya memuat sisa detik yang berubah-ubah, jadi dicocokkan sebagian saja —
 * mencocokkan persis akan rapuh tanpa menambah jaminan apa pun.
 */
function assertLoginThrottled(TestResponse $response): void
{
    $response->assertSessionHasErrors('email');

    expect(session('errors')->first('email'))->toContain('Terlalu banyak percobaan masuk');
}

// ── Login tenant (web) ───────────────────────────────────────────────────────
test('tenant login blocks further attempts after the limit', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create([
        'tenant_id' => $tenant->id,
        'email' => 'kasir@usaha.test',
        'password' => 'rahasia123',
    ]);

    foreach (range(1, 5) as $ignored) {
        post('/login', ['email' => 'kasir@usaha.test', 'password' => 'salah']);
    }

    assertLoginThrottled(post('/login', ['email' => 'kasir@usaha.test', 'password' => 'salah']));
});

test('throttle is keyed per email so one account cannot lock out another', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir-a@usaha.test', 'password' => 'rahasia123']);
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir-b@usaha.test', 'password' => 'rahasia123']);

    // Habiskan jatah kasir A.
    foreach (range(1, 6) as $ignored) {
        post('/login', ['email' => 'kasir-a@usaha.test', 'password' => 'salah']);
    }

    // Kasir B di jaringan yang sama tetap bisa masuk — inilah alasan kuncinya
    // memakai email + IP, bukan IP saja.
    post('/login', ['email' => 'kasir-b@usaha.test', 'password' => 'rahasia123'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

test('email casing does not grant a fresh quota', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir@usaha.test', 'password' => 'rahasia123']);

    foreach (range(1, 6) as $ignored) {
        post('/login', ['email' => 'kasir@usaha.test', 'password' => 'salah']);
    }

    assertLoginThrottled(post('/login', ['email' => 'KASIR@USAHA.TEST', 'password' => 'salah']));
});

// ── Login platform ───────────────────────────────────────────────────────────
test('platform login blocks further attempts after the limit', function () {
    PlatformUser::factory()->create(['email' => 'pemilik@sapi.test', 'password' => 'rahasia123']);

    foreach (range(1, 5) as $ignored) {
        post('/platform/login', ['email' => 'pemilik@sapi.test', 'password' => 'salah']);
    }

    assertLoginThrottled(post('/platform/login', ['email' => 'pemilik@sapi.test', 'password' => 'salah']));
});

test('platform login caps sustained guessing across different emails from one ip', function () {
    // Setiap email punya ember per-menitnya sendiri, jadi penebakan lintas email
    // lolos dari kunci email+IP. Langit-langit per jam per IP yang menahannya.
    foreach (range(1, 20) as $i) {
        post('/platform/login', ['email' => "tebakan-{$i}@sapi.test", 'password' => 'salah']);
    }

    assertLoginThrottled(post('/platform/login', ['email' => 'tebakan-baru@sapi.test', 'password' => 'salah']));
});

test('tenant login is not affected by the platform hourly ip cap', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir@usaha.test', 'password' => 'rahasia123']);

    foreach (range(1, 20) as $i) {
        post('/platform/login', ['email' => "tebakan-{$i}@sapi.test", 'password' => 'salah']);
    }

    // Langit-langit per-IP hanya berlaku di panel platform. Kalau ia ikut
    // mengunci login tenant, satu warung di balik satu IP bisa lumpuh.
    post('/login', ['email' => 'kasir@usaha.test', 'password' => 'rahasia123'])
        ->assertSessionHasNoErrors()
        ->assertRedirect();
});

// ── Login mobile API ─────────────────────────────────────────────────────────
test('mobile login returns 429 after the limit', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir@usaha.test', 'password' => 'rahasia123']);

    foreach (range(1, 5) as $ignored) {
        post('/api/v1/mobile/login', ['email' => 'kasir@usaha.test', 'password' => 'salah']);
    }

    post('/api/v1/mobile/login', ['email' => 'kasir@usaha.test', 'password' => 'salah'])
        ->assertStatus(429);
});

test('mobile login throttle is keyed per email', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir-a@usaha.test', 'password' => 'rahasia123']);
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir-b@usaha.test', 'password' => 'rahasia123']);

    foreach (range(1, 6) as $ignored) {
        post('/api/v1/mobile/login', ['email' => 'kasir-a@usaha.test', 'password' => 'salah']);
    }

    post('/api/v1/mobile/login', ['email' => 'kasir-b@usaha.test', 'password' => 'rahasia123'])
        ->assertStatus(200);
});
