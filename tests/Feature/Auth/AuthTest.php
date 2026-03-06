<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
});

test('login page is accessible', function () {
    $this->get('/login')->assertStatus(200);
});

test('owner can login and is redirected to dashboard', function () {
    $this->post('/login', [
        'email' => $this->owner->email,
        'password' => 'password',
    ])->assertRedirect('/owner/dashboard');
});

test('cashier can login and is redirected to POS', function () {
    $this->post('/login', [
        'email' => $this->cashier->email,
        'password' => 'password',
    ])->assertRedirect('/cashier/pos');
});

test('login fails with wrong credentials', function () {
    $this->post('/login', [
        'email' => $this->owner->email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors('email');
});

test('cashier cannot access owner routes', function () {
    $this->actingAs($this->cashier)
        ->get('/owner/dashboard')
        ->assertStatus(403);
});

test('owner can access cashier routes', function () {
    $this->actingAs($this->owner)
        ->get('/cashier/cash-drawer')
        ->assertStatus(200);
});

test('unauthenticated user is redirected to login', function () {
    $this->get('/owner/dashboard')
        ->assertRedirect('/login');
});

test('logout clears session', function () {
    $this->actingAs($this->owner)
        ->post('/logout')
        ->assertRedirect('/login');

    $this->get('/owner/dashboard')
        ->assertRedirect('/login');
});
