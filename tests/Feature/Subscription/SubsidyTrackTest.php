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
            ->forPeriod(now()->subMonths($bulanLalu)->format('Y-m'))
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
        'occurred_at' => now()->subMonth()->startOfMonth()->addDay(),
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
