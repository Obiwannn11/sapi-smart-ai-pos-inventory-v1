<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\PricingRule;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\PricingService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function actAsPlatformOwner(): PlatformUser
{
    $user = PlatformUser::factory()->withAllModules()->create();
    actingAs($user, 'platform');

    return $user;
}

// --- Aturan sebagai data ---

test('bracket bawaan pindah dari config ke tabel saat migrasi', function () {
    expect(PricingRule::count())->toBe(count(config('subscription.revenue_brackets')))
        ->and(PricingRule::where('label', 'A')->exists())->toBeTrue();
});

test('bracket dibaca dari tabel, bukan dari config', function () {
    PricingRule::query()->delete();
    PricingRule::factory()->revenueBetween(0)->create([
        'label' => 'Z',
        'price' => 7777,
    ]);

    $bracket = app(PricingService::class)->bracketFor(3_000_000);

    expect($bracket['label'])->toBe('Z')
        ->and($bracket['price'])->toBe(7777.0);
});

test('halaman aturan harga digerbang izinnya sendiri', function () {
    $tanpaIzin = PlatformUser::factory()->create();
    $tanpaIzin->modules()->create(['module' => 'subscriptions']);

    actingAs($tanpaIzin, 'platform');
    get('/platform/pricing-rules')->assertForbidden();

    actAsPlatformOwner();
    get('/platform/pricing-rules')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->component('Platform/PricingRules/Index')->has('rules'));
});

// --- Grandfathering ---

test('aturan yang belum berlaku tidak ikut menentukan harga', function () {
    actAsPlatformOwner();

    post('/platform/pricing-rules', [
        'label' => 'X',
        'priority' => 100,
        'price' => 999_000,
        'effective_from' => now()->addMonth()->toDateString(),
        'conditions' => [
            ['dimension' => 'monthly_revenue', 'operator' => 'gte', 'value' => '0'],
            ['dimension' => 'monthly_revenue', 'operator' => 'lt', 'value' => '100000000'],
        ],
    ])->assertSessionHas('success');

    // Aturan masa depan tidak boleh menyentuh perhitungan hari ini — meski
    // prioritasnya jauh lebih tinggi daripada bracket yang sedang berlaku.
    expect(app(PricingService::class)->bracketFor(1_000_000)['label'])->toBe('A');
});

test('aturan berlaku surut ditolak', function () {
    actAsPlatformOwner();

    post('/platform/pricing-rules', [
        'label' => 'Y',
        'priority' => 0,
        'price' => 5000,
        'effective_from' => now()->subDay()->toDateString(),
        'conditions' => [
            ['dimension' => 'monthly_revenue', 'operator' => 'lt', 'value' => '1000000'],
        ],
    ])->assertSessionHasErrors('effective_from');
});

test('mengubah tarif paket tidak menyentuh harga tenant yang sedang berjalan', function () {
    $tenant = Tenant::factory()->active()->create();
    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'price_locked' => 50_000,
    ]);

    actAsPlatformOwner();

    $plan = Plan::default();
    $this->put("/platform/plans/{$plan->id}", [
        'name' => $plan->name,
        'base_price' => 250_000,
        'included_seats' => 1,
        'extra_seat_price' => 20_000,
        'ai_daily_limit' => null,
        'is_adaptive_fallback' => false,
    ])->assertSessionHas('success');

    // Satu kali edit angka tidak boleh mengubah tagihan semua orang seketika.
    expect((float) $subscription->fresh()->price_locked)->toBe(50_000.0)
        ->and((float) $plan->fresh()->base_price)->toBe(250_000.0);
});

test('verifikasi pembayaran mengunci harga dari nominal yang dibayar', function () {
    $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_GRACE]);
    $subscription = Subscription::factory()->create(['tenant_id' => $tenant->id, 'price_locked' => null]);
    $invoice = Invoice::factory()->awaitingVerification()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 42_000,
    ]);

    actAsPlatformOwner();
    post("/platform/invoices/{$invoice->id}/verify");

    expect((float) $subscription->fresh()->price_locked)->toBe(42_000.0);
});

// --- Audit ---

test('setiap perubahan tarif tercatat sebagai kejadian sensitif', function () {
    actAsPlatformOwner();

    post('/platform/pricing-rules', [
        'label' => 'X',
        'priority' => 0,
        'price' => 12_000,
        'effective_from' => now()->addDay()->toDateString(),
        'conditions' => [
            ['dimension' => 'monthly_revenue', 'operator' => 'lt', 'value' => '1000000'],
        ],
    ]);

    $log = PlatformAuditLog::where('action', 'pricing-rules.create')->first();

    expect($log)->not->toBeNull()
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        // toEqual, bukan toBe: meta disimpan sebagai JSON, dan 12000.0 pulang
        // sebagai integer setelah perjalanan itu.
        ->and($log->meta['price'])->toEqual(12_000);
});

test('perubahan paket mencatat nilai lama dan barunya', function () {
    actAsPlatformOwner();

    $plan = Plan::default();
    $sebelum = (float) $plan->base_price;

    $this->put("/platform/plans/{$plan->id}", [
        'name' => $plan->name,
        'base_price' => 75_000,
        'included_seats' => $plan->included_seats,
        'extra_seat_price' => (float) $plan->extra_seat_price,
        'ai_daily_limit' => 10,
        'is_adaptive_fallback' => false,
    ]);

    $log = PlatformAuditLog::where('action', 'plans.update')->firstOrFail();

    // Mencatat hanya nilai barunya membuat pertanyaan "naik dari berapa?" tak
    // terjawab justru saat pertanyaan itu diajukan.
    expect($log->meta['before']['base_price'])->toEqual($sebelum)
        ->and($log->meta['after']['base_price'])->toEqual(75_000)
        // Batas AI ikut berjejak: menaikkannya menaikkan tagihan kunci bersama.
        ->and($log->meta['before']['ai_daily_limit'])->toBeNull()
        ->and($log->meta['after']['ai_daily_limit'])->toEqual(10);
});

