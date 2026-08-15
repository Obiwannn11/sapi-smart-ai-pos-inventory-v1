<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\PermissionCatalogSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Halaman Staf harus menjawab "orang ini bisa membuka apa saja" (`[BL-038]`),
 * bukan hanya menyebut nama rolenya dan menyuruh owner mencocokkan sendiri di
 * halaman Role.
 */
beforeEach(function () {
    $this->seed(PermissionCatalogSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * @return array{tenant: Tenant, owner: User}
 */
function staffPageContext(): array
{
    $tenant = Tenant::factory()->create();

    // Seat dilonggarkan supaya berkas ini menguji tampilan modul, bukan batas
    // seat — paket dasar (1 seat) akan menolak staf kedua.
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
    ];
}

function staffWithModules(Tenant $tenant, string $name, string $roleName, array $modules): User
{
    $user = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
        'name' => $name,
    ]);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $role = Role::findOrCreate($roleName, 'web');
    $role->syncPermissions($modules);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $user;
}

// ── Modul efektif per orang ──────────────────────────────────────────────────

test('each staff row carries the modules that person can actually open', function () {
    ['tenant' => $tenant, 'owner' => $owner] = staffPageContext();
    staffWithModules($tenant, 'Ani', 'Supervisor', ['pos', 'reports']);

    actingAs($owner);

    // Tabelnya ditunda ([BL-037]) — barisnya datang di permintaan kedua.
    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->missing('staff')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('staff.0.name', 'Ani')
            ->where('staff.0.roles.0', 'Supervisor')
            ->where('staff.0.modules', ['pos', 'reports'])));
});

test('a staff member without any role gets an empty module list, not a full one', function () {
    ['tenant' => $tenant, 'owner' => $owner] = staffPageContext();
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier', 'name' => 'Budi']);

    actingAs($owner);

    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('staff.0.name', 'Budi')
            ->where('staff.0.modules', [])));
});

test('revoking a module from the role changes what the page reports', function () {
    ['tenant' => $tenant, 'owner' => $owner] = staffPageContext();
    staffWithModules($tenant, 'Ani', 'Supervisor', ['pos', 'reports', 'stock']);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    Role::findOrCreate('Supervisor', 'web')->syncPermissions(['pos']);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    actingAs($owner);

    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('staff.0.modules', ['pos'])));
});

// ── Baris owner ──────────────────────────────────────────────────────────────

test('the owner appears on the page as a row of their own', function () {
    ['owner' => $owner] = staffPageContext();

    actingAs($owner);

    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('owners', 1)
            ->where('owners.0.id', $owner->id)
            ->where('owners.0.email', $owner->email)));
});

test('the owner row says bypass, not a list of every module', function () {
    ['tenant' => $tenant, 'owner' => $owner] = staffPageContext();

    // Kalaupun owner kebetulan memegang role, jawabannya tetap `['*']`: aksesnya
    // datang dari `Gate::before`, bukan dari rolenya. Membedakan keduanya di
    // layar adalah seluruh maksud entri ini.
    staffWithModules($tenant, 'tak dipakai', 'Supervisor', ['pos']);
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $owner->assignRole('Supervisor');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    actingAs($owner);

    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('owners.0.modules', ['*'])));
});

test('a second owner of the same business is listed too', function () {
    ['tenant' => $tenant, 'owner' => $owner] = staffPageContext();
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner);

    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload->has('owners', 2)));
});

test('owners of another tenant never leak into the list', function () {
    ['owner' => $owner] = staffPageContext();
    ['owner' => $otherOwner] = staffPageContext();

    actingAs($owner);

    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('owners', 1)
            ->where('owners.0.id', $owner->id)));

    expect($otherOwner->tenant_id)->not->toBe($owner->tenant_id);
});

// ── Katalog label ────────────────────────────────────────────────────────────

test('the module label catalog is sent so the page never hardcodes a second list', function () {
    ['owner' => $owner] = staffPageContext();

    actingAs($owner);

    get('/owner/staff')->assertInertia(fn (Assert $page) => $page
        ->has('modules', count(config('rbac.modules')))
        ->where('modules.0.name', 'pos')
        ->where('modules.0.label', config('rbac.modules.pos.label')));
});
