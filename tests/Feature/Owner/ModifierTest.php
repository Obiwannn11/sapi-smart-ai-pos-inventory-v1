<?php

use App\Models\ModifierGroup;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('owner can view modifier groups', function () {
    $this->actingAs($this->owner)
        ->get('/owner/modifiers')
        ->assertStatus(200);
});

test('owner can create modifier group with modifiers', function () {
    $this->actingAs($this->owner)
        ->post('/owner/modifiers', [
            'name' => 'Level Pedas',
            'is_required' => false,
            'is_multiple' => false,
            'modifiers' => [
                ['name' => 'Pedas', 'extra_price' => 0],
                ['name' => 'Extra Pedas', 'extra_price' => 2000],
            ],
        ])
        ->assertSessionHas('success');

    $this->assertDatabaseHas('modifier_groups', [
        'tenant_id' => $this->tenant->id,
        'name' => 'Level Pedas',
    ]);

    $this->assertDatabaseHas('modifiers', ['name' => 'Pedas']);
    $this->assertDatabaseHas('modifiers', ['name' => 'Extra Pedas']);
});

test('owner can delete modifier group (soft delete)', function () {
    $group = ModifierGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->owner)
        ->delete("/owner/modifiers/{$group->id}")
        ->assertSessionHas('success');

    $this->assertSoftDeleted('modifier_groups', ['id' => $group->id]);
});
