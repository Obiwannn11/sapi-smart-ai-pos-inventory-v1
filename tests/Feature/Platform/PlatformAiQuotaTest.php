<?php

use App\Models\AiQuotaPolicy;
use App\Models\AiUsage;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Tenant;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Halaman kuota AI di panel platform (`[BL-047]`(c) dan (d)).
 */
beforeEach(function () {
    config(['ai.free_tier.daily_limit' => 5]);
});

function actAsAiQuotaOwner(): PlatformUser
{
    $user = PlatformUser::factory()->withAllModules()->create();
    actingAs($user, 'platform');

    return $user;
}

// --- Gerbang modul ---

test('halaman kuota AI digerbang modulnya sendiri', function () {
    $tanpaIzin = PlatformUser::factory()->create();
    // Pemegang modul harga BUKAN dengan sendirinya pemegang modul kuota: yang
    // satu menyentuh tarif yang dibayar tenant, yang satu menyentuh tagihan
    // kunci bersama milik pemilik SaaS.
    $tanpaIzin->modules()->create(['module' => 'pricing_rules']);

    actingAs($tanpaIzin, 'platform');
    get('/platform/ai-quota')->assertForbidden();
    post('/platform/ai-quota/reset')->assertForbidden();

    actAsAiQuotaOwner();
    get('/platform/ai-quota')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/AiQuota/Index')
            ->has('policies')
            ->where('configDefault', 5));
});

test('halaman menyebutkan angka yang benar-benar berlaku hari ini', function () {
    actAsAiQuotaOwner();

    AiQuotaPolicy::factory()->baseline(12)->create(['label' => 'Bawaan 2026']);
    AiQuotaPolicy::factory()->bonus(4)->create(['label' => 'Promo Agustus']);
    AiQuotaPolicy::factory()->bonus(99)->expired()->create(['label' => 'Promo Juli']);

    get('/platform/ai-quota')
        ->assertInertia(fn (Assert $page) => $page
            ->where('effective.baseline', 12)
            ->where('effective.baseline_label', 'Bawaan 2026')
            // Promo yang sudah kedaluwarsa tidak boleh ikut terbaca sebagai
            // yang berlaku — itu bentuk kegagalan yang paling mahal di sini.
            ->where('effective.bonus', 4)
            ->where('effective.bonus_label', 'Promo Agustus')
            ->has('policies', 3));
});

// --- CRUD kebijakan ---

