<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class GeminiProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
    {
        $prompt = $systemPrompt
            ."\n\nDATA (JSON):\n".json_encode($context, JSON_UNESCAPED_UNICODE)
            ."\n\nPERTANYAAN:\n".$userPrompt;

        $response = Http::timeout(60)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/{$this->model}:generateContent?key={$this->apiKey}", [
                'contents' => [['parts' => [['text' => $prompt]]]],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('Gemini API error: '.$response->status());
        }

        return new AiResult(
            text: $response->json('candidates.0.content.parts.0.text', ''),
            tokensUsed: $response->json('usageMetadata.totalTokenCount'),
        );
    }
}
