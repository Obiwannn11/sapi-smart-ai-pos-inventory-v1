<?php

namespace App\Services\Ai;

interface AiProvider
{
    /**
     * Panggil LLM dengan system prompt, konteks agregat, dan pertanyaan user.
     *
     * @param  array<string, mixed>  $context  Data agregat (bukan PII) dari AiContextService
     */
    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult;
}
