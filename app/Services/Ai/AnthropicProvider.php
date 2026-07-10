<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class AnthropicProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
    {
        $response = Http::withHeaders([
            'x-api-key' => $this->apiKey,
            'anthropic-version' => '2023-06-01',
        ])->timeout(60)->post('https://api.anthropic.com/v1/messages', [
            'model' => $this->model,
            'max_tokens' => 2048,
            'system' => $systemPrompt,
            'messages' => [
                ['role' => 'user', 'content' => "DATA (JSON):\n".json_encode($context, JSON_UNESCAPED_UNICODE)."\n\nPERTANYAAN:\n".$userPrompt],
            ],
        ]);

        if ($response->failed()) {
            throw new RuntimeException('Anthropic API error: '.$response->status());
        }

        $usage = $response->json('usage', []);

        return new AiResult(
            text: $response->json('content.0.text', ''),
            tokensUsed: ($usage['input_tokens'] ?? 0) + ($usage['output_tokens'] ?? 0),
        );
    }
}
