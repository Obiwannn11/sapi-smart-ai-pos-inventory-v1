<?php

use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{tenant: Tenant, owner: User, cashier: User}
 */
function makeAuthContext(): array
{
    $tenant = Tenant::factory()->create();

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    $cashier = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
    ]);

    return [
        'tenant' => $tenant,
        'owner' => $owner,
        'cashier' => $cashier,
    ];
}

test('login page is accessible', function () {
    get('/login')->assertStatus(200);
});

test('owner can login and is redirected to dashboard', function () {
    ['owner' => $owner] = makeAuthContext();

    post('/login', [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertRedirect('/owner/dashboard');
});

test('cashier can login and is redirected to POS', function () {
    ['cashier' => $cashier] = makeAuthContext();

    post('/login', [
        'email' => $cashier->email,
        'password' => 'password',
    ])->assertRedirect('/cashier/pos');
});

test('login fails with wrong credentials', function () {
    ['owner' => $owner] = makeAuthContext();

    post('/login', [
        'email' => $owner->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

test('cashier cannot access owner routes', function () {
    ['cashier' => $cashier] = makeAuthContext();

    actingAs($cashier);

    get('/owner/dashboard')->assertStatus(403);
});

test('owner can access cashier routes', function () {
    ['owner' => $owner] = makeAuthContext();

    actingAs($owner);

    get('/cashier/cash-drawer')->assertStatus(200);
});

test('unauthenticated user is redirected to login', function () {
    get('/owner/dashboard')->assertRedirect('/login');
});

test('logout clears session', function () {
    ['owner' => $owner] = makeAuthContext();

    actingAs($owner);
    post('/logout')->assertRedirect('/login');

    get('/owner/dashboard')->assertRedirect('/login');
});

test('register page is accessible', function () {
    get('/register')->assertStatus(200);
});

test('a new user can register a tenant and is redirected to owner dashboard', function () {
    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Budi',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/owner/dashboard');

    $user = User::where('email', 'budi@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->role)->toBe('owner');
    expect($user->tenant)->not->toBeNull();
    expect($user->tenant->name)->toBe('Warung Sapi');
});

test('registration fails with mismatched password confirmation', function () {
    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Budi',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'different',
    ])->assertSessionHasErrors('password');
});

test('registration fails with duplicate email', function () {
    ['owner' => $owner] = makeAuthContext();

    post('/register', [
        'business_name' => 'Warung Baru',
        'name' => 'Budi',
        'email' => $owner->email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors('email');
});

test('two tenants with the same business name get unique slugs', function () {
    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Budi',
        'email' => 'budi@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    post('/logout');

    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Ani',
        'email' => 'ani@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $tenants = Tenant::where('name', 'Warung Sapi')->pluck('slug');

    expect($tenants)->toHaveCount(2);
    expect($tenants->unique())->toHaveCount(2);
});
