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
        'is_active' => true,
    ])->assertSessionHas('success');

    // Satu kali edit angka tidak boleh mengubah tagihan semua orang seketika.
    expect((float) $subscription->fresh()->price_locked)->toBe(50_000.0)
        ->and((float) $plan->fresh()->base_price)->toBe(250_000.0);
});

test('aturan yang sudah berlaku tidak bisa dihapus', function () {
    actAsPlatformOwner();

    $berlaku = PricingRule::where('label', 'A')->firstOrFail();

    $this->delete("/platform/pricing-rules/{$berlaku->id}")->assertSessionHas('error');

    expect(PricingRule::find($berlaku->id))->not->toBeNull();
});

test('aturan yang belum berlaku bisa dibatalkan', function () {
    actAsPlatformOwner();

    $menunggu = PricingRule::factory()->effectiveFrom(now()->addWeek()->toDateString())->create();

    $this->delete("/platform/pricing-rules/{$menunggu->id}")->assertSessionHas('success');

    expect(PricingRule::find($menunggu->id))->toBeNull();
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
        'is_active' => true,
    ]);

    $log = PlatformAuditLog::where('action', 'plans.update')->firstOrFail();

    // Mencatat hanya nilai barunya membuat pertanyaan "naik dari berapa?" tak
    // terjawab justru saat pertanyaan itu diajukan.
    expect($log->meta['before']['base_price'])->toEqual($sebelum)
        ->and($log->meta['after']['base_price'])->toEqual(75_000);
});
