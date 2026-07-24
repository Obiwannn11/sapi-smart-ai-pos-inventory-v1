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
use function Pest\Laravel\post;

beforeEach(function () {
    // Seed the global module catalog so roles can be granted these permissions.
    $this->seed(PermissionCatalogSeeder::class);
    app(PermissionRegistrar::class)->forgetCachedPermissions();
});

/**
 * @return array{tenant: Tenant, owner: User, cashier: User}
 */
function rbacContext(): array
{
    $tenant = Tenant::factory()->create();

    // Seat dilonggarkan supaya berkas ini menguji RBAC, bukan batas seat.
    // Tanpa ini paket dasar (1 seat) menolak staf kedua, dan kegagalannya akan
    // terbaca seolah-olah izin modul yang rusak.
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'cashier' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']),
    ];
}

function grantRole(User $user, string $roleName, array $modules): Role
{
    app(PermissionRegistrar::class)->setPermissionsTeamId($user->tenant_id);
    $role = Role::findOrCreate($roleName, 'web');
    $role->syncPermissions($modules);
    $user->assignRole($role);
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    return $role;
}

// ── Owner bypass ─────────────────────────────────────────────────────────────
test('owner without any spatie role can access a gated owner module', function () {
    ['owner' => $owner] = rbacContext();

    actingAs($owner);

    get('/owner/stock')->assertStatus(200);
    get('/owner/products')->assertStatus(200);
});

// ── Staff without permission ─────────────────────────────────────────────────
test('cashier without permission is denied owner modules but keeps the cashier shell', function () {
    ['cashier' => $cashier] = rbacContext();

    actingAs($cashier);

    get('/owner/stock')->assertStatus(403);
    // Cashier routes are never RBAC-gated: POS just redirects to open a cash
    // session first, and the cash drawer shell is reachable.
    get('/cashier/pos')->assertRedirect('/cashier/cash-drawer');
    get('/cashier/cash-drawer')->assertStatus(200);
});

// ── Staff with permission ────────────────────────────────────────────────────
test('cashier with a granted module can open only that module', function () {
    ['cashier' => $cashier] = rbacContext();
    grantRole($cashier, 'Kasir Gudang', ['pos', 'stock']);

    actingAs($cashier);

    get('/owner/stock')->assertStatus(200);
    get('/owner/products')->assertStatus(403);
});

// ── Tenant isolation ─────────────────────────────────────────────────────────
test('roles are isolated per tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $cashierA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'cashier']);
    $cashierB = User::factory()->create(['tenant_id' => $tenantB->id, 'role' => 'cashier']);

    grantRole($cashierA, 'Gudang', ['pos', 'stock']);

    // Cashier B has no role at all — the tenant-A "Gudang" role must not leak.
    actingAs($cashierB);
    get('/owner/stock')->assertStatus(403);

    // Cashier A keeps access within its own tenant.
    actingAs($cashierA);
    get('/owner/stock')->assertStatus(200);
});

// ── Nav share ────────────────────────────────────────────────────────────────
test('inertia shares owner permissions as wildcard', function () {
    ['owner' => $owner] = rbacContext();

    actingAs($owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('auth.user.permissions', ['*']));
});

test('inertia shares only granted modules for staff', function () {
    ['cashier' => $cashier] = rbacContext();
    grantRole($cashier, 'Kasir Gudang', ['pos', 'stock']);

    // Hit a module page the staff may open so the shared props are rendered.
    actingAs($cashier)
        ->get('/owner/stock')
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.permissions', fn ($perms) => collect($perms)->sort()->values()->all() === ['pos', 'stock']));
});

// ── Staff CRUD ───────────────────────────────────────────────────────────────
test('owner can create a staff account and assign a role', function () {
    ['tenant' => $tenant, 'owner' => $owner] = rbacContext();
    grantRole(User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']), 'Kasir', ['pos']);

    actingAs($owner)
        ->post('/owner/staff', [
            'name' => 'Staf Baru',
            'email' => 'staf.baru@usaha.test',
            'password' => 'password123',
            'role_name' => 'Kasir',
        ])
        ->assertRedirect();

    $created = User::where('email', 'staf.baru@usaha.test')->first();
    expect($created)->not->toBeNull();
    expect($created->role)->toBe('cashier');
    expect($created->tenant_id)->toBe($tenant->id);

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    expect($created->fresh()->hasRole('Kasir'))->toBeTrue();
});

test('staff page role list and assigned roles are scoped to the tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $ownerA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'owner']);
    $cashierA = User::factory()->create(['tenant_id' => $tenantA->id, 'role' => 'cashier']);

    grantRole($cashierA, 'Gudang A', ['pos', 'stock']);

    // A role that belongs to tenant B must never surface for tenant A.
    app(PermissionRegistrar::class)->setPermissionsTeamId($tenantB->id);
    Role::findOrCreate('Rahasia B', 'web');
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    actingAs($ownerA)
        ->get('/owner/staff')
        ->assertInertia(fn (Assert $page) => $page
            ->where('roles', fn ($roles) => collect($roles)->contains('Gudang A') && ! collect($roles)->contains('Rahasia B'))
            ->where('staff', fn ($staff) => collect($staff)->firstWhere('id', $cashierA->id)['roles'] === ['Gudang A']));
});

test('non-owner cannot access staff management', function () {
    ['cashier' => $cashier] = rbacContext();

    actingAs($cashier);
    get('/owner/staff')->assertStatus(403);
    post('/owner/staff', [
        'name' => 'X',
        'email' => 'x@x.test',
        'password' => 'password123',
    ])->assertStatus(403);
});

// ── Role CRUD ────────────────────────────────────────────────────────────────
test('owner can create a role with a subset of modules', function () {
    ['tenant' => $tenant, 'owner' => $owner] = rbacContext();

    actingAs($owner)
        ->post('/owner/roles', [
            'name' => 'Supervisor',
            'modules' => ['reports', 'stock'],
        ])
        ->assertRedirect();

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    $role = Role::where('tenant_id', $tenant->id)->where('name', 'Supervisor')->first();
    expect($role)->not->toBeNull();
    expect($role->permissions->pluck('name')->sort()->values()->all())->toBe(['reports', 'stock']);
});

test('role creation rejects modules outside the catalog', function () {
    ['owner' => $owner] = rbacContext();

    actingAs($owner)
        ->post('/owner/roles', [
            'name' => 'Hacker',
            'modules' => ['settings'],
        ])
        ->assertSessionHasErrors('modules.0');
});

test('deleting a role that is still in use is blocked', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'cashier' => $cashier] = rbacContext();
    $role = grantRole($cashier, 'Kasir', ['pos']);

    actingAs($owner)
        ->delete("/owner/roles/{$role->id}")
        ->assertSessionHas('error');

    app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    expect(Role::find($role->id))->not->toBeNull();
});
