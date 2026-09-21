<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use App\Notifications\PlatformAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\artisan;

/**
 * Penundaan penerbitan tagihan — `[BL-080]` butir (b), opsi (i).
 *
 * Tenant Adaptif berjangkar tanggal 1–7 mendapat tagihannya H-7, yaitu di akhir
 * bulan sebelumnya — saat bulan penentu tarifnya belum tutup dan ringkasannya
 * belum mungkin ada. Sebelum entri ini, keadaan itu tidak menghasilkan error
 * melainkan tagihan yang memakai omzet dua bulan lalu.
 *
 * Yang dijaga berkas ini ada empat, dan keempatnya syarat yang membuat opsi (i)
 * tidak berbahaya: penundaannya benar-benar terjadi, ia BERSYARAT (jalur tetap
 * tidak ikut tertunda), ia BERAKHIR (tagihan terbit begitu ringkasannya tiba),
 * dan ia punya BATAS (lewat jatuh tempo, seorang manusia diberi tahu).
 */

/**
 * Tenant jalur Adaptif yang periodenya berakhir `$daysUntilPeriodEnd` lagi.
 *
 * @return array{tenant: Tenant, subscription: Subscription}
 */
function adaptiveTenantDue(int $daysUntilPeriodEnd): array
{
    $plan = Plan::factory()->create(['base_price' => 100000, 'is_adaptive_fallback' => true]);
    $tenant = Tenant::factory()->create([
        'status' => Tenant::STATUS_ACTIVE,
        'pricing_track' => Subscription::TRACK_SUBSIDIZED,
    ]);

    $periodEnd = now()->addDays($daysUntilPeriodEnd)->startOfDay();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'pricing_track' => Subscription::TRACK_SUBSIDIZED,
        'current_period_start' => $periodEnd->copy()->subMonthNoOverflow()->toDateString(),
        'current_period_end' => $periodEnd->toDateString(),
        'billing_anchor_day' => $periodEnd->day,
        'trial_ends_at' => $periodEnd,
    ]);

    return ['tenant' => $tenant, 'subscription' => $subscription];
}

/**
 * Bulan ringkasan yang menentukan tarif periode yang dibuka pada `$periodEnd`.
 */
function metricPeriodFor(Carbon $periodEnd): string
{
    return $periodEnd->copy()->startOfMonth()->subMonth()->format('Y-m');
}

// ── Penundaannya benar-benar terjadi ─────────────────────────────────────────

