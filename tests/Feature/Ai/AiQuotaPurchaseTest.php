<?php

use App\Models\AiQuotaPolicy;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Ai\AiQuota;

/**
 * Kuota AI yang DIBELI langganan (`[BL-069]`).
 *
 * Yang diuji di sini adalah tempatnya di urutan pembacaan, bukan angkanya:
 * satu tingkat di ATAS paket, ditambahkan bukan menggantikan, dan tetap
 * terbaca meski paketnya sendiri berjatah nol. Ketiganya adalah keadaan yang
 * kalau salah tidak menghasilkan galat — hanya jatah yang diam-diam berbeda
 * dari yang ditagihkan.
 */
beforeEach(function () {
    config([
        'ai.free_tier.daily_limit' => 5,
        'subscription.ai_quota.block_size' => 5,
    ]);

    $this->tenant = Tenant::factory()->create();
});

/** Instance baru tiap kali — memo per instance jangan bocor antar kasus. */
function purchaseQuota(): AiQuota
{
    return app()->make(AiQuota::class);
}

function subscribeWithBlocks(Tenant $tenant, ?int $aiDaily, array $attributes = []): Subscription
{
    $plan = Plan::factory()->create([
        'limits' => $aiDaily === null ? [] : ['ai_daily' => $aiDaily],
    ]);

    return Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        ...$attributes,
    ]);
}

test('langganan tanpa blok terbaca persis seperti sebelum fitur ini ada', function () {
    subscribeWithBlocks($this->tenant, 15);

    expect(purchaseQuota()->dailyLimitFor($this->tenant))->toBe(15);
});

test('blok yang dibeli menambah di atas batas paket, bukan menggantikannya', function () {
    subscribeWithBlocks($this->tenant, 15, ['purchased_ai_blocks' => 2]);

    // 15 dari paket + (2 blok × 5). Kalau ia menggantikan, jawabannya 10 —
    // dan tenant yang membeli justru turun jatahnya.
    expect(purchaseQuota()->dailyLimitFor($this->tenant))->toBe(25);
});

test('blok tetap terbaca meski paketnya berjatah nol', function () {
    // Keadaan ini nyata: tenant membeli blok lalu turun ke paket tanpa AI.
    // Bloknya masih ditagih bulanan, jadi menelannya berarti menagih kapasitas
    // yang tidak pernah diberikan. Ini bedanya dari promo.
    subscribeWithBlocks($this->tenant, 0, ['purchased_ai_blocks' => 3]);

    expect(purchaseQuota()->dailyLimitFor($this->tenant))->toBe(15);
});

test('promo dijumlahkan di atas paket beserta blok yang dibeli', function () {
    subscribeWithBlocks($this->tenant, 15, ['purchased_ai_blocks' => 1]);
    AiQuotaPolicy::factory()->bonus(10)->create();

    // 15 paket + 5 beli + 10 promo. Urutannya tunggal (`[BL-047]`(b)): promo
    // selalu terakhir, di atas hasil semua lapis sebelumnya.
    expect(purchaseQuota()->dailyLimitFor($this->tenant))->toBe(30);
});

test('kebijakan bawaan platform tetap jadi batas dasar saat paket tak menetapkannya', function () {
    subscribeWithBlocks($this->tenant, null, ['purchased_ai_blocks' => 2]);
    AiQuotaPolicy::factory()->baseline(12)->create();

    expect(purchaseQuota()->dailyLimitFor($this->tenant))->toBe(22);
});

test('pelepasan yang belum berlaku tidak mengurangi jatah hari ini', function () {
    subscribeWithBlocks($this->tenant, 15, [
        'purchased_ai_blocks' => 3,
        'scheduled_ai_blocks' => 1,
        'ai_quota_release_at' => now()->addMonth()->toDateString(),
    ]);

    // Sampai tanggal itu semuanya masih boleh dipakai — sama seperti seat.
    expect(purchaseQuota()->dailyLimitFor($this->tenant))->toBe(30);
});

test('pelepasan yang sudah lewat tanggalnya berlaku', function () {
    subscribeWithBlocks($this->tenant, 15, [
        'purchased_ai_blocks' => 3,
        'scheduled_ai_blocks' => 1,
        'ai_quota_release_at' => now()->subDay()->toDateString(),
    ]);

    expect(purchaseQuota()->dailyLimitFor($this->tenant))->toBe(20);
});

test('hak yang ditagih dibaca dari periode yang ditagih, bukan dari hari ini', function () {
    $subscription = subscribeWithBlocks($this->tenant, 15, [
        'purchased_ai_blocks' => 3,
        'scheduled_ai_blocks' => 1,
        'ai_quota_release_at' => now()->addDays(3)->toDateString(),
    ]);

    // Tagihan terbit sebelum periodenya mulai. Menanyakan keadaan hari ini
    // akan menagih 3 blok untuk periode yang cuma berhak atas 1.
    expect($subscription->entitledAiBlocks())->toBe(3)
        ->and($subscription->entitledAiBlocks(now()->addWeek()))->toBe(1);
});

test('snapshot memisahkan jatah yang dibeli dari jatah paket', function () {
    subscribeWithBlocks($this->tenant, 15, ['purchased_ai_blocks' => 2]);

    $snapshot = purchaseQuota()->snapshotFor($this->tenant);

    // Angka yang lebur terbaca sebagai jatah paket, dan owner kehilangan satu-
    // satunya cara menilai apakah pembelian bulanannya masih layak diteruskan.
    expect($snapshot['daily_limit'])->toBe(25)
        ->and($snapshot['purchased'])->toBe(10)
        ->and($snapshot['purchased_blocks'])->toBe(2)
        ->and($snapshot['limit_source'])->toBe('plan');
});

test('tenant ber-BYOK tidak dijatah, blok atau bukan', function () {
    subscribeWithBlocks($this->tenant, 15, ['purchased_ai_blocks' => 2]);
    $this->tenant->update(['ai_api_key' => 'sk-milik-tenant-sendiri']);

    expect(purchaseQuota()->snapshotFor($this->tenant)['using_free_tier'])->toBeFalse();
});
