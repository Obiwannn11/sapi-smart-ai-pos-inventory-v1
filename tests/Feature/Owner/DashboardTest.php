<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('owner can view dashboard', function () {
    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertStatus(200);
});

test('cashier cannot access dashboard', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->actingAs($cashier)
        ->get('/owner/dashboard')
        ->assertStatus(403);
});
