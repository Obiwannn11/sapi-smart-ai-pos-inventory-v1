<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\TenantMonthlyMetric;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * @return array{tenant: Tenant, platformUser: PlatformUser}
 */
function makePlatformRevenueContext(): array
{
    $tenant = Tenant::factory()->active()->create([
        'name' => 'Kopi Story',
        'pricing_track' => Subscription::TRACK_SUBSIDIZED,
    ]);

    Subscription::factory()->subsidized()->create(['tenant_id' => $tenant->id]);

    // Persetujuan yang masih aktif adalah syarat omzet boleh DIPAKAI, bukan
    // hanya syarat ia boleh dikumpulkan (`[BL-015]`). Tanpa baris ini fixture-nya
    // menggambarkan keadaan yang tak bisa terjadi di produksi — job penghitung
    // omzet tidak akan pernah menghasilkan ringkasan bagi tenant tanpa consent.
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    TenantConsent::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'type' => TenantConsent::TYPE_SUBSIDIZED,
    ]);

    TenantMonthlyMetric::factory()
        ->revenue(3_500_000)
        ->forPeriod(now()->startOfMonth()->subMonth()->format('Y-m'))
        ->create(['tenant_id' => $tenant->id]);

    return [
        'tenant' => $tenant,
        'platformUser' => PlatformUser::factory()->withAllModules()->create(),
    ];
}

// --- Daftar: kelompok saja, bukan angka ---

test('daftar langganan menampilkan kelompok harga tanpa angka rupiahnya', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = makePlatformRevenueContext();

    $response = actingAs($platformUser, 'platform')->get('/platform/subscriptions');

    $response->assertInertia(fn (Assert $page) => $page
        ->where("brackets.{$tenant->id}", 'B')
        ->where('can_view_revenue', true));

    // Angka omzetnya tidak boleh ikut terkirim ke daftar — bedanya halus tapi
    // nyata antara membuka data saat dibutuhkan dan membukanya terus-menerus.
    expect($response->getContent())->not->toContain('3500000');
});

test('pengguna tanpa izin omzet tidak menerima kelompok harga sama sekali', function () {
    ['platformUser' => $berizin] = makePlatformRevenueContext();

    $tanpaIzin = PlatformUser::factory()->create();
    $tanpaIzin->modules()->create(['module' => 'subscriptions']);

    actingAs($tanpaIzin, 'platform')
        ->get('/platform/subscriptions')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_view_revenue', false)
            // Bukan ada tapi kosong — kuncinya memang tidak berisi apa pun.
            ->where('brackets', []));

    expect($berizin->hasModule('revenue_data'))->toBeTrue();
});

// --- Rincian: angka persis, selalu tercatat ---

test('halaman rincian menampilkan angka persis dan mencatat aksesnya', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = makePlatformRevenueContext();

    actingAs($platformUser, 'platform')
        ->get("/platform/revenue/{$tenant->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Revenue/Show')
            ->where('metrics.0.revenue', 3500000)
            ->where('metrics.0.bracket', 'B'));

    $log = PlatformAuditLog::where('action', 'revenue.view')->first();

    // Selalu sensitif, tanpa deduplikasi: inilah yang membuat janji di dokumen
    // persetujuan klien bisa dibuktikan.
    expect($log)->not->toBeNull()
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($log->subject_id)->toBe($tenant->id);
});

test('setiap kunjungan tercatat, bukan hanya yang pertama', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = makePlatformRevenueContext();

    actingAs($platformUser, 'platform');
    get("/platform/revenue/{$tenant->id}");
    get("/platform/revenue/{$tenant->id}");

    expect(PlatformAuditLog::where('action', 'revenue.view')->count())->toBe(2);
});

test('rincian omzet digerbang izinnya sendiri, terpisah dari daftar langganan', function () {
    ['tenant' => $tenant] = makePlatformRevenueContext();

    $hanyaLangganan = PlatformUser::factory()->create();
    $hanyaLangganan->modules()->create(['module' => 'subscriptions']);

    actingAs($hanyaLangganan, 'platform');
    get('/platform/subscriptions')->assertStatus(200);
    get("/platform/revenue/{$tenant->id}")->assertForbidden();
});

test('tenant jalur normal tidak punya halaman rincian omzet', function () {
    ['platformUser' => $platformUser] = makePlatformRevenueContext();

    $normal = Tenant::factory()->active()->create();
    Subscription::factory()->create(['tenant_id' => $normal->id]);

    actingAs($platformUser, 'platform')
        ->get("/platform/revenue/{$normal->id}")
        ->assertNotFound();
});

test('halaman rincian tidak pernah menampilkan laba maupun margin', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = makePlatformRevenueContext();

    $body = actingAs($platformUser, 'platform')->get("/platform/revenue/{$tenant->id}")->getContent();

    expect($body)->not->toContain('"profit"')
        ->not->toContain('"margin"')
        ->not->toContain('"cogs"');
});
