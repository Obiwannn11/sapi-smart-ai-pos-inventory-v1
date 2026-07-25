<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

beforeEach(function () {
    $this->seed(PermissionCatalogSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * @return array{tenant: Tenant, owner: User, cashier: User}
 */
function mobileRbacContext(string $suffix = ''): array
{
    $tenant = Tenant::factory()->active()->create();
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => "owner{$suffix}@usaha.test",
            'password' => 'rahasia123',
        ]),
        'cashier' => User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'cashier',
            'email' => "kasir{$suffix}@usaha.test",
            'password' => 'rahasia123',
        ]),
    ];
}

function grantModules(User $user, string $roleName, array $modules): void
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
    Role::findOrCreate($roleName, 'web')->syncPermissions($modules);
    $user->assignRole($roleName);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
}

function loginMobile(string $email): array
{
    return postJson('/api/v1/mobile/login', [
        'email' => $email,
        'password' => 'rahasia123',
    ])->json();
}

// --- Payload autentikasi ---

test('owner menerima tanda bypass, bukan daftar modul lengkap', function () {
    mobileRbacContext();

    // ['*'] bukan daftar penuh: owner lolos lewat Gate::before, jadi menyusun
    // daftar untuknya akan menyesatkan seolah daftar itu yang menentukan.
    expect(loginMobile('owner@usaha.test')['user']['permissions'])->toBe(['*']);
});

test('staf menerima daftar modul yang benar-benar dimilikinya', function () {
    ['cashier' => $cashier] = mobileRbacContext();
    grantModules($cashier, 'Kasir Gudang', ['pos', 'stock']);

    $permissions = loginMobile('kasir@usaha.test')['user']['permissions'];

    expect(collect($permissions)->sort()->values()->all())->toBe(['pos', 'stock']);
});

test('staf tanpa role menerima daftar kosong, bukan null', function () {
    mobileRbacContext();

    // Daftar kosong bisa dipetakan aplikasi jadi "sembunyikan semua menu
    // modul"; null menuntut penerimanya menebak maksudnya.
    expect(loginMobile('kasir@usaha.test')['user']['permissions'])->toBe([]);
});

test('izin dihitung dalam konteks tenant yang benar', function () {
    ['cashier' => $cashier] = mobileRbacContext();
    grantModules($cashier, 'Kasir Gudang', ['pos', 'stock']);

    // Tenant lain dengan nama role sama tapi modul berbeda. Endpoint login ada
    // DI LUAR middleware tenant.api, jadi kalau team-id spatie tidak disetel
    // sendiri di controller, jawabannya bisa datang dari tenant yang salah.
    ['cashier' => $lain] = mobileRbacContext('-lain');
    grantModules($lain, 'Kasir Gudang', ['reports']);

    expect(collect(loginMobile('kasir@usaha.test')['user']['permissions'])->sort()->values()->all())
        ->toBe(['pos', 'stock']);
});

// --- Penyegaran tanpa login ulang ---

test('profil tenant ikut mengembalikan izin', function () {
    ['cashier' => $cashier] = mobileRbacContext();
    grantModules($cashier, 'Kasir', ['pos']);

    Sanctum::actingAs($cashier);

    $this->getJson('/api/v1/mobile/tenant/profile')
        ->assertStatus(200)
        ->assertJsonPath('permissions', ['pos']);
});

test('perubahan role terlihat tanpa perlu login ulang', function () {
    ['cashier' => $cashier] = mobileRbacContext();
    grantModules($cashier, 'Kasir', ['pos']);

    Sanctum::actingAs($cashier);
    $this->getJson('/api/v1/mobile/tenant/profile')->assertJsonPath('permissions', ['pos']);

    // Owner mencabut modul saat kasir masih memegang tokennya. Token mobile
    // bertahan berminggu-minggu; tanpa jalan menyegarkan, aplikasi akan memakai
    // daftar usang sampai penggunanya kebetulan keluar-masuk.
    app(PermissionRegistrar::class)->setPermissionsTeamId($cashier->tenant_id);
    Role::findByName('Kasir', 'web')->syncPermissions(['stock']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->getJson('/api/v1/mobile/tenant/profile')->assertJsonPath('permissions', ['stock']);
});

// --- Satu sumber kebenaran ---

test('sisi web dan sisi mobile menjawab daftar izin yang sama', function () {
    ['cashier' => $cashier] = mobileRbacContext();
    grantModules($cashier, 'Kasir Gudang', ['pos', 'stock']);

    $dariMobile = loginMobile('kasir@usaha.test')['user']['permissions'];

    actingAs($cashier)
        ->get('/owner/stock')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.permissions', fn ($dariWeb) => collect($dariWeb)->sort()->values()->all()
                === collect($dariMobile)->sort()->values()->all()));
});

// --- Middleware permission.api ---

test('gerbang modul api menolak dengan json 403, bukan halaman html', function () {
    ['cashier' => $cashier] = mobileRbacContext();

    Route::middleware(['auth:sanctum', 'tenant.api', 'permission.api:stock'])
        ->get('/api/uji-gerbang-modul', fn () => response()->json(['ok' => true]));

    Sanctum::actingAs($cashier);

    $this->getJson('/api/uji-gerbang-modul')
        ->assertStatus(403)
        // Aplikasi kasir yang menerima 403 berbentuk HTML akan menampilkan
        // halaman error mentah alih-alih pesan yang bisa dibaca.
        ->assertJsonStructure(['message'])
        ->assertJsonFragment(['message' => 'Anda tidak memiliki akses ke modul Stok / Inventori.']);
});

test('gerbang modul api meloloskan staf yang memiliki modulnya', function () {
    ['cashier' => $cashier] = mobileRbacContext();
    grantModules($cashier, 'Gudang', ['stock']);

    Route::middleware(['auth:sanctum', 'tenant.api', 'permission.api:stock'])
        ->get('/api/uji-gerbang-modul', fn () => response()->json(['ok' => true]));

    Sanctum::actingAs($cashier);

    $this->getJson('/api/uji-gerbang-modul')->assertStatus(200)->assertJson(['ok' => true]);
});

test('owner lolos gerbang modul api tanpa perlu di-grant', function () {
    ['owner' => $owner] = mobileRbacContext();

    Route::middleware(['auth:sanctum', 'tenant.api', 'permission.api:stock'])
        ->get('/api/uji-gerbang-modul', fn () => response()->json(['ok' => true]));

    Sanctum::actingAs($owner);

    // Lewat Gate::before, sama seperti di web.
    $this->getJson('/api/uji-gerbang-modul')->assertStatus(200);
});

// --- Paritas dengan web ---

test('endpoint POS mobile tetap terbuka untuk kasir tanpa role, sama seperti web', function () {
    ['cashier' => $cashier] = mobileRbacContext();

    Sanctum::actingAs($cashier);

    // Keputusan 2026-07-25: modul `pos`/`cash_drawer` adalah penanda menu, bukan
    // gerbang rute — di web maupun mobile. Menggerbang di sini akan mengunci staf
    // yang dibuat lewat pilihan "Tanpa role (POS saja)" di form tambah staf,
    // dan membuat orang yang sama ditolak aplikasi tapi diterima peramban.
    $this->getJson('/api/v1/mobile/products')->assertStatus(200);
    $this->getJson('/api/v1/mobile/cash-drawer/status')->assertStatus(200);
});
