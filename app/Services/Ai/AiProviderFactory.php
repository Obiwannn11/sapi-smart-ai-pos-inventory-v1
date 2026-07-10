<?php

namespace App\Services\Ai;

use App\Models\Tenant;
use InvalidArgumentException;

class AiProviderFactory
{
    /**
     * Pilih provider + resolusi kredensial: BYOK tenant, fallback ke shared free key.
     */
    public function for(Tenant $tenant): AiProvider
    {
        $provider = $tenant->ai_provider ?: config('ai.default');
        $key = $tenant->ai_api_key ?: config('ai.free_tier.key');
        $model = $tenant->ai_model ?: config("ai.models.{$provider}");

        if (! $key) {
            throw new InvalidArgumentException('Belum ada API key AI (BYOK maupun free tier).');
        }

        return match ($provider) {
            'gemini' => new GeminiProvider($key, $model),
            'openai' => new OpenAiProvider($key, $model),
            'anthropic' => new AnthropicProvider($key, $model),
            default => throw new InvalidArgumentException("Provider AI tidak dikenal: {$provider}"),
        };
    }

    /**
     * Tenant memakai free tier bila belum mengisi API key sendiri (BYOK).
     */
    public function isUsingFreeTier(Tenant $tenant): bool
    {
        return empty($tenant->ai_api_key);
    }
}
