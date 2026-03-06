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

test('owner can view daily report', function () {
    $this->actingAs($this->owner)
        ->get('/owner/reports/daily')
        ->assertStatus(200);
});

test('owner can view daily report with date filter', function () {
    $this->actingAs($this->owner)
        ->get('/owner/reports/daily?date=2026-03-06')
        ->assertStatus(200);
});

test('owner can view transaction history', function () {
    $this->actingAs($this->owner)
        ->get('/owner/transactions')
        ->assertStatus(200);
});

test('owner can view cash drawer history', function () {
    $this->actingAs($this->owner)
        ->get('/owner/cash-drawers')
        ->assertStatus(200);
});
