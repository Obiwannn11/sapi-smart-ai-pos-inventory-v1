<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellEvent;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\Upsell\RuleOutcomeResolver;
use App\Services\Upsell\SellableVariantQuery;
use App\Services\Upsell\Strategies\AttachModifierStrategy;
use App\Services\Upsell\Strategies\ManualRuleStrategy;
use App\Services\Upsell\Strategies\PressedStockStrategy;
use App\Services\Upsell\Strategies\UpsizeVariantStrategy;
use App\Services\Upsell\Suggestion;
use App\Services\Upsell\UpsellIndexBuilder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Status hidup per aturan saran jual — paket A + B.
 *
 * Yang dijaga di sini adalah satu keluhan yang tepat: tabel aturan menulis
 * **Aktif** pada baris yang kasir tidak pernah lihat. Tiap test di bawah
 * menyatakan satu sebab yang SEBELUMNYA terbaca "Tampil di kasir", dan empat
 * di antaranya tidak terdeteksi sama sekali oleh pemeriksaan lama di klien:
 * kalah slot, varian kedaluwarsa, produk nonaktif, dan jenis saran dimatikan.
 *
 * Satu test di bawah menjaga hal yang berbeda sifatnya — kesepakatan kunci
 * antara `RuleOutcomeResolver` dan `Suggestion`. Kalau kesepakatan itu lepas,
 * tidak ada yang melempar error: SELURUH aturan owner mendadak berstatus
 * "tidak ikut perebutan slot" dan tidak satu pun test lain yang menyadarinya.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($this->owner);
});

function outcomeVariant(Tenant $tenant, array $variantAttributes = [], array $productAttributes = []): ProductVariant
{
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        ...$productAttributes,
    ]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        // Bawaan yang lolos penjaga kandidat, supaya tiap test hanya menyebut
        // atribut yang benar-benar sedang diuji.
        'stock' => 20,
        'expiry_date' => null,
        ...$variantAttributes,
    ]);
}

/**
 * @return array<string, mixed>
 */
function outcomeFor(Tenant $tenant, UpsellRule $rule): array
{
    $index = app(UpsellIndexBuilder::class)->build($tenant);

    $outcomes = app(RuleOutcomeResolver::class)->resolve($tenant, $index);

    return collect($outcomes)->firstWhere('rule_id', $rule->id);
}

// --- Yang memang muncul ---

test('aturan yang lolos semua penjagaan menyebut nomor slotnya', function () {
    $suggested = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $outcome = outcomeFor($this->tenant, $rule);

    expect($outcome['state'])->toBe('live')
        ->and($outcome['appears'])->toBeTrue()
        ->and($outcome['tone'])->toBe('live')
        ->and($outcome['slot'])->toBe(1)
        ->and($outcome['status'])->toBe('Tampil · slot 1')
        // Tidak masuk daftar "tidak muncul, dan kenapa" — ia muncul.
        ->and($outcome['needs_attention'])->toBeFalse();
});

// --- Empat sebab yang dulu terbaca "Tampil di kasir" ---

test('aturan yang kalah slot tidak lagi mengaku tampil, dan menyebut lawannya', function () {
    // Batas tampil kasir tiga slot; empat aturan manual berarti yang terbawah
    // pasti tergeser. Prioritas menaik, jadi yang prioritasnya 1 kalah.
    $winners = [];

    foreach ([40, 30, 20] as $priority) {
        $winners[$priority] = outcomeVariant($this->tenant, ['price' => 9000]);

        UpsellRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'suggested_variant_id' => $winners[$priority]->id,
            'priority' => $priority,
        ]);
    }

    $loser = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $loser->id,
        'priority' => 1,
    ]);

    $outcome = outcomeFor($this->tenant, $rule);

    expect($outcome['state'])->toBe('lost_slot')
        ->and($outcome['appears'])->toBeFalse()
        ->and($outcome['status'])->toBe('Kalah slot')
        ->and($outcome['slot'])->toBeNull()
        // Penghuni slot TERAKHIR yang masih tampil — lawan yang bisa dikejar,
        // bukan yang di puncak.
        ->and($outcome['detail'])->toContain($winners[20]->product->name)
        ->and($outcome['detail'])->toContain('naikkan urutannya')
        // Sudah terlihat di daftar slot bertanda "Tergeser", jadi tidak
        // diulang di daftar "tidak muncul sama sekali".
        ->and($outcome['needs_attention'])->toBeFalse();
});

