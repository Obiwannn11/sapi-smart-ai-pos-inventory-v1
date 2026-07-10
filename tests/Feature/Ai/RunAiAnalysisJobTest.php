<?php

use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\AiUsage;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\AiProviderFactory;
use App\Services\AiContextService;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config([
        'ai.default' => 'gemini',
        'ai.free_tier.key' => 'shared-free-key',
        'ai.free_tier.daily_limit' => 5,
    ]);

    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

/**
 * Helper: buat analisis pending untuk tenant utama lalu jalankan job sinkron.
 */
function runAnalysis(array $overrides = []): AiAnalysis
{
    $analysis = AiAnalysis::create(array_merge([
        'tenant_id' => test()->tenant->id,
        'user_id' => test()->owner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_PENDING,
        'params' => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()],
    ], $overrides));

    (new RunAiAnalysisJob($analysis->id))->handle(
        app(AiContextService::class),
        app(AiProviderFactory::class),
    );

    return $analysis->fresh();
}

function fakeGeminiSuccess(): void
{
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([
            'candidates' => [['content' => ['parts' => [['text' => 'Hasil analisis AI.']]]]],
            'usageMetadata' => ['totalTokenCount' => 321],
        ]),
    ]);
}

test('successful run completes the analysis and records free tier usage', function () {
    fakeGeminiSuccess();

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_COMPLETED)
        ->and($analysis->result)->toBe('Hasil analisis AI.')
        ->and($analysis->tokens_used)->toBe(321);

    $usage = AiUsage::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->whereDate('date', now())
        ->value('count');

    expect($usage)->toBe(1);
});

test('provider error marks the analysis as failed', function () {
    Http::fake([
        'generativelanguage.googleapis.com/*' => Http::response([], 500),
    ]);

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_FAILED)
        ->and($analysis->error)->toContain('Gemini API error');
});

test('exhausted free tier quota fails with a quota message and does not call the provider', function () {
    Http::fake();

    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 5,
    ]);

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_FAILED)
        ->and($analysis->error)->toContain('Kuota harian free tier habis');

    Http::assertNothingSent();
});

test('BYOK tenant bypasses quota and does not increment usage', function () {
    fakeGeminiSuccess();

    $this->tenant->update(['ai_provider' => 'gemini', 'ai_api_key' => 'byok-key']);

    // Already at the free tier limit — BYOK must still run.
    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 5,
    ]);

    $analysis = runAnalysis();

    expect($analysis->status)->toBe(AiAnalysis::STATUS_COMPLETED);

    $usage = AiUsage::withoutGlobalScopes()
        ->where('tenant_id', $this->tenant->id)
        ->whereDate('date', now())
        ->value('count');

    expect($usage)->toBe(5);
});
