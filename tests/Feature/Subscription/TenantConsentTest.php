<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\User;
use App\Services\ConsentService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{tenant: Tenant, owner: User, cashier: User}
 */
function makeConsentContext(): array
{
    $tenant = Tenant::factory()->active()->create();
    Subscription::factory()->seats(5)->create(['tenant_id' => $tenant->id]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'cashier' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']),
    ];
}

function agree(string $version = '1'): Illuminate\Testing\TestResponse
{
    return post('/langganan/persetujuan', ['version' => $version, 'agreed' => true]);
}

test('dokumen jalur normal ada dan menyebut apa yang tidak dibuka', function () {
    $document = app(ConsentService::class)->document(TenantConsent::TYPE_NORMAL);

    expect($document['version'])->toBe('1')
        ->and($document['body'])->toContain('Apa yang tidak kami lihat')
        ->and($document['body'])->toContain('Cara mencabut')
        ->and($document['body'])->toContain('Akibat mencabut')
        ->and($document['body'])->toContain('Berapa lama disimpan');
});

test('halaman persetujuan terbuka dan membawa teksnya', function () {
    ['owner' => $owner] = makeConsentContext();

    actingAs($owner);
    get('/langganan/persetujuan')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Consent')
            ->where('document.version', '1')
            ->where('can_agree', true));
});

test('persetujuan tercatat lengkap dengan versi, penyetuju, dan ip', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeConsentContext();

    actingAs($owner);
    agree()->assertRedirect('/langganan');

    $consent = TenantConsent::where('tenant_id', $tenant->id)->firstOrFail();

    expect($consent->type)->toBe(TenantConsent::TYPE_NORMAL)
        ->and($consent->version)->toBe('1')
        ->and($consent->user_id)->toBe($owner->id)
        ->and($consent->agreed_at)->not->toBeNull()
        ->and($consent->ip)->not->toBeNull();
});

test('staf tidak bisa menyetujui', function () {
    ['cashier' => $cashier] = makeConsentContext();

    actingAs($cashier);
    agree()->assertForbidden();

    expect(TenantConsent::count())->toBe(0);
});

test('staf melihat halamannya tapi tanpa tombol setuju', function () {
    ['cashier' => $cashier] = makeConsentContext();

    actingAs($cashier);
    get('/langganan/persetujuan')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->where('can_agree', false));
});

test('kotak centang yang tidak dicentang ditolak', function () {
    ['owner' => $owner] = makeConsentContext();

    actingAs($owner);
    post('/langganan/persetujuan', ['version' => '1', 'agreed' => false])
        ->assertSessionHasErrors('agreed');

    expect(TenantConsent::count())->toBe(0);
});

test('menyetujui versi yang sudah kedaluwarsa ditolak', function () {
    ['owner' => $owner] = makeConsentContext();

    actingAs($owner);

    // Halaman yang dibiarkan terbuka berhari-hari tidak boleh mencatat
    // persetujuan atas teks yang sudah diganti sementara itu.
    agree(version: '0')->assertSessionHas('error');

    expect(TenantConsent::count())->toBe(0);
});

test('persetujuan versi lama tidak dihitung sebagai persetujuan versi berjalan', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeConsentContext();

    TenantConsent::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'version' => '0',
    ]);

    expect(app(ConsentService::class)->hasAgreedToCurrent($tenant, TenantConsent::TYPE_NORMAL))->toBeFalse();
});

test('persetujuan yang dicabut tidak lagi berlaku', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeConsentContext();

    TenantConsent::factory()->revoked()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'version' => '1',
    ]);

    expect(app(ConsentService::class)->hasAgreedToCurrent($tenant, TenantConsent::TYPE_NORMAL))->toBeFalse();
});

test('menyetujui ulang membuat baris baru, tidak menimpa yang lama', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeConsentContext();

    actingAs($owner);
    agree();
    agree();

    expect(TenantConsent::where('tenant_id', $tenant->id)->count())->toBe(2);
});

test('halaman langganan menunjukkan status persetujuannya', function () {
    ['owner' => $owner] = makeConsentContext();

    actingAs($owner);

    get('/langganan')->assertInertia(fn (Assert $page) => $page->where('consent.agreed', false));

    agree();

    get('/langganan')->assertInertia(fn (Assert $page) => $page->where('consent.agreed', true));
});

test('halaman persetujuan tetap terbuka saat tenant ditangguhkan', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeConsentContext();
    $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

    actingAs($owner);

    // Kalau halaman ini ikut terkunci, tenant yang ditangguhkan tidak punya
    // jalan menyelesaikan syarat untuk kembali aktif.
    get('/langganan/persetujuan')->assertStatus(200);
    agree()->assertRedirect('/langganan');
});