test('varian yang sudah kedaluwarsa dilaporkan kedaluwarsa, bukan tampil', function () {
    $suggested = outcomeVariant($this->tenant, [
        'price' => 9000,
        'expiry_date' => now()->subDay(),
    ]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $outcome = outcomeFor($this->tenant, $rule);

    expect($outcome['state'])->toBe('expired')
        ->and($outcome['tone'])->toBe('blocked')
        ->and($outcome['needs_attention'])->toBeTrue()
        ->and($outcome['fix']['href'])->toContain('/owner/products/'.$suggested->product_id);
});

test('produk yang dinonaktifkan dilaporkan nonaktif, bukan tampil', function () {
    $suggested = outcomeVariant($this->tenant, ['price' => 9000], ['is_active' => false]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $outcome = outcomeFor($this->tenant, $rule);

    expect($outcome['state'])->toBe('product_inactive')
        ->and($outcome['tone'])->toBe('blocked')
        ->and($outcome['needs_attention'])->toBeTrue();
});

test('jenis saran manual yang owner matikan membungkam seluruh aturan, dan layar menunjuk Setelan', function () {
    $suggested = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $this->tenant->update(['upsell_manual_enabled' => false]);

    $outcome = outcomeFor($this->tenant->fresh(), $rule);

    expect($outcome['state'])->toBe('type_disabled')
        ->and($outcome['tone'])->toBe('blocked')
        ->and($outcome['needs_attention'])->toBeTrue()
        ->and($outcome['fix']['href'])->toContain('/owner/settings/operations');
});

test('jenis yang dimatikan pemilik SaaS dilaporkan tanpa menawarkan jalan apa pun', function () {
    config()->set('upsell.types.'.UpsellEvent::TYPE_MANUAL, false);

    $suggested = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $outcome = outcomeFor($this->tenant, $rule);

    expect($outcome['state'])->toBe('type_unavailable')
        ->and($outcome['needs_attention'])->toBeTrue()
        // Tidak ada layar yang bisa dibuka owner untuk mengubahnya, dan
        // menawarkan jalan yang tidak bisa ditempuh terbaca seperti izin
        // ([BL-099]).
        ->and($outcome['fix'])->toBeNull();
});

// --- Sebab yang memang sudah terdeteksi, dijaga supaya tidak hilang ---

test('stok habis, jendela belum mulai, dan saklar owner masing-masing punya status sendiri', function () {
    $habis = outcomeVariant($this->tenant, ['price' => 9000, 'stock' => 0]);
    $belum = outcomeVariant($this->tenant, ['price' => 9000]);
    $mati = outcomeVariant($this->tenant, ['price' => 9000]);

    $ruleHabis = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $habis->id,
    ]);

    $ruleBelum = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $belum->id,
        'starts_on' => now()->addWeek()->toDateString(),
    ]);

    $ruleMati = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $mati->id,
        'is_active' => false,
    ]);

    expect(outcomeFor($this->tenant, $ruleHabis)['state'])->toBe('out_of_stock')
        ->and(outcomeFor($this->tenant, $ruleBelum)['state'])->toBe('not_started')
        ->and(outcomeFor($this->tenant, $ruleMati)['state'])->toBe('off');

    // Aturan yang owner matikan sendiri tidak masuk daftar "tidak muncul, dan
    // kenapa": ia tidak sedang bertanya kenapa.
    expect(outcomeFor($this->tenant, $ruleMati)['needs_attention'])->toBeFalse()
        ->and(outcomeFor($this->tenant, $ruleHabis)['needs_attention'])->toBeTrue();
});

