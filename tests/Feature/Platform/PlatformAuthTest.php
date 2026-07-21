<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

// ── Gerbang masuk ────────────────────────────────────────────────────────────
test('guest is redirected to the platform login page', function () {
    get('/platform')->assertRedirect('/platform/login');
    get('/platform/tenants')->assertRedirect('/platform/login');
});

test('platform user can log in and reach the dashboard', function () {
    PlatformUser::factory()->withAllModules()->create([
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ]);

    post('/platform/login', [
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ])->assertRedirect('/platform');

    get('/platform')->assertStatus(200);
});

test('wrong credentials are rejected', function () {
    PlatformUser::factory()->create(['email' => 'pemilik@sapi.test', 'password' => 'rahasia123']);

    post('/platform/login', [
        'email' => 'pemilik@sapi.test',
        'password' => 'salah',
    ])->assertSessionHasErrors('email');

    get('/platform')->assertRedirect('/platform/login');
});

// ── Dua dunia tidak boleh saling menyeberang ─────────────────────────────────
test('tenant user cannot reach the platform console', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    // Owner tenant adalah super-admin DI DALAM tenantnya, bukan di atas platform.
    actingAs($owner);

    get('/platform')->assertRedirect('/platform/login');
    get('/platform/tenants')->assertRedirect('/platform/login');
});

test('platform user cannot reach tenant routes', function () {
    $platformUser = PlatformUser::factory()->withAllModules()->create();

    actingAs($platformUser, 'platform');

    // Ditolak oleh EnsureTenant: akun platform tidak terhubung ke tenant mana pun.
    // Sengaja di-assert sebagai "ditolak", bukan kode status tertentu — di
    // peramban sungguhan guard web yang menolak lebih dulu (redirect ke /login),
    // sedangkan di test guard platform masih jadi default sehingga gerbang tenant
    // yang menolak. Dua jalur berbeda, dan yang penting hasilnya sama: tidak lolos.
    get('/owner/dashboard')->assertForbidden();
    get('/cashier/pos')->assertForbidden();
});

// ── Gerbang per modul ────────────────────────────────────────────────────────
test('platform user without the tenants module is denied that module', function () {
    // Punya akun platform yang sah, tapi tidak dicentang modul `tenants`.
    $platformUser = PlatformUser::factory()->withModules(['payments'])->create();

    actingAs($platformUser, 'platform');

    get('/platform')->assertStatus(200);        // beranda tetap boleh
    get('/platform/tenants')->assertStatus(403); // modulnya tidak
});

test('platform user with the tenants module can open it', function () {
    $platformUser = PlatformUser::factory()->withModules(['tenants'])->create();

    actingAs($platformUser, 'platform');

    get('/platform/tenants')->assertStatus(200);
});

// ── Audit log ────────────────────────────────────────────────────────────────
test('successful and failed logins are both recorded', function () {
    $platformUser = PlatformUser::factory()->create([
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ]);

    post('/platform/login', ['email' => 'pemilik@sapi.test', 'password' => 'salah']);
    post('/platform/login', ['email' => 'pemilik@sapi.test', 'password' => 'rahasia123']);

    $failed = PlatformAuditLog::where('action', 'login.failed')->first();
    expect($failed)->not->toBeNull();
    expect($failed->platform_user_id)->toBeNull();
    expect($failed->meta['email'])->toBe('pemilik@sapi.test');

    $success = PlatformAuditLog::where('action', 'login.success')->first();
    expect($success)->not->toBeNull();
    expect($success->platform_user_id)->toBe($platformUser->id);
});
