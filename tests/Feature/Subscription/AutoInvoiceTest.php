<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Billing\InvoiceSettlement;
use App\Services\SubscriptionService;
use Illuminate\Support\Carbon;

use function Pest\Laravel\artisan;

/**
 * Penerbitan tagihan otomatis di akhir periode — `[BL-044]`.
 *
 * Sebelum ini `advanceLifecycle()` memindahkan status tanpa menerbitkan tagihan
 * apa pun: tenant tidak pernah diberi tahu berapa yang harus dibayar, ia hanya
 * menemukan aplikasinya berubah jadi hanya-baca.
 */

/**
 * Tenant berlangganan dengan tarif yang benar-benar ada.
 *
 * @return array{tenant: Tenant, subscription: Subscription}
 */
function billableTenant(int $daysUntilPeriodEnd, float $basePrice = 100000, string $status = Tenant::STATUS_ACTIVE): array
{
    $plan = Plan::factory()->create(['base_price' => $basePrice]);
    $tenant = Tenant::factory()->create(['status' => $status]);

    $periodEnd = now()->addDays($daysUntilPeriodEnd)->startOfDay();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'current_period_start' => $periodEnd->copy()->subMonthNoOverflow()->toDateString(),
        'current_period_end' => $periodEnd->toDateString(),
        'billing_anchor_day' => $periodEnd->day,
        'trial_ends_at' => $periodEnd,
    ]);

    return ['tenant' => $tenant, 'subscription' => $subscription];
}

// ── Penerbitan ───────────────────────────────────────────────────────────────

test('an invoice is issued before the period lapses, not after', function () {
    ['tenant' => $tenant] = billableTenant(daysUntilPeriodEnd: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $invoice = Invoice::where('tenant_id', $tenant->id)->first();

    expect($invoice)->not->toBeNull()
        ->and($invoice->kind)->toBe(Invoice::KIND_SUBSCRIPTION)
        ->and((float) $invoice->amount)->toBe(100000.0)
        ->and($invoice->status)->toBe(Invoice::STATUS_UNPAID)
        // Jatuh tempo = hari periode berjalan habis. Sesudah itu tenant masuk
        // masa tenggang, bukan langsung tertutup.
        ->and($invoice->due_date->toDateString())->toBe(now()->addDays(3)->toDateString())
        // Kuncinya bulan tempat periode BERIKUTNYA dibuka.
        ->and($invoice->period)->toBe(now()->addDays(3)->format('Y-m'));

    // Tenant tetap aktif — tagihannya terbit sebagai pemberitahuan, bukan
    // sebagai akibat dari kehilangan akses.
    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE);
});

