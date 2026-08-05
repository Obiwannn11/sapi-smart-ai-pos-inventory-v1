<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\TenantMonthlyMetric;
use App\Models\Transaction;
use App\Models\User;
use App\Services\SubscriptionService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function makeSubsidyContext(): array
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

function agreeSubsidy(): Illuminate\Testing\TestResponse
{
    return post('/langganan/persetujuan/subsidized', ['version' => '1', 'agreed' => true]);
}

// --- Dokumen ---

test('dokumen subsidi menyatakan pembukaan omzet tanpa eufemisme', function () {
    $body = app(App\Services\ConsentService::class)->document(TenantConsent::TYPE_SUBSIDIZED)['body'];

    expect($body)->toContain('angka persisnya bisa dilihat oleh pengelola layanan')
        // Klausul data trial wajib disebut di muka, bukan ditemukan sendiri
        // oleh tenant belakangan.
        ->and($body)->toContain('sebelum Anda menyetujui halaman ini')
        ->and($body)->toContain('Laba, margin, dan harga pokok')
        ->and($body)->toContain('24 bulan');
});

test('halaman consent subsidi terpisah dari yang normal', function () {
    ['owner' => $owner] = makeSubsidyContext();

    actingAs($owner);

    get('/langganan/persetujuan/subsidized')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->where('type', 'subsidized'));

    get('/langganan/persetujuan')
        ->assertInertia(fn (Assert $page) => $page->where('type', 'normal'));
});

test('tipe dokumen yang tak dikenal jatuh ke 404', function () {
    ['owner' => $owner] = makeSubsidyContext();

    actingAs($owner);
    get('/langganan/persetujuan/gratisan')->assertNotFound();
});

// --- Pengajuan ---

test('menyetujui dokumen subsidi memindahkan jalur harga', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeSubsidyContext();

    actingAs($owner);
    agreeSubsidy()->assertRedirect('/langganan');

    expect($subscription->fresh()->pricing_track)->toBe(Subscription::TRACK_SUBSIDIZED)
        // Kolom di `tenants` ikut berubah karena di sanalah gerbang privasi job
        // membaca — tanpa join.
        ->and($tenant->fresh()->pricing_track)->toBe(Subscription::TRACK_SUBSIDIZED)
        ->and($subscription->fresh()->track_changed_at)->not->toBeNull();
});

test('staf tidak bisa mengajukan subsidi', function () {
    ['tenant' => $tenant] = makeSubsidyContext();

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($cashier);
    agreeSubsidy()->assertForbidden();

    expect($tenant->fresh()->pricing_track)->toBe(Subscription::TRACK_NORMAL);
});

test('perpindahan jalur kedua ditolak sebelum jarak minimum terlewati', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeSubsidyContext();

    $subscription->update(['track_changed_at' => now()->subMonth()]);

    actingAs($owner);
    agreeSubsidy()->assertSessionHas('error');

    expect($tenant->fresh()->pricing_track)->toBe(Subscription::TRACK_NORMAL);
});

test('perpindahan boleh lagi setelah jarak minimum terlewati', function () {
    ['owner' => $owner, 'subscription' => $subscription] = makeSubsidyContext();

    $subscription->update([
        'track_changed_at' => now()->subMonths(SubscriptionService::trackSwitchMinimumMonths() + 1),
    ]);

    actingAs($owner);
    agreeSubsidy()->assertRedirect('/langganan');

    expect($subscription->fresh()->pricing_track)->toBe(Subscription::TRACK_SUBSIDIZED);
});

// --- Pencabutan ---

test('mencabut consent menghapus ringkasan omzet seketika', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeSubsidyContext();

    actingAs($owner);
    agreeSubsidy();

    foreach ([1, 2, 3] as $bulanLalu) {
        TenantMonthlyMetric::factory()
            ->forPeriod(now()->startOfMonth()->subMonths($bulanLalu)->format('Y-m'))
            ->create(['tenant_id' => $tenant->id]);
    }

    post('/langganan/subsidi/cabut')->assertSessionHas('success');

    expect(TenantMonthlyMetric::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('mencabut consent tidak langsung menaikkan tagihan', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeSubsidyContext();

    actingAs($owner);
    agreeSubsidy();
    post('/langganan/subsidi/cabut');

    $subscription->refresh();

    // Jalurnya BELUM berubah — hanya dijadwalkan kembali di akhir periode.
    expect($subscription->pricing_track)->toBe(Subscription::TRACK_SUBSIDIZED)
        ->and($subscription->track_reverts_at->toDateString())
        ->toBe($subscription->current_period_end->toDateString())
        ->and($tenant->fresh()->pricing_track)->toBe(Subscription::TRACK_SUBSIDIZED);
});

test('jalur kembali normal setelah tanggal pengembalian lewat', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeSubsidyContext();

    actingAs($owner);
    agreeSubsidy();
    post('/langganan/subsidi/cabut');

    $subscription->update(['track_reverts_at' => now()->subDay()->toDateString()]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->pricing_track)->toBe(Subscription::TRACK_NORMAL)
        ->and($subscription->fresh()->track_reverts_at)->toBeNull()
        ->and($tenant->fresh()->pricing_track)->toBe(Subscription::TRACK_NORMAL);
});

