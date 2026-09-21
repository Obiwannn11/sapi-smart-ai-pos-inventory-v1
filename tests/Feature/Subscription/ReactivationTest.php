<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceSettlement;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Jalan keluar dari penangguhan — `[BL-051]`, opsi (ii).
 *
 * Penerbit otomatis sengaja tidak menagih tenant `suspended`, dan aturan itu
 * tetap. Yang ditutup di sini adalah arah sebaliknya: tenant yang ingin KEMBALI
 * dan tak punya apa pun untuk dibayar, karena `current_period_end`-nya beku dan
 * tak ada satu pun tagihan terbuka. Sebelum ini satu-satunya pintunya adalah
 * pemilik SaaS mengetikkan tagihannya manual.
 */

/**
 * Tenant yang ditangguhkan dengan periode yang beku di masa lalu — persis
 * bentuk yang ditinggalkan `advanceLifecycle()`.
 *
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function suspendedTenant(float $basePrice = 100000, int $monthsAgo = 2): array
{
    $plan = Plan::factory()->create(['base_price' => $basePrice, 'included_seats' => 1]);
    $tenant = Tenant::factory()->suspended()->create();

    $periodEnd = now()->startOfDay()->subMonthsNoOverflow($monthsAgo);

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'current_period_start' => $periodEnd->copy()->subMonthNoOverflow()->toDateString(),
        'current_period_end' => $periodEnd->toDateString(),
        'billing_anchor_day' => $periodEnd->day,
    ]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    return ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription];
}

// ── Penerbitan ───────────────────────────────────────────────────────────────

test('a suspended tenant can ask for the one invoice that lets it come back', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = suspendedTenant();

    actingAs($owner);
    post('/langganan/aktifkan-kembali')->assertSessionHas('success');

    $invoice = Invoice::where('tenant_id', $tenant->id)->sole();

    expect($invoice->kind)->toBe(Invoice::KIND_SUBSCRIPTION)
        ->and((float) $invoice->amount)->toBe(100000.0)
        ->and($invoice->status)->toBe(Invoice::STATUS_UNPAID)
        // Periodenya yang BEKU, bukan bulan ini: itulah yang membuat kuncinya
        // sama dengan yang dijaga penerbit massal, dan karena itu tak akan
        // pernah ada tagihan kedua.
        ->and($invoice->period)->toBe($subscription->current_period_end->format('Y-m'))
        // Jatuh tempo tidak pernah di masa lalu. Tagihan yang lahir sudah lewat
        // tempo terbaca sebagai tunggakan lama, padahal tenant baru saja
        // memintanya sendiri.
        ->and($invoice->due_date->toDateString())->toBe(now()->toDateString());
});

test('the recovery invoice is recorded with who asked for it', function () {
    ['owner' => $owner] = suspendedTenant();

    actingAs($owner);
    post('/langganan/aktifkan-kembali');

    $log = PlatformAuditLog::where('action', 'invoices.reactivation')->sole();

    // `platform_user_id` selalu null — tak ada orang platform yang menekan
    // tombolnya. Tanpa `requested_by`, jejaknya menyisakan tagihan yang seolah
    // lahir sendiri.
    expect($log->platform_user_id)->toBeNull()
        ->and($log->meta['requested_by'])->toBe($owner->id);
});

test('paying the recovery invoice brings the tenant back and moves the period into the future', function () {
    ['tenant' => $tenant, 'owner' => $owner] = suspendedTenant();

    actingAs($owner);
    post('/langganan/aktifkan-kembali');

    $invoice = Invoice::where('tenant_id', $tenant->id)->sole();
    app(InvoiceSettlement::class)->settle($invoice, InvoiceSettlement::SOURCE_PLATFORM_VERIFY);

    $tenant->refresh();

    expect($tenant->status)->toBe(Tenant::STATUS_ACTIVE)
        // "Tunggakan tidak ditumpuk": satu pembayaran memulihkan satu periode
        // ke DEPAN, bukan menyeret tenant ke periode yang sudah usai lalu
        // menangguhkannya lagi seketika.
        ->and($tenant->subscription->current_period_end->isFuture())->toBeTrue();
});

// ── Satu tagihan, bukan satu per bulan terlewat (`[BL-051]`(b)) ───────────────

test('asking twice never produces a second invoice', function () {
    ['tenant' => $tenant, 'owner' => $owner] = suspendedTenant();

    actingAs($owner);
    post('/langganan/aktifkan-kembali')->assertSessionHas('success');
    post('/langganan/aktifkan-kembali')->assertSessionHas('error');

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('a tenant whose invoice was rejected is pointed at that invoice, not given a new one', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = suspendedTenant();

    $rejected = Invoice::create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'period' => $subscription->current_period_end->format('Y-m'),
        'kind' => Invoice::KIND_SUBSCRIPTION,
        'amount' => 100000,
        'status' => Invoice::STATUS_REJECTED,
        'due_date' => $subscription->current_period_end->toDateString(),
    ]);

    actingAs($owner);
    post('/langganan/aktifkan-kembali')->assertSessionHas('error');

    // Tagihan `rejected` masih bisa dibayar dan masih bisa diunggahi bukti
    // baru — tenantnya tidak buntu, ia hanya belum menyelesaikannya. Menerbitkan
    // yang kedua justru membuatnya menebak mana yang harus dibayar.
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1)
        ->and(Invoice::where('tenant_id', $tenant->id)->sole()->id)->toBe($rejected->id);
});

// ── Penolakan ────────────────────────────────────────────────────────────────

test('a tenant that is not suspended has nothing to recover', function () {
    $plan = Plan::factory()->create(['base_price' => 100000]);
    $tenant = Tenant::factory()->active()->create();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'current_period_end' => now()->addDays(10)->toDateString(),
        'billing_anchor_day' => now()->addDays(10)->day,
    ]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner);
    post('/langganan/aktifkan-kembali')->assertSessionHas('error');

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('a tenant whose rate was never set is sent to a human, not given a zero-rupiah invoice', function () {
    ['tenant' => $tenant, 'owner' => $owner] = suspendedTenant(basePrice: 0);

    actingAs($owner);
    post('/langganan/aktifkan-kembali')->assertSessionHas('error');

    // Di sinilah opsi (i) `[BL-051]` tetap berlaku: yang menghalangi tenant ini
    // bukan uang, jadi tak ada tagihan yang bisa menjawabnya.
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('staff cannot request the recovery invoice', function () {
    ['tenant' => $tenant] = suspendedTenant();

    $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($staff);
    post('/langganan/aktifkan-kembali')->assertForbidden();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

// ── Halaman ──────────────────────────────────────────────────────────────────

test('the billing page offers the button to a stranded tenant and to nobody else', function () {
    ['owner' => $owner] = suspendedTenant();

    actingAs($owner);
    get('/langganan')->assertInertia(
        fn (Assert $page) => $page->where('reactivation.available', true)
            ->where('reactivation.status', 'issued'),
    );

    // Sudah punya tagihan → tombolnya mati, dan halaman menyebut alasannya
    // supaya tenant tidak menunggu sesuatu yang tidak akan datang sendiri.
    post('/langganan/aktifkan-kembali');

    get('/langganan')->assertInertia(
        fn (Assert $page) => $page->where('reactivation.available', false)
            ->where('reactivation.status', 'already_invoiced'),
    );
});

test('a healthy tenant is never shown the recovery panel', function () {
    $plan = Plan::factory()->create(['base_price' => 100000]);
    $tenant = Tenant::factory()->active()->create();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'current_period_end' => now()->addDays(10)->toDateString(),
        'billing_anchor_day' => now()->addDays(10)->day,
    ]);

    actingAs(User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']));

    get('/langganan')->assertInertia(fn (Assert $page) => $page->where('reactivation', null));
});
