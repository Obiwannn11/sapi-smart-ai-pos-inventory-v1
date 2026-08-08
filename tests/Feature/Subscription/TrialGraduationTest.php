<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;

/**
 * Perpindahan otomatis di akhir masa gratis — `[BL-052]`.
 *
 * Sebelum ini `trial_ends_at` ditulis di `startTrial()` dan tidak pernah dibaca
 * lagi oleh siapa pun. Akibatnya paket `free` TIDAK BISA HIDUP: tarifnya Rp 0,
 * penerbit tagihan melewatinya tanpa memperpanjang periode, lalu periodenya
 * lewat dan tenant turun ke masa tenggang — tiap periode, selamanya.
 */

/**
 * Tenant yang masih duduk di paket gratis, dengan masa gratis berakhir
 * `$daysUntilTrialEnds` hari lagi.
 *
 * @return array{tenant: Tenant, subscription: Subscription}
 */
function freeTrialTenant(int $daysUntilTrialEnds, string $status = Tenant::STATUS_TRIAL): array
{
    $free = Plan::default();
    $tenant = Tenant::factory()->create(['status' => $status]);

    $trialEnds = now()->addDays($daysUntilTrialEnds)->startOfDay();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $free->id,
        'seats' => $free->included_seats,
        'trial_ends_at' => $trialEnds,
        'current_period_start' => $trialEnds->copy()->subMonthsNoOverflow(2)->toDateString(),
        'current_period_end' => $trialEnds->toDateString(),
        'billing_anchor_day' => $trialEnds->day,
    ]);

    return ['tenant' => $tenant, 'subscription' => $subscription];
}

/** Paket berbayar yang ditandai sebagai tujuan setelah masa gratis. */
function postTrialPlan(float $basePrice = 100000, int $includedSeats = 3): Plan
{
    $plan = Plan::factory()->create([
        'name' => 'Paid 1',
        'slug' => 'paid-1',
        'base_price' => $basePrice,
        'included_seats' => $includedSeats,
    ]);

    $plan->setPostTrialTarget(true);

    return $plan;
}

// ── Perpindahannya ───────────────────────────────────────────────────────────

