<?php

use App\Models\AiBlockPrice;
use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Pricing\PublicPricing;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\put;

/**
 * Harga blok kuota AI yang bisa disunting pemilik SaaS (keputusan pemilik
 * 2026-09-16).
 *
 * Dua hal yang diuji di sini terpisah dan keduanya penting: bahwa angkanya
 * BERPINDAH dari config ke tabel, dan bahwa perpindahan itu TIDAK membawa
 * grandfathering — blok yang sudah dibeli ikut harga baru. Yang kedua adalah
 * perkecualian satu-satunya di halaman itu, jadi ia harus punya tes yang gagal
 * bila seseorang kelak "memperbaikinya" jadi seragam dengan tetangganya.
 */
beforeEach(function () {
    config([
        'subscription.ai_quota.block_price' => 15_000,
        'subscription.ai_quota.block_size' => 5,
    ]);
});

function actAsPricingOwner(): PlatformUser
{
    $user = PlatformUser::factory()->withAllModules()->create();
    actingAs($user, 'platform');

    return $user;
}

/**
 * Tenant berlangganan yang periodenya hampir habis, dengan blok yang SUDAH
 * dibeli sebelum harganya diubah.
 *
 * @return array{tenant: Tenant, subscription: Subscription}
 */
function tenantHoldingAiBlocks(int $blocks = 2): array
{
    $plan = Plan::factory()->create(['base_price' => 100_000]);
    $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);

    $periodEnd = now()->addDays(3)->startOfDay();

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'current_period_start' => $periodEnd->copy()->subMonthNoOverflow()->toDateString(),
        'current_period_end' => $periodEnd->toDateString(),
        'billing_anchor_day' => $periodEnd->day,
        'trial_ends_at' => $periodEnd,
        'purchased_ai_blocks' => $blocks,
    ]);

    return ['tenant' => $tenant, 'subscription' => $subscription];
}

// --- Panel ---

test('selama belum pernah disetel, yang berlaku tetap bawaan config', function () {
    actAsPricingOwner();

    get('/platform/pricing-rules')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/PricingRules/Index')
            ->where('aiBlockPrice.value', 15000)
            // Bedanya harus terkirim, bukan diselesaikan jadi satu angka:
            // "Rp 15.000 karena seseorang menetapkannya" dan "Rp 15.000 karena
            // itu bawaan berkas config" adalah dua keadaan berbeda.
            ->where('aiBlockPrice.is_custom', false)
            ->where('aiBlockPrice.block_size', 5));
});

test('harga yang disetel menggantikan bawaan config', function () {
    actAsPricingOwner();

    put('/platform/ai-block-price', ['block_price' => 20000])->assertRedirect();

    expect(AiBlockPrice::current())->toBe(20000.0)
        ->and(AiBlockPrice::query()->count())->toBe(1);

    get('/platform/pricing-rules')
        ->assertInertia(fn (Assert $page) => $page
            ->where('aiBlockPrice.value', 20000)
            ->where('aiBlockPrice.is_custom', true));
});

test('menyetel ulang menyunting baris yang sama, tidak menumpuk baris baru', function () {
    actAsPricingOwner();

    put('/platform/ai-block-price', ['block_price' => 20000]);
    put('/platform/ai-block-price', ['block_price' => 12500]);

    expect(AiBlockPrice::query()->count())->toBe(1)
        ->and(AiBlockPrice::current())->toBe(12500.0);
});

test('harga yang berlaku ikut terbaca halaman publik', function () {
    AiBlockPrice::put(20000);

    expect(app(PublicPricing::class)->snapshot()['ai_quota']['block_price'])->toBe(20000.0);
});

// --- Batas dan gerbang ---

test('harga minus ditolak', function () {
    actAsPricingOwner();

    put('/platform/ai-block-price', ['block_price' => -1])
        ->assertSessionHasErrors('block_price');

    expect(AiBlockPrice::query()->count())->toBe(0);
});

test('angka di luar akal ditolak, karena satu salah ketik menimpa semua pembeli', function () {
    actAsPricingOwner();

    put('/platform/ai-block-price', ['block_price' => 99_000_000])
        ->assertSessionHasErrors('block_price');
});

test('penyuntingan digerbang modul harga', function () {
    $tanpaIzin = PlatformUser::factory()->create();
    $tanpaIzin->modules()->create(['module' => 'ai_quota']);

    actingAs($tanpaIzin, 'platform');

    // Pemegang modul kuota AI bukan dengan sendirinya penetap harganya: yang
    // satu menyentuh tagihan kunci bersama milik pemilik SaaS, yang satu
    // menyentuh tarif yang dibayar tenant.
    put('/platform/ai-block-price', ['block_price' => 1])->assertForbidden();
});

test('perubahannya tercatat sebagai kejadian sensitif berikut nilai lamanya', function () {
    actAsPricingOwner();

    put('/platform/ai-block-price', ['block_price' => 20000]);

    $log = PlatformAuditLog::where('action', 'ai-block-price.update')->firstOrFail();

    expect($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($log->meta['before'])->toEqual(15000)
        ->and($log->meta['after'])->toEqual(20000)
        // Penyuntingan pertama mencabut angka bawaan config untuk selamanya —
        // peristiwa yang berbeda dari penyuntingan berikutnya.
        ->and($log->meta['was_config_default'])->toBeTrue();
});

// --- Keputusan pemilik: TIDAK ada grandfathering ---

test('blok yang sudah dibeli ikut harga baru pada tagihan berikutnya', function () {
    ['tenant' => $tenant] = tenantHoldingAiBlocks(blocks: 2);

    AiBlockPrice::put(20000);

    $this->artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    // Rp 100.000 paket + 2 blok × Rp 20.000 — bukan × Rp 15.000 yang berlaku
    // saat bloknya dibeli. Inilah keputusan pemilik 2026-09-16, dan kalau tes
    // ini gagal karena seseorang menambahkan pembekuan tarif, yang berubah
    // adalah kebijakannya, bukan cuma angkanya.
    expect((float) $invoice->amount)->toBe(140000.0)
        ->and($invoice->pricing_context['billing_breakdown']['ai_block_price'])->toEqual(20000);
});

test('tagihan yang sudah terbit tetap memegang tarif lamanya', function () {
    ['tenant' => $tenant] = tenantHoldingAiBlocks(blocks: 1);

    $this->artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    AiBlockPrice::put(20000);

    // Yang menjaga tagihan lama bisa dijelaskan bukan grandfathering, melainkan
    // rincian yang sudah dibekukan di tagihannya sendiri.
    expect($invoice->fresh()->pricing_context['billing_breakdown']['ai_block_price'])->toEqual(15000)
        ->and((float) $invoice->fresh()->amount)->toBe(115000.0);
});
