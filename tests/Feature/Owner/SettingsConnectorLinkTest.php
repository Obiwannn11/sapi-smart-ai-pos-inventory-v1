<?php

use App\Models\Tenant;
use App\Models\User;

/**
 * Pengelolaan link konektor di halaman Integrasi — `[BL-102]`.
 *
 * Link adalah token Sanctum ber-ability `connector:read` dengan masa berlaku
 * wajib. Yang dijaga berkas ini: bentuk token yang diterbitkan, pagar
 * persetujuan di server, dan bahwa rute cabut tidak bisa dipakai menghapus
 * token lain (MCP, login kasir, milik owner lain).
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);
});

test('owner can create a connector link that is flashed once', function (int $days) {
    $this->freezeSecond();

    $this->post(route('owner.settings.integrations.connector-links.store'), [
        'label' => 'ChatGPT',
        'lifetime_days' => $days,
        'acknowledged' => true,
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors()
        ->assertSessionHas('connectorLink');

    $token = $this->owner->tokens()->sole();

    expect($token->name)->toBe('ChatGPT')
        ->and($token->abilities)->toBe(['connector:read'])
        ->and($token->expires_at->equalTo(now()->addDays($days)))->toBeTrue();

    // `|` pada token di-encode, supaya pengambil halaman AI menerima link utuh.
    expect(session('connectorLink.url'))
        ->toStartWith(url('/api/v1/connector/summary').'?token=')
        ->toContain('%7C')
        ->not->toContain('|');
})->with([7, 30]);

test('a link cannot be created without acknowledging who can read it', function () {
    $this->post(route('owner.settings.integrations.connector-links.store'), [
        'label' => 'ChatGPT',
        'lifetime_days' => 30,
    ])->assertSessionHasErrors('acknowledged');

    expect($this->owner->tokens()->count())->toBe(0);
});

test('only the offered lifetimes are accepted', function (mixed $days) {
    $this->post(route('owner.settings.integrations.connector-links.store'), [
        'label' => 'ChatGPT',
        'lifetime_days' => $days,
        'acknowledged' => true,
    ])->assertSessionHasErrors('lifetime_days');

    expect($this->owner->tokens()->count())->toBe(0);
})->with([
    'setahun' => 365,
    'kosong' => null,
]);

test('a link cannot take a name that other code revokes by name', function (string $label) {
    $this->post(route('owner.settings.integrations.connector-links.store'), [
        'label' => $label,
        'lifetime_days' => 30,
        'acknowledged' => true,
    ])->assertSessionHasErrors('label');

    expect($this->owner->tokens()->count())->toBe(0);
})->with(['mcp-client', 'mobile-app']);

test('integrations page lists active connector links without their secret', function () {
    $this->owner->createToken('mcp-client', ['mcp:use']);
    $this->owner->createToken('mobile-app');
    $this->owner->createToken('Lama', ['connector:read'], now()->subDay());
    $active = $this->owner->createToken('Gemini', ['connector:read'], now()->addDays(7))->accessToken;

    $this->get(route('owner.settings.integrations.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Settings/Integrations')
            ->where('connector.endpoint', url('/api/v1/connector/summary'))
            ->where('connector.lifetimes', [7, 30])
            ->has('connector.links', 1)
            ->where('connector.links.0.id', $active->id)
            ->where('connector.links.0.label', 'Gemini')
            ->where('connector.links.0.last_used_at', null)
            ->missing('connector.links.0.token')
        );
});

test('owner can revoke a connector link', function () {
    $link = $this->owner->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->accessToken;

    $this->delete(route('owner.settings.integrations.connector-links.destroy', $link->id))
        ->assertRedirect();

    expect($this->owner->tokens()->count())->toBe(0);
});

test('the revoke route cannot remove tokens that are not connector links', function () {
    $mcp = $this->owner->createToken('mcp-client', ['mcp:use'])->accessToken;
    $mobile = $this->owner->createToken('mobile-app')->accessToken;

    $this->delete(route('owner.settings.integrations.connector-links.destroy', $mcp->id))->assertNotFound();
    $this->delete(route('owner.settings.integrations.connector-links.destroy', $mobile->id))->assertNotFound();

    expect($this->owner->tokens()->count())->toBe(2);
});

test('an owner cannot revoke a link that belongs to someone else', function () {
    $otherOwner = User::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'role' => 'owner',
    ]);
    $link = $otherOwner->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->accessToken;

    $this->delete(route('owner.settings.integrations.connector-links.destroy', $link->id))->assertNotFound();

    expect($otherOwner->tokens()->count())->toBe(1);
});

test('cashier cannot create connector links', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->actingAs($cashier)
        ->post(route('owner.settings.integrations.connector-links.store'), [
            'label' => 'ChatGPT',
            'lifetime_days' => 30,
            'acknowledged' => true,
        ])
        ->assertForbidden();

    expect($cashier->tokens()->count())->toBe(0);
});
