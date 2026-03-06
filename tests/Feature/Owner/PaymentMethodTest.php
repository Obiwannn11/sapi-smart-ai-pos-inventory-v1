<?php

use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\User;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('owner can view payment methods', function () {
    $this->actingAs($this->owner)
        ->get('/owner/payment-methods')
        ->assertStatus(200);
});

test('owner can create payment method', function () {
    $this->actingAs($this->owner)
        ->post('/owner/payment-methods', [
            'name' => 'QRIS',
            'type' => 'qris_static',
            'is_active' => true,
        ])
        ->assertSessionHas('success');

    $this->assertDatabaseHas('payment_methods', [
        'tenant_id' => $this->tenant->id,
        'name' => 'QRIS',
        'type' => 'qris_static',
    ]);
});

test('owner can update payment method', function () {
    $pm = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Cash',
    ]);

    $this->actingAs($this->owner)
        ->put("/owner/payment-methods/{$pm->id}", [
            'name' => 'Tunai',
            'type' => 'cash',
            'is_active' => true,
        ])
        ->assertSessionHas('success');

    expect($pm->fresh()->name)->toBe('Tunai');
});

test('owner can delete payment method (soft delete)', function () {
    $pm = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
    ]);

    $this->actingAs($this->owner)
        ->delete("/owner/payment-methods/{$pm->id}")
        ->assertSessionHas('success');

    $this->assertSoftDeleted('payment_methods', ['id' => $pm->id]);
});
