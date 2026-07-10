<?php

use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Facades\Http;

test('GeminiProvider posts prompt and parses AiResult', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Halo dari Gemini']]]]],
            'usageMetadata' => ['totalTokenCount' => 123],
        ]),
    ]);

    $result = (new GeminiProvider('secret-key', 'gemini-2.0-flash'))
        ->generate('system', ['revenue' => 1000], 'Bagaimana penjualan?');

    expect($result->text)->toBe('Halo dari Gemini')
        ->and($result->tokensUsed)->toBe(123);

    Http::assertSent(function ($request) {
        expect($request->url())->toContain('gemini-2.0-flash:generateContent')
            ->and($request->url())->toContain('key=secret-key');

        $text = $request->data()['contents'][0]['parts'][0]['text'];

        return str_contains($text, 'system')
            && str_contains($text, '"revenue":1000')
            && str_contains($text, 'Bagaimana penjualan?');
    });
});

test('GeminiProvider throws on API failure', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);

    (new GeminiProvider('secret-key', 'gemini-2.0-flash'))
        ->generate('system', [], 'x');
})->throws(RuntimeException::class, 'Gemini API error: 500');

test('OpenAiProvider sends bearer token and parses AiResult', function () {
    Http::fake([
        'api.openai.com/*' => Http::response([
            'choices' => [['message' => ['content' => 'Halo dari OpenAI']]],
            'usage' => ['total_tokens' => 55],
        ]),
    ]);

    $result = (new OpenAiProvider('sk-test', 'gpt-4o-mini'))
        ->generate('system', ['revenue' => 2000], 'Analisa?');

    expect($result->text)->toBe('Halo dari OpenAI')
        ->and($result->tokensUsed)->toBe(55);

    Http::assertSent(function ($request) {
        expect($request->url())->toBe('https://api.openai.com/v1/chat/completions')
            ->and($request->hasHeader('Authorization', 'Bearer sk-test'))->toBeTrue();

        $body = $request->data();

        return $body['model'] === 'gpt-4o-mini'
            && $body['messages'][0]['role'] === 'system'
            && str_contains($body['messages'][1]['content'], '"revenue":2000');
    });
});

test('AnthropicProvider sends api key header and sums token usage', function () {
    Http::fake([
        'api.anthropic.com/*' => Http::response([
            'content' => [['text' => 'Halo dari Anthropic']],
            'usage' => ['input_tokens' => 10, 'output_tokens' => 20],
        ]),
    ]);

    $result = (new AnthropicProvider('anthropic-key', 'claude-haiku-4-5-20251001'))
        ->generate('system', ['revenue' => 3000], 'Rekomendasi?');

    expect($result->text)->toBe('Halo dari Anthropic')
        ->and($result->tokensUsed)->toBe(30);

    Http::assertSent(function ($request) {
        expect($request->url())->toBe('https://api.anthropic.com/v1/messages')
            ->and($request->hasHeader('x-api-key', 'anthropic-key'))->toBeTrue()
            ->and($request->hasHeader('anthropic-version', '2023-06-01'))->toBeTrue();

        $body = $request->data();

        return $body['model'] === 'claude-haiku-4-5-20251001'
            && $body['system'] === 'system'
            && str_contains($body['messages'][0]['content'], '"revenue":3000');
    });
});
