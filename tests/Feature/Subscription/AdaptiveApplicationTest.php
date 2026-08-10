<?php

use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PricingRule;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantMonthlyMetric;
use App\Models\Transaction;
use App\Models\User;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Alur pengajuan Harga Adaptif — `[BL-055]`.
 *
 * Sejak paket berbayar termurah Rp 100.000, jalur ini adalah satu-satunya
 * jembatan dari Rp 0 ke tarif berbayar bagi tenant yang tidak sanggup
 * membayarnya. Yang diuji di sini bukan tampilannya, melainkan tiga hal yang
 * kalau salah akan merugikan orang: siapa yang boleh masuk, kapan penilaiannya
 * terjadi, dan apa yang terjadi saat ia tumbuh melewati ambang.
 */

/**
 * Tangga bracket yang berujung — A/B/C dengan batas atas di Rp 50 juta, persis
 * bentuk yang terpasang di produksi.
 */
function seedAdaptiveLadder(): void
{
    PricingRule::query()->forceDelete();

    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000, 15_000_000)->create(['label' => 'B', 'price' => 25_000]);
    PricingRule::factory()->revenueBetween(15_000_000, 50_000_000)->create(['label' => 'C', 'price' => 75_000]);
}

/**
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function makeAdaptiveContext(): array
{
    $tenant = Tenant::factory()->active()->create();
    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->addDays(20)->toDateString(),
    ]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'subscription' => $subscription,
    ];
}

/** Penjualan di bulan yang sudah TUTUP — periode yang dipakai penilaian. */
function recordClosedMonthSale(Tenant $tenant, User $owner, float $amount): void
{
    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $amount,
        'occurred_at' => now()->startOfMonth()->subMonth()->addDays(5),
    ]);
}

// --- Tangga dan ambangnya, dibaca dari data ---

test('tangga adaptif dibaca dari aturan harga, terurut, dengan batas tiap anak tangga', function () {
    seedAdaptiveLadder();

    $ladder = app(PricingService::class)->adaptiveLadder();

    expect($ladder)->toHaveCount(3)
        ->and(array_column($ladder, 'label'))->toBe(['A', 'B', 'C'])
        ->and($ladder[0])->toMatchArray(['min' => 0.0, 'max' => 2_000_000.0, 'price' => 10_000.0])
        ->and($ladder[2])->toMatchArray(['min' => 15_000_000.0, 'max' => 50_000_000.0]);
});

test('ambang adalah batas atas tertinggi di tangganya', function () {
    seedAdaptiveLadder();

    expect(app(PricingService::class)->adaptiveCeiling())->toBe(50_000_000.0);
});

/**
 * Inti peringatan `[BL-055]`(f). Satu bracket yang lupa diberi batas atas
 * berarti tangganya tidak berujung — dan jawaban yang benar saat itu adalah
 * tidak menolak siapa pun, bukan menolak semua orang.
 */
test('bracket tanpa batas atas meniadakan ambang, bukan menutup pintunya', function () {
    PricingRule::query()->forceDelete();
    PricingRule::factory()->revenueBetween(0, 2_000_000)->create(['label' => 'A', 'price' => 10_000]);
    PricingRule::factory()->revenueBetween(2_000_000)->create(['label' => 'B', 'price' => 25_000]);

    expect(app(PricingService::class)->adaptiveCeiling())->toBeNull();
});

test('aturan bersyarat majemuk tidak dipajang sebagai anak tangga umum', function () {
    seedAdaptiveLadder();

    PricingRule::factory()
        ->revenueBetween(0, 2_000_000)
        ->withCondition('business_type', 'eq', 'kuliner')
        ->create(['label' => 'A-KULINER', 'price' => 5_000]);

    // Ia tetap berlaku saat harga ditetapkan; yang tidak dilakukan adalah
    // memajangnya sebagai baris tangga yang seolah berlaku untuk semua orang.
    expect(array_column(app(PricingService::class)->adaptiveLadder(), 'label'))
        ->toBe(['A', 'B', 'C']);
});

// --- Halaman pengajuan ---

test('halaman pengajuan menyajikan tangga, tarif berjalan, dan verdict', function () {
    seedAdaptiveLadder();
    ['owner' => $owner] = makeAdaptiveContext();

    actingAs($owner);

    get('/langganan/harga-adaptif')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Adaptive')
            ->has('ladder', 3)
            ->where('verdict.reason', 'eligible')
            ->where('verdict.eligible', true)
            ->where('verdict.ceiling', 50_000_000)
            ->has('current.price')
        );
});

