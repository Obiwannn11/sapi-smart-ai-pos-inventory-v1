<?php

use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\TenantMonthlyMetric;
use App\Models\User;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\actingAs;

uses(RefreshDatabase::class);

beforeEach(function () {
    PricingRule::query()->delete();

    $this->plan = Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
    $this->tenant = Tenant::factory()->active()->create(['business_type' => 'kuliner']);
    $this->subscription = Subscription::factory()->create([
        'tenant_id' => $this->tenant->id,
        'plan_id' => $this->plan->id,
        'current_period_end' => now()->addDays(20)->toDateString(),
    ]);
    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
    $this->pricing = app(PricingService::class);
});

test('tenant jalur Harga Tetap mendapat kelas dari dimensi tanpa consent', function () {
    $this->pricing->publishRule(
        ['label' => 'Kuliner Kecil', 'price' => 80_000, 'effective_from' => now()->subDay()->toDateString()],
        [['dimension' => 'business_type', 'operator' => 'eq', 'value' => 'kuliner']],
    );

    $classification = $this->pricing->classificationFor($this->tenant);

    expect($classification)->not->toBeNull()
        ->and($classification['source'])->toBe(PricingService::SOURCE_RULE)
        ->and($classification['label'])->toBe('Kuliner Kecil')
        ->and($classification['price'])->toBe(80_000.0);
});

test('dasar kelas HANYA memuat dimensi yang tidak butuh consent', function () {
    // Batas privasi `[BL-041]`(b): owner boleh melihat kelasnya sendiri tanpa
    // membuka data penjualan yang tidak pernah ia setujui.
    $this->pricing->publishRule(
        ['label' => 'Kuliner Kecil', 'price' => 80_000, 'effective_from' => now()->subDay()->toDateString()],
        [['dimension' => 'business_type', 'operator' => 'eq', 'value' => 'kuliner']],
    );

    $basis = collect($this->pricing->classificationFor($this->tenant)['basis'])->pluck('name');

    expect($basis)->not->toContain('monthly_revenue')
        ->and($basis)->not->toContain('transaction_count')
        ->and($basis)->toContain('business_type');
});

test('omzet tetap tidak ikut dasar kelas meski tenant sudah menyetujui', function () {
    // Lapis kedua penjaganya. Lapis pertama (`valueFor()` mengembalikan null)
    // tidak lagi menolong begitu tenant menyetujui, jadi `basis` harus menyaring
    // atas namanya sendiri — bukan bergantung pada null yang kebetulan.
    TenantConsent::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => TenantConsent::TYPE_SUBSIDIZED,
    ]);
    TenantMonthlyMetric::factory()->create([
        'tenant_id' => $this->tenant->id,
        'period' => now()->subMonth()->format('Y-m'),
        'revenue' => 1_500_000,
    ]);
    $this->pricing->publishRule(
        ['label' => 'Kuliner Kecil', 'price' => 80_000, 'effective_from' => now()->subDay()->toDateString()],
        [['dimension' => 'business_type', 'operator' => 'eq', 'value' => 'kuliner']],
    );

    $basis = collect($this->pricing->classificationFor($this->tenant->fresh())['basis'])->pluck('name');

    expect($basis)->not->toContain('monthly_revenue')
        ->and($basis)->not->toContain('transaction_count');
});

test('tanpa aturan yang cocok, kelasnya adalah paketnya sendiri', function () {
    // Jawaban yang berbeda, dan pantas disebut namanya: bukan "tidak ada kelas"
    // melainkan "tarif Anda datang dari paket, bukan dari aturan".
    $classification = $this->pricing->classificationFor($this->tenant);

    expect($classification['source'])->toBe(PricingService::SOURCE_PLAN)
        ->and($classification['label'])->toBe('Paid 1')
        ->and($classification['price'])->toBe(100_000.0);
});

test('nilai atribut diterjemahkan ke labelnya, bukan dikirim sebagai slug', function () {
    $basis = collect($this->pricing->classificationFor($this->tenant)['basis'])
        ->firstWhere('name', 'business_type');

    expect($basis['value'])->toBe('kuliner')
        ->and($basis['display'])->toBe('Kuliner / F&B');
});

test('halaman langganan mengirim kelas untuk tenant jalur Harga Tetap', function () {
    actingAs($this->owner)->get('/langganan')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page
            ->where('classification.source', PricingService::SOURCE_PLAN)
            ->where('classification.label', 'Paid 1'));
});

test('halaman langganan TIDAK mengirim kelas untuk tenant jalur Harga Adaptif', function () {
    // Mereka sudah punya `subsidy.bracket`, lengkap dengan omzet yang
    // mendasarinya. Dua kartu yang menjawab pertanyaan sama dengan angka sama
    // hanya membuat pembacanya bertanya mana yang benar.
    $this->subscription->update(['pricing_track' => 'subsidized']);

    actingAs($this->owner)->get('/langganan')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->where('classification', null));
});
