<?php

use App\Models\Tenant;
use App\Services\Ai\AiProviderFactory;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use App\Services\Ai\SumoPodProvider;

beforeEach(function () {
    $this->factory = new AiProviderFactory;
});

test('uses BYOK provider and key when tenant has its own key', function () {
    $tenant = Tenant::factory()->create([
        'ai_provider' => 'openai',
        'ai_api_key' => 'sk-tenant',
        'ai_model' => 'gpt-4o-mini',
    ]);

    expect($this->factory->for($tenant))->toBeInstanceOf(OpenAiProvider::class)
        ->and($this->factory->isUsingFreeTier($tenant))->toBeFalse();
});

test('falls back to shared free tier key when tenant has no key', function () {
    config(['ai.default' => 'gemini', 'ai.free_tier.key' => 'shared-free-key']);

    $tenant = Tenant::factory()->create([
        'ai_provider' => null,
        'ai_api_key' => null,
    ]);

    expect($this->factory->for($tenant))->toBeInstanceOf(GeminiProvider::class)
        ->and($this->factory->isUsingFreeTier($tenant))->toBeTrue();
});

test('resolves sumopod provider as the default when tenant has no provider', function () {
    config(['ai.default' => 'sumopod', 'ai.free_tier.key' => 'shared-free-key']);

    $tenant = Tenant::factory()->create([
        'ai_provider' => null,
        'ai_api_key' => null,
    ]);

    expect($this->factory->for($tenant))->toBeInstanceOf(SumoPodProvider::class)
        ->and($this->factory->isUsingFreeTier($tenant))->toBeTrue();
});

test('resolves sumopod provider for BYOK tenant', function () {
    $tenant = Tenant::factory()->create([
        'ai_provider' => 'sumopod',
        'ai_api_key' => 'sk-sumopod',
    ]);

    expect($this->factory->for($tenant))->toBeInstanceOf(SumoPodProvider::class);
});

test('resolves anthropic provider', function () {
    $tenant = Tenant::factory()->create([
        'ai_provider' => 'anthropic',
        'ai_api_key' => 'anthropic-key',
    ]);

    expect($this->factory->for($tenant))->toBeInstanceOf(AnthropicProvider::class);
});

test('throws when no key available at all', function () {
    config(['ai.free_tier.key' => null]);

    $tenant = Tenant::factory()->create(['ai_provider' => 'gemini', 'ai_api_key' => null]);

    $this->factory->for($tenant);
})->throws(InvalidArgumentException::class, 'Belum ada API key AI');

test('throws on unknown provider', function () {
    $tenant = Tenant::factory()->create([
        'ai_provider' => 'unknown',
        'ai_api_key' => 'some-key',
    ]);

    $this->factory->for($tenant);
})->throws(InvalidArgumentException::class, 'Provider AI tidak dikenal');