test('halaman pengajuan terbuka untuk staf, karena ia menjelaskan tarif', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant] = makeAdaptiveContext();

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($cashier);
    get('/langganan/harga-adaptif')->assertStatus(200);
});

test('membuka halaman pengajuan tidak menuliskan ringkasan omzet apa pun', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner] = makeAdaptiveContext();

    recordClosedMonthSale($tenant, $owner, 3_000_000);

    actingAs($owner);
    get('/langganan/harga-adaptif')->assertStatus(200);

    // Perkiraannya dihitung untuk mata pemiliknya sendiri, dan tidak boleh
    // meninggalkan satu baris pun yang bisa dibaca halaman platform.
    expect(TenantMonthlyMetric::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

// --- Pagar kelayakan, ditegakkan di tempat perpindahan benar-benar terjadi ---

test('omzet di atas ambang ditolak dengan menyebut angkanya, dan ditahan di titik perpindahan', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner] = makeAdaptiveContext();

    recordClosedMonthSale($tenant, $owner, 80_000_000);

    actingAs($owner);

    get('/langganan/harga-adaptif')->assertInertia(fn (Assert $page) => $page
        ->where('verdict.reason', 'above_ceiling')
        ->where('verdict.eligible', false)
        ->where('verdict.revenue', 80_000_000)
    );

    // Tombolnya boleh tidak dirender di mana pun; yang menahan adalah ini.
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true])
        ->assertSessionHas('error');

    expect($tenant->fresh()->pricing_track)->toBe(Subscription::TRACK_NORMAL);
});

test('masa tunggu perpindahan disebut sebagai cooldown, bukan sebagai omzet terlalu tinggi', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeAdaptiveContext();

    recordClosedMonthSale($tenant, $owner, 1_000_000);
    $subscription->update(['track_changed_at' => now()->subMonth()]);

    actingAs($owner);

    get('/langganan/harga-adaptif')->assertInertia(fn (Assert $page) => $page
        ->where('verdict.reason', 'cooldown')
        ->where('verdict.eligible', false)
        ->where('verdict.available_at', now()->subMonth()
            ->addMonthsNoOverflow(SubscriptionService::trackSwitchMinimumMonths())->toDateString())
    );
});

test('tenant yang sudah di jalur adaptif tidak diminta mengajukan lagi', function () {
    seedAdaptiveLadder();
    ['owner' => $owner] = makeAdaptiveContext();

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);

    get('/langganan/harga-adaptif')->assertInertia(fn (Assert $page) => $page
        ->where('verdict.reason', 'active')
        ->where('verdict.eligible', false)
    );
});

test('halaman langganan mengirim alasan penolakan, bukan cuma tombol mati', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner] = makeAdaptiveContext();

    recordClosedMonthSale($tenant, $owner, 80_000_000);

    actingAs($owner);

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.can_switch', false)
        ->where('subsidy.reason', 'above_ceiling')
        ->where('subsidy.ceiling', 50_000_000)
        ->where('subsidy.measured_revenue', 80_000_000)
    );
});

test('omzet di bawah ambang tetap boleh mengajukan', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner] = makeAdaptiveContext();

    recordClosedMonthSale($tenant, $owner, 3_000_000);

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true])
        ->assertRedirect('/langganan');

    expect($tenant->fresh()->pricing_track)->toBe(Subscription::TRACK_SUBSIDIZED);
});

// --- Penilaian otomatis, tanpa antrean admin ---

test('pengajuan langsung menghitung omzet, tanpa menunggu jadwal bulanan', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner] = makeAdaptiveContext();

    recordClosedMonthSale($tenant, $owner, 3_000_000);

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true])
        ->assertRedirect('/langganan');

    $metric = TenantMonthlyMetric::where('tenant_id', $tenant->id)->first();

    expect($metric)->not->toBeNull()
        ->and((float) $metric->revenue)->toBe(3_000_000.0)
        ->and($metric->period)->toBe(now()->startOfMonth()->subMonth()->format('Y-m'));

    // Dan bracketnya sudah bisa dibaca di layar pada permintaan berikutnya,
    // bukan empat minggu kemudian.
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.bracket.label', 'B')
        ->where('subsidy.bracket.price', 25_000)
    );
});

// --- Pemindahan saat melewati ambang ---