// --- Kesepakatan yang gagal dalam diam bila lepas ---

test('kunci yang dihitung dari aturan sama persis dengan kunci sarannya', function () {
    $suggested = outcomeVariant($this->tenant, ['price' => 9000]);
    $trigger = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'trigger_variant_id' => $trigger->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $suggestions = app(ManualRuleStrategy::class)->suggestFor(
        $this->tenant,
        SellableVariantQuery::for($this->tenant)->with('product:id,name')->get(),
    );

    $built = $suggestions[$trigger->id][0];

    expect(Suggestion::keyFor(
        UpsellEvent::TYPE_MANUAL,
        $rule->trigger_variant_id,
        $rule->suggested_variant_id,
    ))->toBe($built->key());
});

test('aturan berpemicu melaporkan slotnya di keranjang yang memuat pemicunya', function () {
    $trigger = outcomeVariant($this->tenant, ['price' => 9000]);
    $suggested = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'trigger_variant_id' => $trigger->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $outcome = outcomeFor($this->tenant, $rule);

    expect($outcome['state'])->toBe('live')
        ->and($outcome['slot'])->toBe(1)
        ->and($outcome['detail'])->toContain('barang pemicunya masuk keranjang');
});

// --- Halaman owner ---

test('status per aturan tiba bersama pratinjau, bukan bersama tabel aturan', function () {
    $suggested = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    get('/owner/upsell-rules')
        ->assertInertia(fn ($page) => $page
            ->component('Owner/UpsellRules/Index')
            // Tabel aturan tidak boleh menunggu perakitan indeks; statusnya
            // belum ada pada cat pertama, dan itu memang yang diinginkan.
            ->missing('outcomes')
            ->loadDeferredProps('pratinjau', fn ($reload) => $reload
                ->where('outcomes.0.rule_id', $rule->id)
                ->where('outcomes.0.state', 'live')
                ->where('outcomes.0.slot', 1)
                // Sependapat dengan pratinjau sampai ke nomor slotnya — inilah
                // alasan keduanya dihitung dari satu indeks yang sama.
                ->where('preview.cart_level.0.wins_slot', true)
            )
        );
});

test('indeks saran dirakit sekali saja walau dua prop tunda memerlukannya', function () {
    outcomeVariant($this->tenant, ['price' => 9000]);

    $builder = Mockery::mock(UpsellIndexBuilder::class, [
        app(AttachModifierStrategy::class),
        app(UpsizeVariantStrategy::class),
        app(PressedStockStrategy::class),
        app(ManualRuleStrategy::class),
    ])->makePartial();

    // `once()`, bukan `atLeast()`: tanpa memo di controller, `preview` dan
    // `outcomes` masing-masing menelusuri seluruh katalog, stok, dan riwayat
    // penjualan — dua kali harga yang sama untuk jawaban yang identik.
    $builder->shouldReceive('build')->once()->passthru();

    $this->instance(UpsellIndexBuilder::class, $builder);

    get('/owner/upsell-rules')
        ->assertInertia(fn ($page) => $page->loadDeferredProps('pratinjau', fn ($reload) => $reload
            ->has('preview')
            ->has('outcomes')
        ));
});

test('pemicu dari produk nonaktif membuat aturannya tidak akan pernah menyala', function () {
    $trigger = outcomeVariant($this->tenant, ['price' => 9000], ['is_active' => false]);
    $suggested = outcomeVariant($this->tenant, ['price' => 9000]);

    $rule = UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'trigger_variant_id' => $trigger->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $outcome = outcomeFor($this->tenant, $rule);

    expect($outcome['state'])->toBe('trigger_inactive')
        ->and($outcome['tone'])->toBe('blocked')
        ->and($outcome['needs_attention'])->toBeTrue();
});
