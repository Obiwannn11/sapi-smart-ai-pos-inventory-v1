<?php

use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\AiUsage;
use App\Models\Plan;
use App\Models\Subscription;
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

// ── Kuota di halaman yang membelanjakannya (`[BL-062]`) ──────────────────────

test('index sends the ai quota block to the page that spends it', function () {
    config(['ai.free_tier.daily_limit' => 5]);

    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 3,
    ]);

    $this->actingAs($this->owner)
        ->get(route('owner.ai-analysis.index'))
        ->assertInertia(
            fn ($page) => $page
                ->where('aiQuota.daily_limit', 5)
                ->where('aiQuota.used', 3)
                ->where('aiQuota.remaining', 2)
                ->where('aiQuota.using_free_tier', true)
        );
});

test('show sends the ai quota block too', function () {
    config(['ai.free_tier.daily_limit' => 5]);

    $analysis = AiAnalysis::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_COMPLETED,
        'params' => ['from' => now()->subDay()->toDateString(), 'to' => now()->toDateString()],
    ]);

    // Keduanya me-render komponen yang sama, jadi melewatkan salah satunya
    // membuat angkanya hilang begitu satu analisis dibuka.
    $this->actingAs($this->owner)
        ->get(route('owner.ai-analysis.show', $analysis))
        ->assertInertia(
            fn ($page) => $page
                ->where('aiQuota.daily_limit', 5)
                ->where('aiQuota.remaining', 5)
        );
});

test('quota block tells byok tenants they are unmetered instead of showing zero', function () {
    config(['ai.free_tier.daily_limit' => 5]);

    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 5,
    ]);

    $this->tenant->update(['ai_api_key' => 'sk-own-key']);

    $this->actingAs($this->owner)
        ->get(route('owner.ai-analysis.index'))
        ->assertInertia(
            fn ($page) => $page
                ->where('aiQuota.using_free_tier', false)
                ->where('aiQuota.used', 0)
                ->where('aiQuota.remaining', 5)
        );
});

test('store is rejected on screen when today quota is spent, and nothing is queued', function () {
    Queue::fake();
    config(['ai.free_tier.daily_limit' => 2]);

    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 2,
    ]);

    $this->actingAs($this->owner)
        ->post(route('owner.ai-analysis.store'), [
            'type' => 'general',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    // Inti `[BL-062]`: tidak ada baris `failed` yang lahir hanya untuk
    // memberitahu owner sesuatu yang sudah bisa diketahui sebelum tombol
    // ditekan.
    expect(AiAnalysis::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('store is rejected when the plan carries no ai allowance at all', function () {
    Queue::fake();

    $plan = Plan::factory()->create(['limits' => ['ai_daily' => 0]]);
    Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $plan->id,
    ]);

    $this->actingAs($this->owner)
        ->post(route('owner.ai-analysis.store'), [
            'type' => 'general',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionHas('error');

    expect(AiAnalysis::count())->toBe(0);
    Queue::assertNothingPushed();
});

test('byok tenants are never rejected for quota, even past the free limit', function () {
    Queue::fake();
    config(['ai.free_tier.daily_limit' => 1]);

    AiUsage::create([
        'tenant_id' => $this->tenant->id,
        'date' => now()->toDateString(),
        'count' => 99,
    ]);

    $this->tenant->update(['ai_api_key' => 'sk-own-key']);

    $this->actingAs($this->owner)
        ->post(route('owner.ai-analysis.store'), [
            'type' => 'general',
            'from' => now()->subDays(7)->toDateString(),
            'to' => now()->toDateString(),
        ])
        ->assertRedirect()
        ->assertSessionMissing('error');

    expect(AiAnalysis::count())->toBe(1);
    Queue::assertPushed(RunAiAnalysisJob::class);
});