test('tagihan tenant adaptif TIDAK terbit selama ringkasan omzetnya belum ada', function () {
    ['tenant' => $tenant] = adaptiveTenantDue(daysUntilPeriodEnd: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('yang tertunda bukan tagihan tanpa tarif — paket penampung TIDAK dipakai', function () {
    // Inti kenapa penjaganya berdiri sebelum penetapan harga. Paket penampung
    // di sini berharga Rp 100.000; tanpa penundaan, tenant subsidi yang
    // ringkasannya belum tiba akan ditagih segitu — bukan tertunda, melainkan
    // ditagih tarif termahal karena datanya belum sempat ditulis.
    ['tenant' => $tenant] = adaptiveTenantDue(daysUntilPeriodEnd: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->exists())->toBeFalse()
        // Juga bukan `unpriced`: itu berarti salah setel yang butuh dibetulkan
        // pemilik SaaS, sementara ini keadaan wajar yang selesai sendiri besok.
        ->and(PlatformAuditLog::where('action', 'invoices.unpriced')->exists())->toBeFalse();
});

test('perintahnya melaporkan jumlah yang tertunda, bukan menelannya diam-diam', function () {
    adaptiveTenantDue(daysUntilPeriodEnd: 3);

    artisan('subscriptions:advance-lifecycle')
        ->expectsOutputToContain('Menunggu ringkasan omzet')
        ->assertSuccessful();
});

// ── Penundaannya BERSYARAT ───────────────────────────────────────────────────

test('tenant jalur tetap tidak ikut tertunda meski tak punya ringkasan omzet', function () {
    // Jalur Harga Tetap memang tidak pernah punya baris ringkasan — gerbang
    // privasinya memastikan datanya tidak ada, bukan disembunyikan. Menundanya
    // berarti mencabut masa siap tenant tanpa menukar apa pun.
    ['tenant' => $tenant, 'subscription' => $subscription] = adaptiveTenantDue(daysUntilPeriodEnd: 3);

    $tenant->update(['pricing_track' => Subscription::TRACK_NORMAL]);
    $subscription->update(['pricing_track' => Subscription::TRACK_NORMAL]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

// ── Penundaannya BERAKHIR ────────────────────────────────────────────────────

test('tagihan terbit pada hari ringkasan omzetnya tiba', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = adaptiveTenantDue(daysUntilPeriodEnd: 3);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();
    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);

    // Hari berikutnya penghitung omzet sudah menulis barisnya.
    TenantMonthlyMetric::factory()->create([
        'tenant_id' => $tenant->id,
        'period' => metricPeriodFor($subscription->current_period_end),
        'revenue' => 3_000_000,
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

test('ringkasan bulan yang KELIRU tidak melepas penundaan', function () {
    // Inilah cacat aslinya, dalam bentuk yang paling kecil: sebelum `[BL-080]`(c)
    // ringkasan bulan mana pun yang lebih tua sudah cukup untuk menerbitkan
    // tagihan, dan angka yang dipakai bukan angka yang dijanjikan `[BL-056]`.
    ['tenant' => $tenant, 'subscription' => $subscription] = adaptiveTenantDue(daysUntilPeriodEnd: 3);

    TenantMonthlyMetric::factory()->create([
        'tenant_id' => $tenant->id,
        'period' => $subscription->current_period_end->copy()->startOfMonth()->subMonths(2)->format('Y-m'),
        'revenue' => 3_000_000,
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(0);
});

// ── Penundaannya punya BATAS ─────────────────────────────────────────────────

test('lewat jatuh tempo tagihannya tetap ditahan, dan seorang manusia diberi tahu', function () {
    Notification::fake();
    PlatformUser::factory()->owner()->create();

    // Periodenya sudah lewat dan ringkasannya tidak pernah tiba — kasus consent
    // yang dicabut (ringkasannya dihapus) atau penghitung omzet yang gagal.
    ['tenant' => $tenant] = adaptiveTenantDue(daysUntilPeriodEnd: -1);

    artisan('subscriptions:advance-lifecycle')
        ->expectsOutputToContain('Ringkasan omzet tak kunjung ada')
        ->assertSuccessful();

    // Ditahan, BUKAN diterbitkan dari paket penampung. Menagih tenant subsidi
    // dengan tarif termahal karena sebuah cron gagal adalah arah kesalahan yang
    // paling merugikan tenant.
    expect(Invoice::where('tenant_id', $tenant->id)->exists())->toBeFalse();

    $log = PlatformAuditLog::where('action', 'invoices.postponement-overdue')->first();

    expect($log)->not->toBeNull()
        ->and($log->meta['tenant_id'])->toBe($tenant->id);

    // Jejak audit saja tidak cukup: keadaan ini butuh seseorang yang belum tahu
    // harus membuka halaman apa pun.
    Notification::assertSentTimes(PlatformAlert::class, 1);
});

test('dry-run tidak mengirim peringatan dan tidak meninggalkan jejak', function () {
    Notification::fake();
    PlatformUser::factory()->owner()->create();

    adaptiveTenantDue(daysUntilPeriodEnd: -1);

    artisan('subscriptions:advance-lifecycle', ['--dry-run' => true])->assertSuccessful();

    expect(PlatformAuditLog::where('action', 'invoices.postponement-overdue')->exists())->toBeFalse();

    Notification::assertNothingSent();
});