test('pencabutan menghentikan pengumpulan data meski jalurnya belum berubah', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeSubsidyContext();

    actingAs($owner);
    agreeSubsidy();

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'total_amount' => 400000,
        'status' => Transaction::STATUS_COMPLETED,
        'occurred_at' => now()->startOfMonth()->subMonth()->addDay(),
    ]);

    post('/langganan/subsidi/cabut');
    artisan('subscriptions:compute-revenue');

    // Menyaring hanya lewat kolom jalur akan membuat pengumpulan data terus
    // jalan sebulan penuh setelah tenant menarik izinnya.
    expect(TenantMonthlyMetric::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

test('mencabut saat tidak ada consent aktif ditolak', function () {
    ['owner' => $owner] = makeSubsidyContext();

    actingAs($owner);
    post('/langganan/subsidi/cabut')->assertSessionHas('error');
});

test('riwayat persetujuan tetap tersimpan setelah dicabut', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeSubsidyContext();

    actingAs($owner);
    agreeSubsidy();
    post('/langganan/subsidi/cabut');

    $consent = TenantConsent::where('tenant_id', $tenant->id)
        ->where('type', TenantConsent::TYPE_SUBSIDIZED)
        ->firstOrFail();

    // Barisnya ditandai, bukan dihapus — "dulu setuju lalu mencabut" adalah
    // bagian dari buktinya.
    expect($consent->revoked_at)->not->toBeNull()
        ->and($consent->version)->toBe('1');
});

// --- Perkiraan tarif adaptif untuk tenant jalur tetap ---

/**
 * Tenant jalur tetap dengan tarif berjalan yang jelas, plus penjualan di bulan
 * yang baru tutup — bahan minimum untuk sebuah perkiraan.
 *
 * @return array{tenant: Tenant, owner: User}
 */
function makeEstimateContext(float $currentPrice = 100_000, float $revenue = 3_000_000): array
{
    $tenant = Tenant::factory()->active()->create();
    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'price_locked' => $currentPrice,
        'current_period_end' => now()->addDays(20)->toDateString(),
    ]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $revenue,
        'occurred_at' => now()->startOfMonth()->subMonth()->addDays(5),
    ]);

    return ['tenant' => $tenant, 'owner' => $owner];
}

test('tenant jalur tetap diberi tahu bahwa omzetnya masuk kelompok lebih murah', function () {
    App\Models\PricingRule::query()->delete();
    App\Models\PricingRule::factory()
        ->revenueBetween(0, 5_000_000)
        ->create(['label' => 'KECIL', 'price' => 25_000]);

    ['owner' => $owner] = makeEstimateContext(currentPrice: 100_000, revenue: 3_000_000);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.estimate.revenue', 3_000_000)
        ->where('subsidy.estimate.label', 'KECIL')
        ->where('subsidy.estimate.price', 25_000)
        ->where('subsidy.estimate.current_price', 100_000)
        ->where('subsidy.estimate.is_cheaper', true)
    );
});

test('omzet yang tidak masuk kelompok lebih murah tidak diklaim menguntungkan', function () {
    App\Models\PricingRule::query()->delete();
    App\Models\PricingRule::factory()
        ->revenueBetween(0, 5_000_000)
        ->create(['label' => 'KECIL', 'price' => 250_000]);

    ['owner' => $owner] = makeEstimateContext(currentPrice: 100_000, revenue: 3_000_000);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.estimate.price', 250_000)
        ->where('subsidy.estimate.is_cheaper', false)
    );
});

test('perkiraan tidak menuliskan ringkasan omzet apa pun', function () {
    App\Models\PricingRule::query()->delete();
    App\Models\PricingRule::factory()
        ->revenueBetween(0, 5_000_000)
        ->create(['label' => 'KECIL', 'price' => 25_000]);

    ['tenant' => $tenant, 'owner' => $owner] = makeEstimateContext();

    actingAs($owner);
    get('/langganan')->assertStatus(200);

    // Inti gerbang privasinya: melihat perkiraan tidak boleh melahirkan baris
    // yang bisa dibaca halaman platform. Omzet baru mengalir ke sana setelah
    // tenant menyetujuinya, bukan sebelum.
    expect(TenantMonthlyMetric::where('tenant_id', $tenant->id)->count())->toBe(0);
});

test('transaksi batal tidak ikut diperkirakan', function () {
    App\Models\PricingRule::query()->delete();
    App\Models\PricingRule::factory()
        ->revenueBetween(0, 5_000_000)
        ->create(['label' => 'KECIL', 'price' => 25_000]);

    ['tenant' => $tenant, 'owner' => $owner] = makeEstimateContext(revenue: 3_000_000);

    Transaction::factory()->voided()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'total_amount' => 9_000_000,
        'occurred_at' => now()->startOfMonth()->subMonth()->addDays(6),
    ]);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.estimate.revenue', 3_000_000)
        ->where('subsidy.estimate.transaction_count', 1)
    );
});

test('tenant yang sudah di jalur adaptif tidak diberi perkiraan', function () {
    ['owner' => $owner] = makeSubsidyContext();

    actingAs($owner);
    agreeSubsidy();

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.estimate', null)
        ->where('subsidy.is_active', true)
    );
});

// --- Jejak persetujuan di halaman langganan ---

test('halaman langganan menyebut versi, tanggal, dan penyetuju', function () {
    ['owner' => $owner] = makeSubsidyContext();

    actingAs($owner);
    post('/langganan/persetujuan', ['version' => '1', 'agreed' => true]);

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('consent.agreed', true)
        ->where('consent.agreed_version', '1')
        ->where('consent.agreed_at', now()->toDateString())
        ->where('consent.agreed_by', $owner->name)
    );
});

test('persetujuan versi lama dibedakan dari belum pernah menyetujui', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeSubsidyContext();

    // Versi yang sudah tidak berlaku lagi — tenant pernah setuju, tapi bukan
    // pada teks yang berlaku sekarang.
    TenantConsent::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'type' => TenantConsent::TYPE_NORMAL,
        'version' => '0',
    ]);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('consent.agreed', false)
        ->where('consent.agreed_version', '0')
        ->where('consent.current_version', '1')
    );
});
