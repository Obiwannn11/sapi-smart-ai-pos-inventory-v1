<?php

use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('owner can view the ai analysis page', function () {
    $this->actingAs($this->owner)
        ->get(route('owner.ai-analysis.index'))
        ->assertInertia(fn ($page) => $page->component('Owner/AiAnalysis/Index'));
});

test('cashier cannot access ai analysis', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->actingAs($cashier)
        ->get(route('owner.ai-analysis.index'))
        ->assertStatus(403);
});

test('store creates a pending analysis and dispatches the job', function () {
    Queue::fake();

    $this->actingAs($this->owner)
        ->post(route('owner.ai-analysis.store'), [
            'type' => 'discount',
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertRedirect();

    $analysis = AiAnalysis::first();

    expect($analysis)->not->toBeNull()
        ->and($analysis->status)->toBe(AiAnalysis::STATUS_PENDING)
        ->and($analysis->type)->toBe('discount')
        ->and($analysis->user_id)->toBe($this->owner->id)
        ->and($analysis->tenant_id)->toBe($this->tenant->id)
        ->and($analysis->params)->toBe([
            'from' => now()->subDays(30)->toDateString(),
            'to' => now()->toDateString(),
        ]);

    Queue::assertPushed(RunAiAnalysisJob::class, fn ($job) => $job->analysisId === $analysis->id);
});

test('store validates type and date range', function () {
    Queue::fake();

    $this->actingAs($this->owner)
        ->post(route('owner.ai-analysis.store'), [
            'type' => 'invalid',
            'from' => now()->toDateString(),
            'to' => now()->subDays(5)->toDateString(),
        ])
        ->assertSessionHasErrors(['type', 'to']);

    Queue::assertNothingPushed();
});

test('show returns 404 for an analysis from another tenant', function () {
    $otherTenant = Tenant::factory()->create();
    $otherOwner = User::factory()->create([
        'tenant_id' => $otherTenant->id,
        'role' => 'owner',
    ]);

    $foreign = AiAnalysis::create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherOwner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_COMPLETED,
        'params' => ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()],
    ]);

    $this->actingAs($this->owner)
        ->get(route('owner.ai-analysis.show', $foreign))
        ->assertNotFound();
});
