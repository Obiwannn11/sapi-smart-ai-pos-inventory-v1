<?php

use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);
});

test('owner can generate an mcp token that is flashed once', function () {
    $this->post(route('owner.settings.mcp-token.generate'))
        ->assertRedirect()
        ->assertSessionHas('mcpToken');

    expect($this->owner->tokens()->where('name', 'mcp-client')->count())->toBe(1);
});

test('generating a token again rotates the previous one', function () {
    $this->post(route('owner.settings.mcp-token.generate'));
    $this->post(route('owner.settings.mcp-token.generate'));

    expect($this->owner->tokens()->where('name', 'mcp-client')->count())->toBe(1);
});

test('owner can revoke the mcp token', function () {
    $this->owner->createToken('mcp-client', ['mcp:use']);

    $this->delete(route('owner.settings.mcp-token.revoke'))->assertRedirect();

    expect($this->owner->tokens()->where('name', 'mcp-client')->count())->toBe(0);
});

test('settings index exposes mcp token status and endpoint but not the token', function () {
    $this->owner->createToken('mcp-client', ['mcp:use']);

    $this->get(route('owner.settings.index'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Owner/Settings/Index')
                ->where('mcp.token_set', true)
                ->where('mcp.endpoint', url('/mcp/business'))
        );
});

test('cashier cannot access owner settings', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->actingAs($cashier)
        ->post(route('owner.settings.mcp-token.generate'))
        ->assertForbidden();
});
