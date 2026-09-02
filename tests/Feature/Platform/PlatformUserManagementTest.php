<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

// ── Gerbang: pemilik saja ────────────────────────────────────────────────────
test('platform owner can open account management', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');

    get('/platform/users')->assertStatus(200);
});

test('platform staff cannot open account management even with every module', function () {
    // Justru inilah alasan manajemen akun BUKAN modul grantable: kalau ia
    // grantable, staf di bawah ini bisa mencentangkan `revenue_data` untuk
    // dirinya sendiri dan pemisahan modul sensitif jadi tak ada artinya.
    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');

    get('/platform/users')->assertStatus(403);
    post('/platform/users', ['name' => 'X', 'email' => 'x@sapi.test', 'password' => 'rahasia123'])
        ->assertStatus(403);
});

// ── Buat akun ────────────────────────────────────────────────────────────────
test('owner can create a staff account with selected modules', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');

    post('/platform/users', [
        'name' => 'Staf Platform',
        'email' => 'staf@sapi.test',
        'password' => 'rahasia123',
        'modules' => ['tenants'],
    ])->assertRedirect();

    $created = PlatformUser::where('email', 'staf@sapi.test')->first();

    expect($created)->not->toBeNull();
    expect($created->is_owner)->toBeFalse();
    expect($created->moduleNames())->toBe(['tenants']);
});

test('a created account is never an owner even if the payload says so', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');

    post('/platform/users', [
        'name' => 'Penyusup',
        'email' => 'penyusup@sapi.test',
        'password' => 'rahasia123',
        'is_owner' => true,
    ])->assertRedirect();

    expect(PlatformUser::where('email', 'penyusup@sapi.test')->first()->is_owner)->toBeFalse();
});

test('modules without a page cannot be granted', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');

    // Sejak Tahap D SELURUH modul di katalog sudah punya halaman, jadi tidak
    // ada lagi contoh alami untuk diuji. Flag-nya ditandai di sini supaya
    // mekanismenya tetap terjaga untuk modul berikutnya yang katalognya
    // ditetapkan lebih dulu daripada halamannya.
    config()->set('platform-rbac.modules.tenants.available', false);

    post('/platform/users', [
        'name' => 'Staf',
        'email' => 'staf@sapi.test',
        'password' => 'rahasia123',
        'modules' => ['tenants'],
    ])->assertSessionHasErrors('modules.0');
});

// ── Ubah akun ────────────────────────────────────────────────────────────────
test('owner can change the modules of a staff account', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');
    $staff = PlatformUser::factory()->withModules(['tenants'])->create();

    put("/platform/users/{$staff->id}", [
        'name' => $staff->name,
        'email' => $staff->email,
        'modules' => [],
    ])->assertRedirect();

    expect($staff->fresh()->moduleNames())->toBe([]);
    // Modulnya dicabut, jadi daftar tenant ikut tertutup.
    actingAs($staff->fresh(), 'platform');
    get('/platform/tenants')->assertStatus(403);
});

// ── Penjaga terhadap terkunci sendiri ────────────────────────────────────────
test('owner cannot delete their own account', function () {
    $owner = PlatformUser::factory()->owner()->create();
    actingAs($owner, 'platform');

    delete("/platform/users/{$owner->id}")->assertSessionHas('error');

    expect(PlatformUser::find($owner->id))->not->toBeNull();
});

test('the last owner account cannot be deleted', function () {
    $owner = PlatformUser::factory()->owner()->create();
    $otherOwner = PlatformUser::factory()->owner()->create();

    actingAs($owner, 'platform');

    // Masih ada dua pemilik — boleh.
    delete("/platform/users/{$otherOwner->id}")->assertSessionHas('success');

    // Kini tersisa satu; menghapusnya lewat akun lain pun harus ditolak.
    $staffOwner = PlatformUser::factory()->owner()->create();
    actingAs($staffOwner, 'platform');
    delete("/platform/users/{$owner->id}")->assertSessionHas('success'); // masih dua pemilik

    actingAs($staffOwner, 'platform');
    delete("/platform/users/{$staffOwner->id}")->assertSessionHas('error'); // dirinya sendiri
});

// ── Katalog & audit ──────────────────────────────────────────────────────────
test('the module catalog marks which modules have no page yet', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');

    config()->set('platform-rbac.modules.pricing_rules.available', false);

    get('/platform/users')->assertInertia(fn (Assert $page) => $page
        ->component('Platform/Users/Index')
        ->where('modules', fn ($modules) => collect($modules)->firstWhere('name', 'tenants')['available'] === true
            && collect($modules)->firstWhere('name', 'pricing_rules')['available'] === false));
});

test('every module in the catalog now has a page', function () {
    // Penjaga arah sebaliknya: sejak Tahap D tidak ada lagi modul yang
    // katalognya ada tapi halamannya belum. Kalau kelak ada modul baru yang
    // ditambahkan lebih dulu ke katalog, test ini merah dan mengingatkan untuk
    // menandainya `available => false` — bukan membiarkannya bisa dicentang
    // tanpa berefek apa pun.
    $tanpaHalaman = collect(config('platform-rbac.modules'))
        ->reject(fn (array $module) => $module['available'])
        ->keys();

    expect($tanpaHalaman)->toBeEmpty();
});

test('account changes are written to the audit log', function () {
    actingAs(PlatformUser::factory()->owner()->create(), 'platform');

    post('/platform/users', [
        'name' => 'Staf',
        'email' => 'staf@sapi.test',
        'password' => 'rahasia123',
        'modules' => ['tenants'],
    ]);

    expect(PlatformAuditLog::where('action', 'platform_users.create')->exists())->toBeTrue();
});

// ── Owner tak perlu dicentangkan modul ───────────────────────────────────────
test('owner reaches module-gated pages without any module row', function () {
    $owner = PlatformUser::factory()->owner()->create();

    expect($owner->modules()->count())->toBe(0);

    actingAs($owner, 'platform');
    get('/platform/tenants')->assertStatus(200);
});