// --- Menyunting & menghentikan aturan ---

test('aturan yang belum berlaku bisa disunting berikut syaratnya', function () {
    actAsPlatformOwner();

    $rule = PricingRule::factory()->effectiveFrom(now()->addWeek()->toDateString())->create([
        'label' => 'Warung kecil',
        'price' => 10_000,
    ]);
    $rule->conditions()->create(['dimension' => 'monthly_revenue', 'operator' => 'lt', 'value' => '1000000']);

    $this->put("/platform/pricing-rules/{$rule->id}", [
        'label' => 'Warung kecil',
        'priority' => 5,
        'price' => 12_000,
        'effective_from' => now()->addWeek()->toDateString(),
        'conditions' => [
            ['dimension' => 'monthly_revenue', 'operator' => 'lt', 'value' => '2000000'],
            ['dimension' => 'business_type', 'operator' => 'eq', 'value' => 'kuliner'],
        ],
    ])->assertSessionHas('success');

    $rule = $rule->fresh()->load('conditions');

    expect((float) $rule->price)->toBe(12_000.0)
        ->and($rule->priority)->toBe(5)
        // Syaratnya ditulis ulang seluruhnya — yang lama tidak boleh tertinggal
        // sebagai syarat kedua yang tak pernah diminta siapa pun.
        ->and($rule->conditions)->toHaveCount(2)
        ->and($rule->conditions->pluck('value')->all())->toEqualCanonicalizing(['2000000', 'kuliner']);
});

test('aturan yang sudah berlaku tidak bisa disunting di tempat', function () {
    actAsPlatformOwner();

    $berlaku = PricingRule::where('label', 'A')->firstOrFail();
    $hargaSemula = (float) $berlaku->price;

    $this->put("/platform/pricing-rules/{$berlaku->id}", [
        'label' => 'A',
        'priority' => 0,
        'price' => 1,
        'effective_from' => now()->addDay()->toDateString(),
        'conditions' => [],
    ])->assertSessionHas('error');

    expect((float) $berlaku->fresh()->price)->toBe($hargaSemula);
});

test('aturan yang sudah berlaku bisa dihentikan tanpa menghapus jejaknya', function () {
    actAsPlatformOwner();

    $berlaku = PricingRule::where('label', 'A')->firstOrFail();

    $this->delete("/platform/pricing-rules/{$berlaku->id}")->assertSessionHas('success');

    // Berhenti dinilai seketika, tapi barisnya tetap ada bagi tagihan yang
    // menautnya — `invoices.pricing_rule_id` tidak boleh berubah jadi null.
    expect(PricingRule::find($berlaku->id))->toBeNull()
        ->and(PricingRule::withTrashed()->find($berlaku->id))->not->toBeNull()
        ->and(app(PricingService::class)->bracketFor(1_000_000)['label'] ?? null)->not->toBe('A');
});

test('aturan yang belum berlaku bisa dibatalkan', function () {
    actAsPlatformOwner();

    $menunggu = PricingRule::factory()->effectiveFrom(now()->addWeek()->toDateString())->create();

    $this->delete("/platform/pricing-rules/{$menunggu->id}")->assertSessionHas('success');

    expect(PricingRule::find($menunggu->id))->toBeNull();
});

// --- Paket penampung jalur adaptif ---

test('tenant adaptif tanpa aturan yang cocok jatuh ke paket penampung', function () {
    $premium = Plan::factory()->create(['name' => 'Premium', 'base_price' => 150_000]);
    $premium->setAdaptiveFallback(true);

    $tenant = Tenant::factory()->active()->create(['pricing_track' => Subscription::TRACK_SUBSIDIZED]);
    Subscription::factory()->subsidized()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => Plan::default()->id,
    ]);

    // Tak satu pun aturan tersisa — persis keadaan setelah bracketnya dihentikan.
    PricingRule::query()->delete();

    $resolved = app(PricingService::class)->resolveFor($tenant);

    expect($resolved['source'])->toBe(PricingService::SOURCE_PLAN)
        ->and($resolved['label'])->toBe('Premium')
        ->and($resolved['price'])->toBe(150_000.0);
});

test('tenant jalur harga tetap jatuh ke paketnya sendiri, bukan ke penampung', function () {
    $premium = Plan::factory()->create(['name' => 'Premium', 'base_price' => 150_000]);
    $premium->setAdaptiveFallback(true);

    $dasar = Plan::default();
    $dasar->update(['base_price' => 25_000]);

    $tenant = Tenant::factory()->active()->create();
    Subscription::factory()->create(['tenant_id' => $tenant->id, 'plan_id' => $dasar->id]);

    $resolved = app(PricingService::class)->resolveFor($tenant);

    // Aturan adaptif memang tidak pernah ditujukan kepadanya; yang berlaku
    // baginya adalah tarif paketnya sendiri sejak awal.
    expect($resolved['source'])->toBe(PricingService::SOURCE_PLAN)
        ->and($resolved['price'])->toBe(25_000.0);
});

test('hanya satu paket yang bisa jadi penampung', function () {
    $pertama = Plan::factory()->create();
    $pertama->setAdaptiveFallback(true);

    $kedua = Plan::factory()->create();
    $kedua->setAdaptiveFallback(true);

    expect($pertama->fresh()->is_adaptive_fallback)->toBeFalse()
        ->and(Plan::adaptiveFallback()->id)->toBe($kedua->id);
});
