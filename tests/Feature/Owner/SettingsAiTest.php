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

test('ai_api_key is stored encrypted', function () {
    $this->patch(route('owner.settings.update'), [
        'ai_provider' => 'openai',
        'ai_api_key' => 'sk-super-secret',
        'ai_model' => 'gpt-4o-mini',
    ])->assertRedirect();

    $this->tenant->refresh();

    expect($this->tenant->ai_api_key)->toBe('sk-super-secret')
        ->and($this->tenant->getRawOriginal('ai_api_key'))->not->toBe('sk-super-secret');
});

test('settings props expose ai_key_set but never the key itself', function () {
    $this->tenant->update(['ai_api_key' => 'sk-hidden']);

    $this->get(route('owner.settings.index'))
        ->assertInertia(
            fn ($page) => $page
                ->component('Owner/Settings/Index')
                ->where('tenant.ai_key_set', true)
                ->missing('tenant.ai_api_key')
        );
});

test('blank ai_api_key does not overwrite existing stored key', function () {
    $this->tenant->update(['ai_api_key' => 'sk-existing']);

    $this->patch(route('owner.settings.update'), [
        'ai_provider' => 'gemini',
        'ai_api_key' => '',
    ])->assertRedirect();

    $this->tenant->refresh();

    expect($this->tenant->ai_api_key)->toBe('sk-existing')
        ->and($this->tenant->ai_provider)->toBe('gemini');
});

test('free tier remaining reflects daily limit minus usage', function () {
    config(['ai.free_tier.daily_limit' => 5]);

    \App\Models\AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 2,
    ]);

    $this->get(route('owner.settings.index'))
        ->assertInertia(
            fn ($page) => $page
                ->where('aiFreeTier.daily_limit', 5)
                ->where('aiFreeTier.remaining', 3)
        );
});

test('invalid provider is rejected', function () {
    $this->patch(route('owner.settings.update'), [
        'ai_provider' => 'invalid-provider',
    ])->assertSessionHasErrors('ai_provider');
});
