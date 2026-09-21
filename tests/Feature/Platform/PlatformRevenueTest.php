<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\TenantMonthlyMetric;
use App\Models\User;
use Inertia\Support\Header;
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

/**
 * Permintaan parsial yang membawa daftar langganan beserta kelompok harganya.
 *
 * Daftarnya ditunda ([BL-037]), jadi respons pertamanya tidak memuat satu pun
 * baris — dan mencari angka omzet di respons yang memang kosong tidak
 * membuktikan apa-apa.
 */
function subscriptionListPayload(PlatformUser $platformUser): string
{
    return actingAs($platformUser, 'platform')
        ->get('/platform/subscriptions', [
            Header::PARTIAL_COMPONENT => 'Platform/Subscriptions/Index',
            Header::PARTIAL_ONLY => 'subscriptions,brackets',
        ])
        ->getContent();
}

// --- Daftar: kelompok saja, bukan angka ---

test('daftar langganan menampilkan kelompok harga tanpa angka rupiahnya', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = makePlatformRevenueContext();

    $response = actingAs($platformUser, 'platform')->get('/platform/subscriptions');

    // Kelompok harganya ikut daftar yang ditunda ([BL-037]); izinnya tetap
    // eager karena ialah yang menentukan bentuk halamannya.
    $response->assertInertia(fn (Assert $page) => $page
        ->where('can.revenue', true)
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where("brackets.{$tenant->id}", 'B')));

    // Angka omzetnya tidak boleh ikut terkirim ke daftar — bedanya halus tapi
    // nyata antara membuka data saat dibutuhkan dan membukanya terus-menerus.
    // Diperiksa pada permintaan yang benar-benar membawa daftarnya — dan
    // nama tenantnya dipastikan ikut, supaya permintaan yang salah alamat
    // tidak lulus hanya karena isinya kosong.
    $daftar = subscriptionListPayload($platformUser);

    expect($daftar)->toContain('Kopi Story')
        ->not->toContain('3500000');
});

test('pengguna tanpa izin omzet tidak menerima kelompok harga sama sekali', function () {
    ['platformUser' => $berizin] = makePlatformRevenueContext();

    $tanpaIzin = PlatformUser::factory()->create();
    $tanpaIzin->modules()->create(['module' => 'subscriptions']);

    actingAs($tanpaIzin, 'platform')
        ->get('/platform/subscriptions')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.revenue', false)
            ->loadDeferredProps(fn (Assert $reload) => $reload
                // Bukan ada tapi kosong — kuncinya memang tidak berisi apa pun.
                ->where('brackets', [])));

    expect($berizin->hasModule('revenue_data'))->toBeTrue();
});

// --- Rincian: angka persis, selalu tercatat ---

test('halaman rincian menampilkan angka persis dan mencatat aksesnya', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = makePlatformRevenueContext();

    actingAs($platformUser, 'platform')
        ->get("/platform/tenants/{$tenant->id}/revenue")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants/Show')
            ->where('revenue.metrics.0.revenue', 3500000)
            ->where('revenue.metrics.0.bracket', 'B'));

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
    get("/platform/tenants/{$tenant->id}/revenue");
    get("/platform/tenants/{$tenant->id}/revenue");

    expect(PlatformAuditLog::where('action', 'revenue.view')->count())->toBe(2);
});

test('rincian omzet digerbang izinnya sendiri, terpisah dari daftar langganan', function () {
    ['tenant' => $tenant] = makePlatformRevenueContext();

    $hanyaLangganan = PlatformUser::factory()->create();
    $hanyaLangganan->modules()->create(['module' => 'subscriptions']);

    actingAs($hanyaLangganan, 'platform');
    get('/platform/subscriptions')->assertStatus(200);
    get("/platform/tenants/{$tenant->id}/revenue")->assertForbidden();
});

test('tenant jalur normal tidak punya halaman rincian omzet', function () {
    ['platformUser' => $platformUser] = makePlatformRevenueContext();

    $normal = Tenant::factory()->active()->create();
    Subscription::factory()->create(['tenant_id' => $normal->id]);

    actingAs($platformUser, 'platform')
        ->get("/platform/tenants/{$normal->id}/revenue")
        ->assertNotFound();
});

test('halaman rincian tidak pernah menampilkan laba maupun margin', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = makePlatformRevenueContext();

    $body = actingAs($platformUser, 'platform')->get("/platform/tenants/{$tenant->id}/revenue")->getContent();

    expect($body)->not->toContain('"profit"')
        ->not->toContain('"margin"')
        ->not->toContain('"cogs"');
});
