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
                ->where('aiQuota.daily_limit', 5)
                ->where('aiQuota.used', 2)
                ->where('aiQuota.remaining', 3)
                ->where('aiQuota.using_free_tier', true)
        );
});

test('settings quota block reports byok tenants as unmetered', function () {
    config(['ai.free_tier.daily_limit' => 5]);

    \App\Models\AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 2,
    ]);

    $this->tenant->update(['ai_api_key' => 'sk-own-key']);

    // `used` nol, bukan 2: pemakaian dari masa sebelum kuncinya diisi tidak
    // boleh dibacakan sebagai jatah yang sedang berjalan.
    $this->get(route('owner.settings.index'))
        ->assertInertia(
            fn ($page) => $page
                ->where('aiQuota.using_free_tier', false)
                ->where('aiQuota.used', 0)
        );
});

test('quota block names the plan when the limit comes from one', function () {
    $plan = \App\Models\Plan::factory()->create([
        'name' => 'Paket Warung',
        'limits' => ['ai_daily' => 9],
    ]);

    \App\Models\Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
    ]);

    $this->get(route('owner.settings.index'))
        ->assertInertia(
            fn ($page) => $page
                ->where('aiQuota.daily_limit', 9)
                ->where('aiQuota.limit_source', 'plan')
                ->where('aiQuota.plan_name', 'Paket Warung')
        );
});

test('invalid provider is rejected', function () {
    $this->patch(route('owner.settings.update'), [
        'ai_provider' => 'invalid-provider',
    ])->assertSessionHasErrors('ai_provider');
});

test('promo yang berjalan ikut terbaca di halaman Pengaturan', function () {
    config(['ai.free_tier.daily_limit' => 5]);

    \App\Models\AiQuotaPolicy::factory()->bonus(3)->create(['label' => 'Promo Agustus']);

    // Angkanya naik DAN alasannya ikut, supaya hari promo berakhir tidak
    // terbaca owner sebagai aplikasi yang mendadak memotong jatahnya.
    $this->get(route('owner.settings.index'))
        ->assertInertia(
            fn ($page) => $page
                ->where('aiQuota.daily_limit', 8)
                ->where('aiQuota.bonus', 3)
                ->where('aiQuota.bonus_label', 'Promo Agustus')
        );
});
