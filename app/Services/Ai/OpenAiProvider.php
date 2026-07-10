<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProvider
{
    public function __construct(
        private string $apiKey,
        private string $model,
    ) {}

    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "DATA (JSON):\n".json_encode($context, JSON_UNESCAPED_UNICODE)."\n\nPERTANYAAN:\n".$userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException('OpenAI API error: '.$response->status());
        }

        return new AiResult(
            text: $response->json('choices.0.message.content', ''),
            tokensUsed: $response->json('usage.total_tokens'),
        );
    }
}
