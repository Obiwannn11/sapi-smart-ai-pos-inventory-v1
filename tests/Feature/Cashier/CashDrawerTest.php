<?php

use App\Models\CashDrawer;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
});

test('cashier can open cash drawer', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/open', [
            'opening_amount' => 500000,
        ])
        ->assertRedirect(route('cashier.pos'));

    $this->assertDatabaseHas('cash_drawers', [
        'user_id' => $this->cashier->id,
        'opening_amount' => 500000,
        'closed_at' => null,
    ]);
});

test('cashier cannot open second cash drawer while one is open', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/open', [
            'opening_amount' => 500000,
        ])
        ->assertSessionHas('error');
});

test('cashier can close cash drawer', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opening_amount' => 500000,
        'opened_at' => now()->subHours(8),
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/close', [
            'closing_amount' => 750000,
            'notes' => 'Shift selesai',
        ])
        ->assertSessionHas('success');

    $drawer = CashDrawer::where('user_id', $this->cashier->id)->first();
    $this->assertNotNull($drawer->closed_at);
    $this->assertNotNull($drawer->expected_amount);
});

test('cashier can view cash drawer summary', function () {
    $drawer = CashDrawer::factory()->closed()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
    ]);

    $this->actingAs($this->cashier)
        ->get("/cashier/cash-drawer/{$drawer->id}/summary")
        ->assertStatus(200);
});

test('cashier cannot view other cashier summary', function () {
    $otherCashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $drawer = CashDrawer::factory()->closed()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $otherCashier->id,
    ]);

    $this->actingAs($this->cashier)
        ->get("/cashier/cash-drawer/{$drawer->id}/summary")
        ->assertStatus(403);
});

test('cash drawer index shows form when no open session', function () {
    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer')
        ->assertStatus(200);
});
