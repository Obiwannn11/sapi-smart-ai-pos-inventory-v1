<?php

use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\AiQuota;
use App\Services\SubscriptionService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Alur beli & lepas kuota AI tambahan (`[BL-069]`).
 *
 * Bentuknya sengaja kembar dengan seat, jadi yang diuji di sini adalah tiga
 * hal yang membuat kembaran itu benar-benar berlaku: pembelian berlaku HARI
 * INI, pelepasan berlaku SATU PERIODE PENUH ke depan, dan pembelian membatalkan
 * pelepasan yang masih menunggu.
 */
beforeEach(function () {
    config([
        'ai.free_tier.daily_limit' => 5,
        'subscription.ai_quota.block_size' => 5,
        'subscription.ai_quota.block_price' => 15000,
        'subscription.ai_quota.max_blocks' => 20,
    ]);
});

/**
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function makeAiQuotaContext(int $blocks = 0, int $aiDaily = 15): array
{
    $plan = Plan::factory()->create(['limits' => ['ai_daily' => $aiDaily]]);
    $tenant = Tenant::factory()->active()->create();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'purchased_ai_blocks' => $blocks,
        'current_period_end' => now()->addDays(10)->toDateString(),
        'billing_anchor_day' => now()->addDays(10)->day,
    ]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    return ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription];
}

// --- Pembelian ---

test('membeli blok menaikkan jatah harian mulai hari ini juga', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeAiQuotaContext();

    actingAs($owner);
    post('/langganan/tambah-kuota-ai', ['blocks' => 2])->assertSessionHas('success');

    // Kuota berlaku HARIAN, jadi tak ada alasan menunda pemberlakuannya sampai
    // periode berikutnya — beda dari tagihannya, yang memang menunggu.
    expect(app()->make(AiQuota::class)->dailyLimitFor($tenant->fresh()))->toBe(25);
});

test('pembelian membatalkan pelepasan yang masih menunggu', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeAiQuotaContext(blocks: 2);

    $subscriptions = app(SubscriptionService::class);
    $subscriptions->releaseAiQuota($tenant, 2);

    actingAs($owner);
    post('/langganan/tambah-kuota-ai', ['blocks' => 1])->assertSessionHas('success');

    // Tenant yang berubah pikiran lalu membeli lagi jelas tidak sedang meminta
    // keduanya. Membiarkan jadwalnya hidup berarti blok yang baru dibeli ikut
    // lenyap di tanggal pelepasan, tanpa seorang pun memintanya.
    $subscription = $tenant->fresh()->subscription;

    expect($subscription->purchased_ai_blocks)->toBe(3)
        ->and($subscription->hasPendingAiQuotaRelease())->toBeFalse();
});

test('pembelian di atas batas maksimum ditolak dengan menyebut angkanya', function () {
    config(['subscription.ai_quota.max_blocks' => 3]);
    ['owner' => $owner] = makeAiQuotaContext(blocks: 2);

    actingAs($owner);
    post('/langganan/tambah-kuota-ai', ['blocks' => 2])->assertSessionHas('error');

    // Ditolak, bukan diam-diam dipotong jadi 1: tenant yang mengetik 2 lalu
    // mendapat 1 tanpa diberi tahu akan mengira sisanya hilang.
    expect(Subscription::first()->purchased_ai_blocks)->toBe(2);
});

test('staf tidak boleh membeli kuota atas nama usaha tempatnya bekerja', function () {
    ['tenant' => $tenant] = makeAiQuotaContext();
    $staff = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($staff);
    post('/langganan/tambah-kuota-ai', ['blocks' => 1])->assertForbidden();
});

// --- Pelepasan ---

test('pelepasan dijadwalkan satu periode penuh ke depan, bukan di akhir periode berjalan', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'subscription' => $subscription] = makeAiQuotaContext(blocks: 2);

    actingAs($owner);
    post('/langganan/lepas-kuota-ai', ['blocks' => 1])->assertSessionHas('success');

    $fresh = $subscription->fresh();

    // Tagihan periode berikutnya terbit sebelum periode berjalan habis dan
    // sudah memuat blok itu. Melepasnya di akhir periode berjalan berarti
    // tenant membayar sebulan untuk kuota yang sudah dicabut.
    expect($fresh->scheduled_ai_blocks)->toBe(1)
        ->and($fresh->ai_quota_release_at->toDateString())
        ->toBe($subscription->nextAnchoredDateAfter($subscription->current_period_end)->toDateString());
});

test('jatah harian tetap penuh sampai tanggal pelepasannya tiba', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeAiQuotaContext(blocks: 2);

    actingAs($owner);
    post('/langganan/lepas-kuota-ai', ['blocks' => 2]);

    // Sampai tanggal itu kuotanya masih dibayar, jadi masih boleh dipakai.
    expect(app()->make(AiQuota::class)->dailyLimitFor($tenant->fresh()))->toBe(25);
});

test('melepas lebih banyak daripada yang dimiliki ditolak', function () {
    ['owner' => $owner] = makeAiQuotaContext(blocks: 1);

    actingAs($owner);
    post('/langganan/lepas-kuota-ai', ['blocks' => 3])->assertSessionHas('error');

    expect(Subscription::first()->hasPendingAiQuotaRelease())->toBeFalse();
});

test('tenant tanpa blok tambahan ditolak dengan kalimat, bukan galat', function () {
    ['owner' => $owner] = makeAiQuotaContext();

    actingAs($owner);
    post('/langganan/lepas-kuota-ai', ['blocks' => 1])->assertSessionHas('error');
});

// --- Pemberlakuan terjadwal ---

test('pelepasan yang tanggalnya tiba benar-benar menurunkan jatah', function () {
    ['tenant' => $tenant, 'subscription' => $subscription] = makeAiQuotaContext(blocks: 3);

    $subscription->update([
        'scheduled_ai_blocks' => 1,
        'ai_quota_release_at' => now()->subDay()->toDateString(),
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $fresh = $subscription->fresh();

    // Menumpang di siklus hidup, bukan sebagai jadwal tersendiri: satu jadwal
    // yang lupa dipasang cukup untuk membuat tenant terus tertagih atas kuota
    // yang sudah ia lepas berbulan-bulan lalu.
    expect($fresh->purchased_ai_blocks)->toBe(1)
        ->and($fresh->scheduled_ai_blocks)->toBeNull()
        ->and($fresh->ai_quota_release_at)->toBeNull()
        ->and(app()->make(AiQuota::class)->dailyLimitFor($tenant->fresh()))->toBe(20);
});

test('pelepasan yang tanggalnya belum tiba tidak disentuh siklus hidup', function () {
    ['subscription' => $subscription] = makeAiQuotaContext(blocks: 3);

    $subscription->update([
        'scheduled_ai_blocks' => 1,
        'ai_quota_release_at' => now()->addMonth()->toDateString(),
    ]);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($subscription->fresh()->purchased_ai_blocks)->toBe(3);
});

test('siklus hidup melaporkan pelepasan kuota meski nol', function () {
    makeAiQuotaContext();

    // Dilaporkan meski nol karena satu-satunya cara memastikan pemberlakuannya
    // berjalan adalah melihat angkanya di hari yang dijanjikan kepada tenant.
    artisan('subscriptions:advance-lifecycle')
        ->expectsOutputToContain('Pelepasan kuota AI berlaku')
        ->assertSuccessful();
});

// --- Layar ---

test('halaman langganan mengirim tawaran kuota beserta batas-batasnya', function () {
    ['owner' => $owner] = makeAiQuotaContext(blocks: 2);

    actingAs($owner);

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('aiQuotaOffer.block_size', 5)
        ->where('aiQuotaOffer.block_price', 15000)
        ->where('aiQuotaOffer.purchasable_blocks', 18)
        ->where('aiQuotaOffer.releasable_blocks', 2)
        ->where('aiQuotaOffer.release_at', null)
        // Jatah yang dibeli dipisah dari jatah paket, supaya panel pelepasannya
        // punya angka pembanding di layar yang sama dengan tombolnya.
        ->where('aiQuota.purchased_blocks', 2)
        ->where('aiQuota.purchased', 10)
        ->where('aiQuota.daily_limit', 25)
    );
});

test('pelepasan yang tercatat ikut dikirim beserta tanggalnya', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeAiQuotaContext(blocks: 2);

    $subscription = app(SubscriptionService::class)->releaseAiQuota($tenant, 1);

    actingAs($owner);

    // Tanggalnya wajib ikut: tenant yang tidak tahu kapan pelepasannya berlaku
    // akan mengira kuotanya sudah hilang hari ini.
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('aiQuotaOffer.scheduled_blocks', 1)
        ->where('aiQuotaOffer.release_at', $subscription->ai_quota_release_at->toDateString())
    );
});
