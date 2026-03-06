<?php

use App\Models\Tenant;
use App\Models\User;
use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

$tenant = null;
$owner = null;
$cashier = null;

beforeEach(function () use (&$tenant, &$owner, &$cashier) {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);
    $cashier = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
    ]);
});

test('login page is accessible', function () {
    get('/login')->assertStatus(200);
});

test('owner can login and is redirected to dashboard', function () use (&$owner) {
    post('/login', [
        'email' => $owner->email,
        'password' => 'password',
    ])->assertRedirect('/owner/dashboard');
});

test('cashier can login and is redirected to POS', function () use (&$cashier) {
    post('/login', [
        'email' => $cashier->email,
        'password' => 'password',
    ])->assertRedirect('/cashier/pos');
});

test('login fails with wrong credentials', function () use (&$owner) {
    post('/login', [
        'email' => $owner->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

test('cashier cannot access owner routes', function () use (&$cashier) {
    actingAs($cashier);

    get('/owner/dashboard')->assertStatus(403);
});

test('owner can access cashier routes', function () use (&$owner) {
    actingAs($owner);

    get('/cashier/cash-drawer')->assertStatus(200);
});

test('unauthenticated user is redirected to login', function () {
    get('/owner/dashboard')->assertRedirect('/login');
});

test('logout clears session', function () use (&$owner) {
    actingAs($owner);
    post('/logout')->assertRedirect('/login');

    get('/owner/dashboard')->assertRedirect('/login');
});
