<?php

use App\Models\Invoice;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Tanggal tagih langganan — `[BL-030]`.
 *
 * Keputusan yang diuji di sini ada tiga, dan ketiganya keputusan bisnis:
 * bulan pendek menjepit (bukan meluber), penjepitan itu sementara (jangkar
 * tetap utuh), dan periode menyambung dari periode sebelumnya (bukan dari
 * hari verifikasi).
 */

/**
 * @return array{tenant: Tenant, subscription: Subscription}
 */
function anchoredSubscription(string $periodEnd, ?int $anchorDay = null): array
{
    $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_GRACE]);

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_start' => Carbon::parse($periodEnd)->subMonthNoOverflow()->toDateString(),
        'current_period_end' => $periodEnd,
        'billing_anchor_day' => $anchorDay ?? Carbon::parse($periodEnd)->day,
    ]);

    return ['tenant' => $tenant, 'subscription' => $subscription];
}

// ── Bulan pendek menjepit, tidak meluber ─────────────────────────────────────

test('a period ending on the 31st clamps to the last day of a short month', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-01-31');

    // Perilaku lama: addMonth() menghasilkan 2026-03-03 — periode 31 hari yang
    // ditagih sebagai satu bulan, dan Februari dilewati sama sekali.
    expect($subscription->nextAnchoredDateAfter(Carbon::parse('2026-01-31'))->toDateString())
        ->toBe('2026-02-28');
});

test('a leap February clamps to the 29th, not the 28th', function () {
    ['subscription' => $subscription] = anchoredSubscription('2028-01-31');

    expect($subscription->nextAnchoredDateAfter(Carbon::parse('2028-01-31'))->toDateString())
        ->toBe('2028-02-29');
});

// ── Penjepitan sementara: jangkar tetap utuh ─────────────────────────────────

test('the clamp does not stick — the anchor pulls the date back in a long month', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-02-28', anchorDay: 31);

    // Inilah yang membedakan jangkar dari sekadar addMonthNoOverflow(): dengan
    // rantai, tanggal 31 yang sudah turun ke 28 akan menetap di 28 selamanya.
    expect($subscription->nextAnchoredDateAfter(Carbon::parse('2026-02-28'))->toDateString())
        ->toBe('2026-03-31');
});

test('a 30-day month clamps a 31 anchor without losing it', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-03-31', anchorDay: 31);

    $april = $subscription->nextAnchoredDateAfter(Carbon::parse('2026-03-31'));
    expect($april->toDateString())->toBe('2026-04-30');

    // Dan Mei mengembalikannya ke 31.
    expect($subscription->nextAnchoredDateAfter($april)->toDateString())->toBe('2026-05-31');
});

test('an anchor that never needs clamping is left alone', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-07-24');

    expect($subscription->nextAnchoredDateAfter(Carbon::parse('2026-07-24'))->toDateString())
        ->toBe('2026-08-24');
});

// ── Periode menyambung, tidak mulai dari hari verifikasi ─────────────────────

test('paying late does not push the billing date forward', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-08-24');

    // Bukti bayar diperiksa 5 hari setelah periodenya lewat — masih dalam masa
    // tenggang. Perilaku lama menjadikan 29 Agustus sebagai titik mulai periode
    // baru, sehingga tanggal tagih maju 5 hari dan tidak pernah kembali.
    Carbon::setTestNow('2026-08-29');

    $renewed = app(SubscriptionService::class)->renewPeriod($subscription);

    expect($renewed['current_period_start'])->toBe('2026-08-24')
        ->and($renewed['current_period_end'])->toBe('2026-09-24');
});

test('paying on a 31st does not make the 31st the billing date', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-07-24');

    Carbon::setTestNow('2026-07-31');

    $renewed = app(SubscriptionService::class)->renewPeriod($subscription);

    // Justru inilah yang membuat tanggal 29/30/31 bisa jadi titik mulai periode
    // sama sekali: hari verifikasi dipakai sebagai awal periode.
    expect($renewed['current_period_start'])->toBe('2026-07-24')
        ->and($renewed['current_period_end'])->toBe('2026-08-24');
});

test('arrears are not stacked — a long-suspended tenant lands in a live period', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-05-24');

    // Tertangguh berbulan-bulan, lalu membayar. Menyambung apa adanya akan
    // memberinya periode yang sudah usai, dan `advanceLifecycle` langsung
    // menangguhkannya lagi di hari yang sama.
    Carbon::setTestNow('2026-09-10');

    $renewed = app(SubscriptionService::class)->renewPeriod($subscription);

    expect($renewed['current_period_start'])->toBe('2026-08-24')
        ->and($renewed['current_period_end'])->toBe('2026-09-24');
});

// ── Jangkar dibawa dari awal & tidak hilang ──────────────────────────────────

test('a new trial records its billing anchor', function () {
    Carbon::setTestNow('2026-01-01');

    $tenant = Tenant::factory()->create();
    $subscription = app(SubscriptionService::class)->startTrial($tenant);

    // Masa coba 30 hari dari 1 Januari berakhir 31 Januari — persis tanggal yang
    // paling rentan meluber, dan justru itu yang perlu tersimpan.
    expect($subscription->current_period_end->toDateString())->toBe('2026-01-31')
        ->and($subscription->billing_anchor_day)->toBe(31);
});

test('an older subscription without an anchor gets one on its first renewal', function () {
    ['subscription' => $subscription] = anchoredSubscription('2026-01-31');
    $subscription->update(['billing_anchor_day' => null]);

    Carbon::setTestNow('2026-02-01');

    $renewed = app(SubscriptionService::class)->renewPeriod($subscription->fresh());

    expect($renewed['billing_anchor_day'])->toBe(31)
        ->and($renewed['current_period_end'])->toBe('2026-02-28');
});

test('verifying a payment carries the anchor onto the subscription', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = anchoredSubscription('2026-01-31');
    $subscription->update(['billing_anchor_day' => null]);

    $invoice = Invoice::factory()->awaitingVerification()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 100000,
    ]);

    Carbon::setTestNow('2026-02-05');
    actingAs(PlatformUser::factory()->withAllModules()->create(), 'platform');

    post("/platform/invoices/{$invoice->id}/verify")->assertSessionHas('success');

    $fresh = $subscription->fresh();
    expect($fresh->billing_anchor_day)->toBe(31)
        ->and($fresh->current_period_end->toDateString())->toBe('2026-02-28');
});

// ── Jarak minimum pindah jalur ───────────────────────────────────────────────

test('the three-month track switch distance does not overflow on the 31st', function () {
    $tenant = Tenant::factory()->create();
    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'track_changed_at' => Carbon::parse('2025-11-30 10:00:00'),
    ]);

    // 30 November + 3 bulan meluber ke 2 Maret, jadi jarak minimum diam-diam
    // menjadi 3 bulan lebih dua hari.
    expect(app(SubscriptionService::class)->trackSwitchAvailableAt($tenant)->toDateString())
        ->toBe('2026-02-28');

    // Tanggal yang dipajang dan tanggal yang ditegakkan wajib satu.
    Carbon::setTestNow('2026-02-28 11:00:00');
    expect(app(SubscriptionService::class)->canSwitchTrack($tenant))->toBeTrue();

    Carbon::setTestNow('2026-02-27 11:00:00');
    expect(app(SubscriptionService::class)->canSwitchTrack($tenant))->toBeFalse();

    expect($subscription->fresh()->track_changed_at->toDateString())->toBe('2025-11-30');
});