test('tenant adaptif yang melewati ambang dijadwalkan keluar pada periode berikutnya', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeAdaptiveContext();

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);

    // Omzetnya tumbuh melewati ujung tangga.
    TenantMonthlyMetric::where('tenant_id', $tenant->id)->delete();
    TenantMonthlyMetric::factory()
        ->forPeriod(now()->startOfMonth()->subMonth()->format('Y-m'))
        ->create(['tenant_id' => $tenant->id, 'revenue' => 90_000_000]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $subscription->refresh();

    // Jalurnya BELUM berubah — yang terjadi hari ini hanyalah pemberitahuannya.
    expect($subscription->pricing_track)->toBe(Subscription::TRACK_SUBSIDIZED)
        ->and($subscription->track_revert_reason)->toBe(Subscription::REVERT_ABOVE_CEILING)
        ->and($subscription->track_reverts_at->toDateString())
        ->toBe($subscription->current_period_end->toDateString())
        ->and(PlatformAuditLog::where('action', 'subscriptions.adaptive-ceiling-exit')->exists())->toBeTrue();
});

test('pemindahan ambang memindahkan paket ke penampung adaptif saat tanggalnya tiba', function () {
    seedAdaptiveLadder();
    ['owner' => $owner, 'subscription' => $subscription] = makeAdaptiveContext();

    $penampung = Plan::factory()->create(['name' => 'Premium 1', 'base_price' => 100_000, 'included_seats' => 3]);
    $penampung->setAdaptiveFallback(true);

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);

    $subscription->update([
        'track_reverts_at' => now()->subDay()->toDateString(),
        'track_revert_reason' => Subscription::REVERT_ABOVE_CEILING,
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $subscription->refresh();

    expect($subscription->pricing_track)->toBe(Subscription::TRACK_NORMAL)
        ->and($subscription->plan_id)->toBe($penampung->id)
        ->and($subscription->track_revert_reason)->toBeNull();
});

/**
 * Pembeda yang justru paling mudah hilang: pencabutan sukarela dan pemindahan
 * ambang sama-sama berakhir di jalur normal, tapi hanya yang kedua menaikkan
 * tagihan. Menyamakannya berarti tenant yang menarik izinnya sendiri ikut
 * dipindahkan ke paket berbayar tanpa pernah memintanya.
 */
test('pencabutan sukarela tidak ikut memindahkan paket', function () {
    seedAdaptiveLadder();
    ['owner' => $owner, 'subscription' => $subscription] = makeAdaptiveContext();

    Plan::factory()->create(['name' => 'Premium 1', 'base_price' => 100_000])->setAdaptiveFallback(true);

    $paketAwal = $subscription->plan_id;

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);
    post('/langganan/subsidi/cabut')->assertSessionHas('success');

    $subscription->refresh();
    expect($subscription->track_revert_reason)->toBe(Subscription::REVERT_REVOKED);

    $subscription->update(['track_reverts_at' => now()->subDay()->toDateString()]);
    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->plan_id)->toBe($paketAwal);
});

test('tenant yang sudah punya jadwal kembali tidak ditimpa penandaan ambang', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeAdaptiveContext();

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);
    post('/langganan/subsidi/cabut');

    TenantMonthlyMetric::factory()
        ->forPeriod(now()->startOfMonth()->subMonth()->format('Y-m'))
        ->create(['tenant_id' => $tenant->id, 'revenue' => 90_000_000]);

    artisan('subscriptions:advance-lifecycle');

    // Pencabutan yang sedang berjalan tidak boleh berubah diam-diam jadi
    // pemindahan paket.
    expect($subscription->fresh()->track_revert_reason)->toBe(Subscription::REVERT_REVOKED);
});

// --- Modal tenggat berhenti menagih orang yang sudah mengajukan ---

test('pengajuan adaptif memadamkan modal penagihan', function () {
    seedAdaptiveLadder();
    ['tenant' => $tenant, 'owner' => $owner] = makeAdaptiveContext();

    // Status disetel SEBELUM permintaan pertama: `actingAs()` memakai instance
    // yang sama sepanjang test, dan relasi `tenant`-nya membeku begitu request
    // pertama memuatnya.
    $tenant->update(['status' => Tenant::STATUS_GRACE]);

    actingAs($owner);

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription.adaptive_pending', false)
    );

    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription.adaptive_pending', true)
    );
});

test('tanpa ambang, tak seorang pun dipindahkan', function () {
    PricingRule::query()->forceDelete();
    PricingRule::factory()->revenueBetween(0)->create(['label' => 'SEMUA', 'price' => 10_000]);

    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeAdaptiveContext();

    actingAs($owner);
    post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);

    TenantMonthlyMetric::where('tenant_id', $tenant->id)->delete();
    TenantMonthlyMetric::factory()
        ->forPeriod(now()->startOfMonth()->subMonth()->format('Y-m'))
        ->create(['tenant_id' => $tenant->id, 'revenue' => 900_000_000]);

    artisan('subscriptions:advance-lifecycle');

    expect($subscription->fresh()->track_reverts_at)->toBeNull();
});