test('kebijakan bisa diterbitkan dari panel dan tercatat di jejak audit', function () {
    actAsAiQuotaOwner();

    post('/platform/ai-quota/policies', [
        'label' => 'Bawaan 2026',
        'mode' => 'baseline',
        'daily_limit' => 12,
        'effective_from' => now()->toDateString(),
        'effective_until' => null,
    ])->assertRedirect();

    $policy = AiQuotaPolicy::sole();

    expect($policy->daily_limit)->toBe(12)
        ->and($policy->mode)->toBe(AiQuotaPolicy::MODE_BASELINE);

    $log = PlatformAuditLog::where('action', 'ai-quota.create')->sole();

    expect($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($log->meta['daily_limit'])->toBe(12);
});

test('penyuntingan mencatat nilai lama dan baru', function () {
    actAsAiQuotaOwner();

    $policy = AiQuotaPolicy::factory()->baseline(10)->create(['label' => 'Bawaan']);

    put("/platform/ai-quota/policies/{$policy->id}", [
        'label' => 'Bawaan',
        'mode' => 'baseline',
        'daily_limit' => 25,
        'effective_from' => $policy->effective_from->toDateString(),
        'effective_until' => null,
    ])->assertRedirect();

    $log = PlatformAuditLog::where('action', 'ai-quota.update')->sole();

    // "Naik dari berapa" adalah pertanyaan yang muncul justru saat angkanya
    // dipersoalkan, dan jawabannya harus ada di barisnya sendiri.
    expect($log->meta['before']['daily_limit'])->toBe(10)
        ->and($log->meta['after']['daily_limit'])->toBe(25);
});

test('promo yang sedang berjalan bisa diakhiri lebih cepat', function () {
    actAsAiQuotaOwner();

    $promo = AiQuotaPolicy::factory()->bonus(50)->create([
        'effective_from' => now()->subDays(3)->toDateString(),
        'effective_until' => now()->addMonth()->toDateString(),
    ]);

    // Satu-satunya jalan keluar dari promo yang telanjur kebablasan — dan
    // alasan halaman ini tidak meniru larangan sunting milik `pricing_rules`.
    put("/platform/ai-quota/policies/{$promo->id}", [
        'label' => $promo->label,
        'mode' => $promo->mode,
        'daily_limit' => $promo->daily_limit,
        'effective_from' => $promo->effective_from->toDateString(),
        'effective_until' => now()->subDay()->toDateString(),
    ])->assertRedirect();

    expect($promo->fresh()->isEffective())->toBeFalse();
});

test('tanggal akhir sebelum tanggal mulai ditolak', function () {
    actAsAiQuotaOwner();

    post('/platform/ai-quota/policies', [
        'label' => 'Salah tanggal',
        'mode' => 'bonus',
        'daily_limit' => 5,
        'effective_from' => now()->toDateString(),
        'effective_until' => now()->subDay()->toDateString(),
    ])->assertSessionHasErrors('effective_until');

    expect(AiQuotaPolicy::count())->toBe(0);
});

test('mode di luar katalog ditolak', function () {
    actAsAiQuotaOwner();

    post('/platform/ai-quota/policies', [
        'label' => 'Mode asing',
        'mode' => 'unlimited',
        'daily_limit' => 5,
        'effective_from' => now()->toDateString(),
        'effective_until' => null,
    ])->assertSessionHasErrors('mode');
});

test('kebijakan yang dihentikan tetap tersimpan sebagai riwayat', function () {
    actAsAiQuotaOwner();

    $policy = AiQuotaPolicy::factory()->baseline(20)->create();

    delete("/platform/ai-quota/policies/{$policy->id}")->assertRedirect();

    expect(AiQuotaPolicy::count())->toBe(0)
        ->and(AiQuotaPolicy::withTrashed()->count())->toBe(1)
        ->and(PlatformAuditLog::where('action', 'ai-quota.delete')->exists())->toBeTrue();
});

// --- Reset pemakaian ---

test('reset menolkan pemakaian hari ini dan mencatat berapa tenant tersentuh', function () {
    actAsAiQuotaOwner();

    $satu = Tenant::factory()->create();
    $dua = Tenant::factory()->create();

    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $satu->id, 'date' => now()->toDateString(), 'count' => 3]);
    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $dua->id, 'date' => now()->toDateString(), 'count' => 1]);
    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $satu->id, 'date' => now()->subDay()->toDateString(), 'count' => 5]);

    post('/platform/ai-quota/reset')->assertRedirect();

    expect(AiUsage::withoutGlobalScopes()->whereDate('date', now())->count())->toBe(0)
        ->and(AiUsage::withoutGlobalScopes()->count())->toBe(1);

    $log = PlatformAuditLog::where('action', 'ai-quota.reset')->sole();

    expect($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        // Berapa tenant, bukan tenant yang mana: rincian per tenant bukan hal
        // yang boleh dilihat panel platform.
        ->and($log->meta['tenants_affected'])->toBe(2)
        ->and($log->meta)->not->toHaveKey('tenants');
});

test('reset tidak mengubah satu pun angka kebijakan', function () {
    actAsAiQuotaOwner();

    $policy = AiQuotaPolicy::factory()->baseline(20)->create();
    $tenant = Tenant::factory()->create();
    AiUsage::withoutGlobalScopes()->create(['tenant_id' => $tenant->id, 'date' => now()->toDateString(), 'count' => 3]);

    post('/platform/ai-quota/reset')->assertRedirect();

    expect($policy->fresh()->daily_limit)->toBe(20);
});