test('a trial about to end is moved onto the paid plan', function () {
    $target = postTrialPlan();
    ['tenant' => $tenant, 'subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->plan_id)->toBe($target->id);
});

test('the first paid invoice is issued at the new plan price, not at zero', function () {
    postTrialPlan();
    ['tenant' => $tenant] = freeTrialTenant(daysUntilTrialEnds: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Inilah inti `[BL-052]`. Dengan urutan terbalik — menagih dulu, memindahkan
    // paket belakangan — tarifnya masih Rp 0 saat penerbit melihatnya, tagihan
    // tidak terbit sama sekali, dan tenantnya jatuh ke masa tenggang tanpa
    // pernah melihat satu angka pun.
    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    expect($invoice->kind)->toBe(Invoice::KIND_SUBSCRIPTION)
        ->and((float) $invoice->amount)->toBe(100000.0);
});

test('the move happens inside the invoice lead window, not after the trial has lapsed', function () {
    postTrialPlan();
    ['subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 7);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Tujuh hari = persis `invoice_lead_days`. Menunggu sampai `trial_ends_at`
    // lewat berarti penerbit sudah melihat tenant ini seharga Rp 0 dan
    // melewatinya — tepat kegagalan yang hendak ditutup.
    expect($subscription->fresh()->plan_id)->not->toBe(Plan::default()->id);
});

test('a trial still far off is left on the free plan', function () {
    postTrialPlan();
    ['subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 20);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->plan_id)->toBe(Plan::default()->id);
});

test('a trial that already lapsed is still rescued', function () {
    $target = postTrialPlan();
    ['subscription' => $subscription] = freeTrialTenant(
        daysUntilTrialEnds: -5,
        status: Tenant::STATUS_GRACE,
    );

    // Tenant yang terlanjur jatuh ke masa tenggang selama bug ini hidup harus
    // ikut terangkat, bukan tertinggal menunggu tangan pemilik SaaS.
    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->plan_id)->toBe($target->id);
});

test('the seat allowance follows the new plan, and purchased seats survive it', function () {
    $target = postTrialPlan(includedSeats: 3);
    ['subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 3);

    // Satu seat dibeli di atas jatah paket gratis (2 + 1).
    $subscription->update(['seats' => Plan::default()->included_seats + 1]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Jatah paket baru (3) + seat yang benar-benar dibelinya (1).
    expect($subscription->fresh()->seats)->toBe(4)
        ->and($subscription->fresh()->plan_id)->toBe($target->id);
});

test('the move leaves an audit trail', function () {
    postTrialPlan();
    ['tenant' => $tenant] = freeTrialTenant(daysUntilTrialEnds: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $logged = PlatformAuditLog::where('action', 'subscriptions.trial-graduated')->first();

    expect($logged)->not->toBeNull()
        ->and($logged->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($logged->meta['tenant_id'])->toBe($tenant->id)
        ->and($logged->meta['to_plan'])->toBe('paid-1');
});

// ── Yang tidak ikut pindah ───────────────────────────────────────────────────

test('a tenant already on a paid plan is not touched', function () {
    postTrialPlan();

    $lain = Plan::factory()->create(['base_price' => 150000]);
    $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);
    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $lain->id,
        'trial_ends_at' => now()->subMonth(),
        'current_period_end' => now()->addDays(3)->toDateString(),
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Tarif yang kebetulan Rp 0 hari ini — keringanan, atau paket yang angkanya
    // belum ditetapkan — bukan masa gratis yang habis. Yang menentukan adalah
    // paketnya.
    expect($subscription->fresh()->plan_id)->toBe($lain->id);
});

test('a free-plan tenant without a trial end date is left alone', function () {
    postTrialPlan();
    ['subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 3);

    $subscription->update(['trial_ends_at' => null]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Memindahkan tenant yang tak pernah dijanjikan tanggal berakhir berarti
    // menagihnya karena datanya tidak lengkap.
    expect($subscription->fresh()->plan_id)->toBe(Plan::default()->id);
});

test('a suspended tenant is out of reach', function () {
    postTrialPlan();
    ['subscription' => $subscription] = freeTrialTenant(
        daysUntilTrialEnds: -40,
        status: Tenant::STATUS_SUSPENDED,
    );

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->plan_id)->toBe(Plan::default()->id);
});

// ── Tanpa paket tujuan ───────────────────────────────────────────────────────

test('without a designated target the move stops and says so', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 3);

    $result = app(SubscriptionService::class)->advanceLifecycle();

    expect($result['graduated'])->toBe(0)
        ->and($result['stranded'])->toBe(1)
        // Tidak jatuh diam-diam ke paket termurah: menebak berarti memindahkan
        // tenant ke tarif yang tak seorang pun putuskan, lalu menagihkannya.
        ->and($subscription->fresh()->plan_id)->toBe(Plan::default()->id);

    $logged = PlatformAuditLog::where('action', 'subscriptions.post-trial-target-missing')->first();

    expect($logged)->not->toBeNull()
        ->and($logged->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($logged->meta['tenant_ids'])->toBe([$tenant->id]);
});

test('a target pointing back at the free plan is refused like no target at all', function () {
    Plan::default()->setPostTrialTarget(true);

    ['subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 3);

    $result = app(SubscriptionService::class)->advanceLifecycle();

    // "Memindahkan" tenant ke tempat yang sama, tiap hari: lingkaran yang
    // berjalan mulus jauh lebih sulit dikenali daripada perpindahan yang
    // berhenti dan mengeluh.
    expect($result['stranded'])->toBe(1)
        ->and($subscription->fresh()->plan_id)->toBe(Plan::default()->id);
});

test('only one plan can hold the post-trial role', function () {
    $pertama = postTrialPlan();
    $kedua = Plan::factory()->create(['base_price' => 150000]);

    $kedua->setPostTrialTarget(true);

    expect($pertama->fresh()->is_post_trial_target)->toBeFalse()
        ->and($kedua->fresh()->is_post_trial_target)->toBeTrue()
        ->and(Plan::postTrialTarget()->id)->toBe($kedua->id);
});

// ── Yang sampai ke layar tenant (`[BL-052]`(c)) ─────────────────────────────

test('the billing page names the plan the tenant will move to', function () {
    postTrialPlan();
    ['tenant' => $tenant] = freeTrialTenant(daysUntilTrialEnds: 20);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        // Diberitahukan SEBELUM hari-H, bukan lewat tagihan yang tiba-tiba
        // muncul: masa gratis yang berakhir dengan tagihan pertama tanpa satu
        // pun peringatan terbaca sebagai jebakan, bukan sebagai kesepakatan.
        ->where('subscription.post_trial_plan.name', 'Paid 1')
        // Angka bulat pulang dari JSON sebagai int, bukan float.
        ->where('subscription.post_trial_plan.base_price', 100000));
});

test('the billing page stops naming it once the move has happened', function () {
    $target = postTrialPlan();
    ['tenant' => $tenant, 'subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 20);

    $subscription->update(['plan_id' => $target->id]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        // Kalimat "akan pindah ke Paid 1" di halaman tenant yang SUDAH di
        // Paid 1 hanya membingungkan — paket di baris atas sudah menyebut
        // namanya sendiri.
        ->where('subscription.post_trial_plan', null));
});

test('the billing page says nothing when the platform has designated no target', function () {
    ['tenant' => $tenant] = freeTrialTenant(daysUntilTrialEnds: 20);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        // Salah setel platform bukan kabar yang berguna bagi tenant; yang
        // menagihnya adalah peringatan di panel harga dan keluaran
        // `subscriptions:advance-lifecycle`.
        ->where('subscription.post_trial_plan', null));
});

// ── Dry run ─────────────────────────────────────────────────────────────────

test('dry run moves nothing', function () {
    postTrialPlan();
    ['subscription' => $subscription] = freeTrialTenant(daysUntilTrialEnds: 3);

    $result = app(SubscriptionService::class)->advanceLifecycle(dryRun: true);

    expect($result['graduated'])->toBe(1)
        ->and($subscription->fresh()->plan_id)->toBe(Plan::default()->id)
        ->and(PlatformAuditLog::where('action', 'subscriptions.trial-graduated')->count())->toBe(0);
});
