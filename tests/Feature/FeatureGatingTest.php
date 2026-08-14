<?php

use App\Jobs\RunAiAnalysisJob;
use App\Models\AiAnalysis;
use App\Models\AiUsage;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Ai\AiProviderFactory;
use App\Services\AiContextService;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

beforeEach(function () {
    config([
        'ai.default' => 'gemini',
        'ai.free_tier.key' => 'shared-free-key',
        'ai.free_tier.daily_limit' => 5,
    ]);
});

/**
 * @return array{tenant: Tenant, owner: User}
 */
function makeFlagContext(array $flags = []): array
{
    $tenant = Tenant::factory()->create($flags);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    return ['tenant' => $tenant, 'owner' => $owner];
}

// ── Fondasi ────────────────────────────────────────────────────────────────

test('nama fitur yang tidak dikenal menjawab false', function () {
    ['tenant' => $tenant] = makeFlagContext(['ai_enabled' => true]);

    // Gagal tertutup: salah ketik harus MENUTUP pintu, bukan membukanya
    // diam-diam.
    expect($tenant->hasFeature('kitchn_queue'))->toBeFalse()
        ->and($tenant->hasFeature(''))->toBeFalse();
});

test('tenant yang baru dibuat sudah memegang default tanpa dibaca ulang dari database', function () {
    $tenant = new Tenant;

    // Mengunci blok $attributes. Tanpa itu hasFeature() membaca `null` pada
    // instance yang belum tersimpan dan menjawab false — termasuk untuk
    // ai_enabled yang seharusnya true.
    expect($tenant->hasFeature('ai'))->toBeTrue()
        ->and($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->hasFeature('self_order'))->toBeFalse();
});

test('flag satu tenant tidak memengaruhi tenant lain', function () {
    ['tenant' => $a] = makeFlagContext(['kitchen_queue_enabled' => true]);
    ['tenant' => $b] = makeFlagContext(['kitchen_queue_enabled' => false]);

    expect($a->hasFeature('kitchen_queue'))->toBeTrue()
        ->and($b->hasFeature('kitchen_queue'))->toBeFalse();
});

// ── Self-order ─────────────────────────────────────────────────────────────

test('self order yang dimatikan menolak pesanan dengan json berkode', function () {
    ['owner' => $owner] = makeFlagContext(['self_order_enabled' => false]);

    Sanctum::actingAs($owner);

    // Kode yang stabil, bukan kalimatnya: n8n mencocokkan kode.
    $this->postJson('/api/v1/orders', [])
        ->assertStatus(403)
        ->assertJsonPath('code', 'feature_disabled')
        ->assertJsonPath('success', false);
});

test('self order yang hidup meneruskan pesanan ke validasi', function () {
    ['owner' => $owner] = makeFlagContext(['self_order_enabled' => true]);

    Sanctum::actingAs($owner);

    // 422 dari validasi controller — gerbangnya dilewati, bukan menutup.
    $this->postJson('/api/v1/orders', [])->assertStatus(422);
});

test('saran upsell ikut tertutup saat self order mati', function () {
    ['owner' => $owner] = makeFlagContext(['self_order_enabled' => false]);

    Sanctum::actingAs($owner);

    // Ia hanya berguna untuk permukaan self-order, dan tetap membocorkan
    // barang mana yang sedang tertekan stoknya kalau dibiarkan terbuka.
    $this->postJson('/api/v1/upsell/suggestions', ['variant_ids' => []])
        ->assertStatus(403)
        ->assertJsonPath('code', 'feature_disabled');
});

test('katalog tetap terbuka meski self order mati', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeFlagContext(['self_order_enabled' => false]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    ProductVariant::factory()->create(['product_id' => $product->id]);

    Sanctum::actingAs($owner);

    // Katalog bukan pemesanan, dan endpoint yang sama dipakai jalur mobile.
    $this->getJson('/api/v1/products')->assertStatus(200);
});

test('memajukan pesanan ikut tertutup saat self order mati', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeFlagContext(['self_order_enabled' => false]);

    // Transaksi sungguhan: route model binding berjalan lebih dulu, jadi id
    // karangan akan menjawab 404 dan tidak membuktikan apa pun soal gerbang.
    $transaction = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
    ]);

    Sanctum::actingAs($owner);

    $this->patchJson("/api/v1/orders/{$transaction->id}/fulfillment")
        ->assertStatus(403)
        ->assertJsonPath('code', 'feature_disabled');
});