test('a trial about to end gets its first real invoice', function () {
    ['tenant' => $tenant] = billableTenant(daysUntilPeriodEnd: 1, status: Tenant::STATUS_TRIAL);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Inilah "bulan kedua" yang selama ini tidak pernah menagih apa pun.
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('a period still far off is left alone', function () {
    ['tenant' => $tenant] = billableTenant(daysUntilPeriodEnd: 20);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

// ── Tidak menerbitkan dua kali ───────────────────────────────────────────────

test('running the command twice does not issue a second invoice', function () {
    ['tenant' => $tenant] = billableTenant(daysUntilPeriodEnd: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();
    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('a manually typed invoice for the same period is not duplicated', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = billableTenant(daysUntilPeriodEnd: 3);

    // Pemilik SaaS sudah mengetiknya sendiri, mungkin dengan nominal keringanan.
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'period' => now()->addDays(3)->format('Y-m'),
        'amount' => 50000,
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $invoices = Invoice::where('tenant_id', $tenant->id)->get();

    // Penerbit otomatis tunduk pada penjaga yang sama dengan penerbit manual,
    // dan keputusan pemilik SaaS menang.
    expect($invoices)->toHaveCount(1)
        ->and((float) $invoices->first()->amount)->toBe(50000.0);
});

// ── Yang tidak bisa ditagih ──────────────────────────────────────────────────

test('a zero tariff issues nothing and does not stop the lifecycle', function () {
    ['tenant' => $tenant] = billableTenant(daysUntilPeriodEnd: -1, basePrice: 0);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Tagihan Rp 0 akan menuntut tenant mengunggah bukti transfer nol rupiah
    // ([BL-049]), jadi tidak diterbitkan sama sekali.
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0)
        // Tapi siklus hidupnya TIDAK ikut berhenti — keputusan pemilik
        // 2026-08-05. Tarif yang kebetulan belum ditetapkan tidak boleh
        // diam-diam mencabut jaminan yang sudah ada.
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_GRACE);
});

test('a tenant with no tariff at all is recorded as a sensitive event', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = billableTenant(daysUntilPeriodEnd: 3);

    // Jalur Adaptif tanpa bracket yang cocok dan tanpa paket penampung yang
    // ditunjuk — `resolveFor()` mengembalikan harga null.
    $subscription->update(['pricing_track' => Subscription::TRACK_SUBSIDIZED]);
    $tenant->update(['pricing_track' => Subscription::TRACK_SUBSIDIZED]);
    Plan::query()->update(['is_adaptive_fallback' => false]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);

    // Ini salah setel, bukan kebijakan — jadi ia meninggalkan jejak, tidak
    // seperti tarif Rp 0 yang hanya dilaporkan sebagai angka.
    $logged = PlatformAuditLog::where('action', 'invoices.unpriced')->first();
    expect($logged)->not->toBeNull()
        ->and($logged->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($logged->meta['tenant_id'])->toBe($tenant->id);
});

test('a zero tariff leaves no audit noise', function () {
    billableTenant(daysUntilPeriodEnd: 3, basePrice: 0);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Selama tarifnya belum ditetapkan keadaan ini berlaku untuk setiap tenant
    // setiap hari; mencatatnya akan menenggelamkan kejadian yang perlu terlihat.
    expect(PlatformAuditLog::where('action', 'invoices.unpriced')->count())->toBe(0);
});

// ── Yang tidak ikut ditagih ──────────────────────────────────────────────────

test('a tenant already in grace is not billed again', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = billableTenant(
        daysUntilPeriodEnd: -2,
        status: Tenant::STATUS_GRACE,
    );

    // Tagihannya sudah terbit saat ia masih aktif — keadaan yang sebenarnya
    // dijaga di sini, dan yang dulu hanya diandaikan oleh fixture-nya.
    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'period' => now()->subDays(2)->format('Y-m'),
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Menagihnya lagi tiap hari selama masa tenggang akan menumpuk tunggakan
    // yang tak pernah diminta. Yang menolaknya adalah penjaga periode-ganda,
    // bukan status tenantnya.
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('a tenant that lapsed into grace unbilled is billed once its tariff exists', function () {
    // Persis keadaan `Kopi Story` per 2026-08-06: tarifnya masih Rp 0 saat
    // periodenya habis, jadi tak ada tagihan yang terbit, lalu ia turun ke masa
    // tenggang. Selama `grace` dikecualikan penerbit, pengecualian itu permanen
    // — `current_period_end` tak pernah maju, jadi ia tak akan pernah kembali
    // aktif sendiri, dan menetapkan tarifnya besok tidak menerbitkan apa pun.
    ['tenant' => $tenant] = billableTenant(
        daysUntilPeriodEnd: -2,
        basePrice: 0,
        status: Tenant::STATUS_GRACE,
    );

    Plan::query()->update(['base_price' => 100000]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    expect((float) $invoice->amount)->toBe(100000.0)
        // Jatuh temponya tidak boleh di masa lalu: hari ini barulah pertama kali
        // tenant melihat angkanya.
        ->and($invoice->due_date->toDateString())->toBe(now()->toDateString());
});

test('a suspended tenant is not billed at all', function () {
    ['tenant' => $tenant] = billableTenant(daysUntilPeriodEnd: -40, status: Tenant::STATUS_SUSPENDED);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Aksesnya sudah tertutup penuh. Menagih bulan yang tak bisa dipakai berarti
    // menumbuhkan utang yang tak pernah diminta siapa pun — `[BL-051]`.
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('dry run issues nothing', function () {
    ['tenant' => $tenant] = billableTenant(daysUntilPeriodEnd: 3);

    artisan('subscriptions:advance-lifecycle', ['--dry-run' => true])->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

// ── Sambungan ke perpanjangan periode ────────────────────────────────────────

test('paying an auto-issued invoice moves the period onto the next anchor', function () {
    Carbon::setTestNow('2026-01-25');

    ['tenant' => $tenant, 'subscription' => $subscription] = billableTenant(daysUntilPeriodEnd: 6);
    $subscription->update([
        'current_period_end' => '2026-01-31',
        'billing_anchor_day' => 31,
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();
    expect($invoice->period)->toBe('2026-01');

    // Dilunasi lewat jalur yang sama dengan tagihan manual.
    $renewed = app(SubscriptionService::class)->renewPeriod($subscription->fresh());

    // Jangkar 31 dijepit Februari, dan tetap utuh — lihat `[BL-030]`.
    expect($renewed['current_period_start'])->toBe('2026-01-31')
        ->and($renewed['current_period_end'])->toBe('2026-02-28');
});

test('a lapse only ever produces one invoice, and one payment clears it', function () {
    // Inilah yang membuat aturan "tunggakan tidak ditumpuk" di `renewPeriod()`
    // tetap benar setelah tagihan terbit otomatis (`[BL-030]` × `[BL-044]`).
    // Kekhawatirannya: tiap bulan terlewat punya tagihannya sendiri, jadi
    // melompati periode berarti melompati tagihan. Yang menahannya adalah
    // `current_period_end` yang tidak pernah maju selama tenant belum membayar
    // — kunci `Y-m` periodenya membeku, dan penjaga periode-ganda menolak
    // semua penerbitan sesudahnya.
    Carbon::setTestNow('2026-01-24');

    ['tenant' => $tenant, 'subscription' => $subscription] = billableTenant(daysUntilPeriodEnd: 7);

    foreach (['2026-01-24', '2026-02-01', '2026-03-05', '2026-04-10'] as $hari) {
        Carbon::setTestNow($hari);
        artisan('subscriptions:advance-lifecycle')->assertSuccessful();
    }

    // Empat bulan menunggak, tetap satu tagihan — bukan empat.
    $invoices = Invoice::where('tenant_id', $tenant->id)->get();
    expect($invoices)->toHaveCount(1)
        ->and($invoices->first()->period)->toBe('2026-01')
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_SUSPENDED);

    app(InvoiceSettlement::class)->settle(
        $invoices->first(),
        InvoiceSettlement::SOURCE_PLATFORM_VERIFY,
    );

    // Satu pembayaran memulihkan satu periode ke depan — dan tidak ada tagihan
    // lain yang tertinggal di belakangnya untuk dilompati.
    $fresh = $subscription->fresh();
    expect($fresh->current_period_start->toDateString())->toBe('2026-03-31')
        ->and($fresh->current_period_end->toDateString())->toBe('2026-04-30')
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        ->and(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('the reported counts separate issued, free, and unpriced', function () {
    billableTenant(daysUntilPeriodEnd: 3);
    billableTenant(daysUntilPeriodEnd: 3, basePrice: 0);

    $result = app(SubscriptionService::class)->advanceLifecycle();

    expect($result['invoiced'])->toBe(1)
        ->and($result['free'])->toBe(1)
        ->and($result['unpriced'])->toBe(0);
});
