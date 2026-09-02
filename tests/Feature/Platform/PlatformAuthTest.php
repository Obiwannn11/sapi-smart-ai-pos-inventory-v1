<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

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

// ── Pengalihan setelah masuk (BL-043) ────────────────────────────────────────
// Guard, broker, dan pengalihan tamu sudah lama terpisah rapi; yang tidak
// terpisah adalah pengalihan SETELAH berhasil masuk. Dua jalur, dua gejala,
// keduanya berakhir di luar /platform.

test('a tenant destination left in the session does not hijack the platform login', function () {
    PlatformUser::factory()->withAllModules()->create([
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ]);

    // Persis jejak yang ditinggalkan peramban yang pernah membuka area tenant
    // dalam keadaan keluar: Authenticate menyimpannya, lalu tidak ada yang
    // membersihkannya. Sesi peramban yang bersih tidak pernah punya kunci ini,
    // dan itu sebabnya cacat ini lolos dari pengujian di jendela penyamaran.
    session(['url.intended' => url('/owner/dashboard')]);

    post('/platform/login', [
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ])->assertRedirect('/platform');

    // Ikut hangus, bukan sekadar diabaikan sekali.
    expect(session()->has('url.intended'))->toBeFalse();
});

test('a platform destination left in the session is still honoured', function () {
    PlatformUser::factory()->withAllModules()->create([
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ]);

    // Akun platform yang mengklik tautan langsung lalu diminta masuk tetap
    // layak dikembalikan ke tujuannya — pagarnya menyaring, bukan menghapus.
    session(['url.intended' => url('/platform/tenants')]);

    post('/platform/login', [
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ])->assertRedirect(url('/platform/tenants'));
});

test('a look-alike destination outside the platform prefix is rejected', function () {
    PlatformUser::factory()->withAllModules()->create([
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ]);

    // Dicocokkan sebagai segmen utuh: '/platformx' bukan area platform.
    session(['url.intended' => url('/platformx/anything')]);

    post('/platform/login', [
        'email' => 'pemilik@sapi.test',
        'password' => 'rahasia123',
    ])->assertRedirect('/platform');
});

test('the platform login page is reachable by guests', function () {
    get('/platform/login')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Platform/Login'));
});

test('an already signed-in platform user reopening the login lands on the console', function () {
    $platformUser = PlatformUser::factory()->withAllModules()->create();

    actingAs($platformUser, 'platform');

    // Tanpa redirectUsersTo, tujuan bawaan framework (rute `dashboard`/`home`)
    // tidak ketemu dan jatuh ke '/' — landing publik yang tombol utamanya
    // menuju login TENANT.
    get('/platform/login')->assertRedirect('/platform');
    get('/platform/forgot-password')->assertRedirect('/platform');
});

test('an already signed-in tenant user reopening the login lands in their own area', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    // Sisi tenant memakai callback yang sama, jadi pemilahan perannya ikut
    // diuji di sini — bukan dilempar ke landing seperti sebelumnya.
    actingAs($owner);
    get('/login')->assertRedirect(route('owner.dashboard'));

    actingAs($cashier);
    get('/login')->assertRedirect(route('cashier.pos'));
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
