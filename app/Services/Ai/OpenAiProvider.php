<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class OpenAiProvider implements AiProvider
{
    public function __construct(
        protected string $apiKey,
        protected string $model,
    ) {}

    public function generate(string $systemPrompt, array $context, string $userPrompt): AiResult
    {
        $response = Http::withToken($this->apiKey)
            ->timeout(60)
            ->post($this->endpoint(), [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => "DATA (JSON):\n".json_encode($context, JSON_UNESCAPED_UNICODE)."\n\nPERTANYAAN:\n".$userPrompt],
                ],
            ]);

        if ($response->failed()) {
            throw new RuntimeException($this->providerLabel().' API error: '.$response->status());
        }

        return new AiResult(
            text: $response->json('choices.0.message.content', ''),
            tokensUsed: $response->json('usage.total_tokens'),
        );
    }

    /**
     * Endpoint chat completions. Provider yang OpenAI-compatible cukup override ini.
     */
    protected function endpoint(): string
    {
        return 'https://api.openai.com/v1/chat/completions';
    }

    /**
     * Label provider untuk pesan error.
     */
    protected function providerLabel(): string
    {
        return 'OpenAI';
    }
}