// ── AI ─────────────────────────────────────────────────────────────────────

test('ai yang dimatikan menolak halaman analisis dengan sebab fitur', function () {
    ['owner' => $owner] = makeFlagContext(['ai_enabled' => false]);

    actingAs($owner);

    // Owner melewati gerbang permission (bypass ['*']), jadi yang menolak di
    // sini pasti gerbang fitur — dan pesannya harus menyebut fitur, bukan izin,
    // supaya owner tidak dikirim memeriksa halaman Role.
    get('/owner/ai-analysis')
        ->assertStatus(403)
        ->assertSee('Fitur ini tidak aktif untuk outlet Anda.');
});

test('ai yang hidup membuka halaman analisis seperti biasa', function () {
    ['owner' => $owner] = makeFlagContext(['ai_enabled' => true]);

    actingAs($owner);

    get('/owner/ai-analysis')->assertStatus(200);
});

test('job memeriksa flag sebelum kuota dan sebelum provider dipanggil', function () {
    Http::fake();

    ['tenant' => $tenant, 'owner' => $owner] = makeFlagContext(['ai_enabled' => false]);

    $analysis = AiAnalysis::create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_PENDING,
        'params' => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()],
    ]);

    (new RunAiAnalysisJob($analysis->id))->handle(
        app(AiContextService::class),
        app(AiProviderFactory::class),
    );

    expect($analysis->fresh()->status)->toBe(AiAnalysis::STATUS_FAILED)
        ->and($analysis->fresh()->error)->toContain('Fitur AI tidak aktif');

    Http::assertNothingSent();

    // Kuota tidak boleh ikut terpakai — inilah kenapa urutannya flag dulu,
    // kuota kemudian.
    $usage = AiUsage::withoutGlobalScopes()
        ->where('tenant_id', $tenant->id)
        ->whereDate('date', now())
        ->value('count');

    expect($usage)->toBeNull();
});

test('flag mati mengalahkan kuota yang masih tersedia', function () {
    Http::fake();

    ['tenant' => $tenant, 'owner' => $owner] = makeFlagContext(['ai_enabled' => false]);

    // Kuota penuh tersedia; yang menggagalkan harus tetap flag.
    $analysis = AiAnalysis::create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_PENDING,
        'params' => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()],
    ]);

    (new RunAiAnalysisJob($analysis->id))->handle(
        app(AiContextService::class),
        app(AiProviderFactory::class),
    );

    expect($analysis->fresh()->error)->toContain('Fitur AI tidak aktif')
        ->and($analysis->fresh()->error)->not->toContain('Kuota');
});

// ── MCP ────────────────────────────────────────────────────────────────────

test('mcp ditolak saat ai dimatikan untuk tenant itu', function () {
    ['owner' => $owner] = makeFlagContext(['ai_enabled' => false]);

    Sanctum::actingAs($owner);

    // Sebelum hari ini satu-satunya cara menghentikan akses MCP adalah
    // mencabut tokennya. Sekarang ada saklarnya.
    $this->postJson('/mcp/business', [
        'jsonrpc' => '2.0',
        'id' => 1,
        'method' => 'tools/list',
    ])
        ->assertStatus(403)
        ->assertJsonPath('code', 'feature_disabled');
});

// ── Settings ───────────────────────────────────────────────────────────────

test('owner bisa mengubah flag dari settings', function () {
    ['tenant' => $tenant, 'owner' => $owner] = makeFlagContext([
        'kitchen_queue_enabled' => false,
        'ai_enabled' => true,
    ]);

    actingAs($owner);

    $this->patch('/owner/settings/operations', [
        'kitchen_queue_enabled' => true,
        'self_order_enabled' => false,
        'ai_enabled' => false,
    ])->assertSessionHasNoErrors();

    $tenant->refresh();

    expect($tenant->hasFeature('kitchen_queue'))->toBeTrue()
        ->and($tenant->hasFeature('self_order'))->toBeFalse()
        ->and($tenant->hasFeature('ai'))->toBeFalse();
});
