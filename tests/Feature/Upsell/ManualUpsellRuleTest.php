<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\Upsell\UpsellIndexBuilder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * Aturan saran jual yang ditulis owner (`[BL-074]`).
 *
 * Tiga hal yang dijaga di sini, dan ketiganya gagal dalam diam bila salah:
 * aturan manual MENANG saat berebut slot dengan saran mesin, penjaga kandidat
 * tetap berlaku walaupun owner sendiri yang menuliskannya, dan jendela
 * tanggalnya benar-benar dihormati — termasuk aturan yang dijadwalkan dari
 * jauh hari.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($this->owner);
});

function buildManualIndex(Tenant $tenant): array
{
    return app(UpsellIndexBuilder::class)->build($tenant);
}

function makeRuleVariant(Tenant $tenant, array $variantAttributes = [], array $productAttributes = []): ProductVariant
{
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        ...$productAttributes,
    ]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        // Bawaan yang lolos penjaga kandidat, supaya tiap test hanya perlu
        // menyebut atribut yang benar-benar sedang diuji.
        'stock' => 20,
        'expiry_date' => null,
        ...$variantAttributes,
    ]);
}

// --- Bentuk aturan ---

test('aturan tanpa pemicu muncul di cart_level dan bertipe manual', function () {
    $suggested = makeRuleVariant($this->tenant, ['price' => 9000]);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
        'note' => 'Promo bulan ini',
    ]);

    $index = buildManualIndex($this->tenant);

    $manual = collect($index['cart_level'])->firstWhere('type', 'manual');

    expect($manual)->not->toBeNull()
        ->and($manual['reason'])->toBe('owner_rule')
        ->and($manual['suggested_variant_id'])->toBe($suggested->id)
        // Catatan owner dipakai apa adanya — itulah satu-satunya hal yang
        // strategi ini punya dan mesin tidak.
        ->and($manual['note'])->toBe('Promo bulan ini');
});

test('aturan berpemicu dipetakan ke varian pemicunya, bukan ke cart_level', function () {
    $trigger = makeRuleVariant($this->tenant);
    $suggested = makeRuleVariant($this->tenant);

    UpsellRule::factory()->triggeredBy($trigger)->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $index = buildManualIndex($this->tenant);

    expect($index['by_variant'][$trigger->id])->toHaveCount(1)
        ->and($index['by_variant'][$trigger->id][0]['type'])->toBe('manual')
        ->and($index['by_variant'][$trigger->id][0]['suggested_variant_id'])->toBe($suggested->id)
        ->and(collect($index['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

test('aturan tanpa catatan tetap punya keterangan, bukan string kosong', function () {
    $suggested = makeRuleVariant($this->tenant);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
        'note' => null,
    ]);

    $manual = collect(buildManualIndex($this->tenant)['cart_level'])->firstWhere('type', 'manual');

    expect($manual['note'])->toBe('Pilihan pemilik');
});

// --- Menang saat berebut slot ---

test('aturan manual mengalahkan saran mesin paling mendesak sekalipun', function () {
    // Skor tertinggi yang bisa dicapai mesin: barang yang kedaluwarsa HARI INI.
    makeRuleVariant($this->tenant, ['expiry_date' => now()->toDateString()]);

    $suggested = makeRuleVariant($this->tenant);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $cartLevel = collect(buildManualIndex($this->tenant)['cart_level'])
        ->sortByDesc('score')
        ->values();

    // Client menyortir berdasarkan skor lalu memotong di max_per_transaction,
    // jadi lantai skor inilah yang benar-benar memenangkan slotnya. Kalau
    // aturan owner bisa tergeser diam-diam oleh angka yang tidak pernah ia
    // lihat, ia akan menyimpulkan fiturnya rusak — dan ia tidak akan salah.
    expect($cartLevel[0]['type'])->toBe('manual');
});

test('prioritas hanya mengurutkan sesama aturan manual', function () {
    $rendah = makeRuleVariant($this->tenant);
    $tinggi = makeRuleVariant($this->tenant);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $rendah->id,
        'priority' => 1,
    ]);
    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $tinggi->id,
        'priority' => 50,
    ]);

    $manual = collect(buildManualIndex($this->tenant)['cart_level'])
        ->where('type', 'manual')
        ->sortByDesc('score')
        ->values();

    expect($manual[0]['suggested_variant_id'])->toBe($tinggi->id)
        ->and($manual[1]['suggested_variant_id'])->toBe($rendah->id);
});

test('batas tampil kasir tiga saran per penjualan', function () {
    expect(buildManualIndex($this->tenant)['max_per_transaction'])->toBe(3);
});

// --- Penjaga kandidat, tanpa pengecualian ---

test('aturan manual tidak bisa menyarankan barang yang stoknya habis', function () {
    $suggested = makeRuleVariant($this->tenant, ['stock' => 0]);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    // Owner yang menyuruh menawarkan barang habis tidak sedang meminta barang
    // habis ditawarkan — ia hanya belum tahu stoknya nol.
    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

test('aturan manual tidak bisa menyarankan barang yang sudah kedaluwarsa', function () {
    $suggested = makeRuleVariant($this->tenant, ['expiry_date' => now()->subDay()->toDateString()]);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    // Batas keamanan pangan, bukan pilihan bisnis — dan karena itu bukan
    // sesuatu yang boleh ditembus aturan buatan manusia.
    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

test('aturan manual tidak bisa menyarankan produk yang dinonaktifkan', function () {
    $suggested = makeRuleVariant($this->tenant, [], ['is_active' => false]);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

// --- Jendela berlaku dan saklar per aturan ---

test('aturan yang dimatikan tidak sampai ke kasir', function () {
    $suggested = makeRuleVariant($this->tenant);

    UpsellRule::factory()->inactive()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

test('aturan yang dijadwalkan dari jauh hari belum muncul', function () {
    $suggested = makeRuleVariant($this->tenant);

    UpsellRule::factory()->scheduled()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

test('aturan yang jendelanya sudah lewat berhenti muncul', function () {
    $suggested = makeRuleVariant($this->tenant);

    UpsellRule::factory()->expired()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

test('aturan yang jendelanya sedang berjalan muncul', function () {
    $suggested = makeRuleVariant($this->tenant);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addDay()->toDateString(),
    ]);

    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toHaveCount(1);
});

// --- Isolasi tenant ---

test('aturan tenant lain tidak bocor ke indeks', function () {
    $tenantLain = Tenant::factory()->create();
    $suggested = makeRuleVariant($tenantLain);

    UpsellRule::factory()->create([
        'tenant_id' => $tenantLain->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    expect(collect(buildManualIndex($this->tenant)['cart_level'])->where('type', 'manual'))->toBeEmpty();
});

// --- Halaman owner ---

test('halaman aturan hanya untuk owner', function () {
    $kasir = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);

    actingAs($kasir);
    get('/owner/upsell-rules')->assertForbidden();

    actingAs($this->owner);
    get('/owner/upsell-rules')->assertOk();
});

test('owner bisa menambah, menyunting, mematikan, dan menghapus aturan', function () {
    $trigger = makeRuleVariant($this->tenant);
    $suggested = makeRuleVariant($this->tenant);

    post('/owner/upsell-rules', [
        'trigger_variant_id' => $trigger->id,
        'suggested_variant_id' => $suggested->id,
        'note' => 'Stok baru datang',
        'priority' => 5,
        'is_active' => true,
    ])->assertSessionHas('success');

    $rule = UpsellRule::sole();

    expect($rule->trigger_variant_id)->toBe($trigger->id)
        ->and($rule->note)->toBe('Stok baru datang');

    put("/owner/upsell-rules/{$rule->id}", [
        'trigger_variant_id' => null,
        'suggested_variant_id' => $suggested->id,
        'note' => 'Dorong sepanjang bulan',
        'priority' => 9,
        'is_active' => true,
    ])->assertSessionHas('success');

    expect($rule->fresh()->trigger_variant_id)->toBeNull()
        ->and($rule->fresh()->priority)->toBe(9);

    post("/owner/upsell-rules/{$rule->id}/toggle")->assertSessionHas('success');
    expect($rule->fresh()->is_active)->toBeFalse();

    delete("/owner/upsell-rules/{$rule->id}")->assertSessionHas('success');
    expect(UpsellRule::count())->toBe(0);
});

test('aturan tidak boleh menyarankan barang yang sama dengan pemicunya', function () {
    $variant = makeRuleVariant($this->tenant);

    post('/owner/upsell-rules', [
        'trigger_variant_id' => $variant->id,
        'suggested_variant_id' => $variant->id,
    ])->assertSessionHasErrors('suggested_variant_id');
});

test('aturan tidak boleh menunjuk varian milik tenant lain', function () {
    $variantOrangLain = makeRuleVariant(Tenant::factory()->create());

    post('/owner/upsell-rules', [
        'suggested_variant_id' => $variantOrangLain->id,
    ])->assertSessionHasErrors('suggested_variant_id');
});

test('tanggal berakhir sebelum tanggal mulai ditolak', function () {
    $suggested = makeRuleVariant($this->tenant);

    post('/owner/upsell-rules', [
        'suggested_variant_id' => $suggested->id,
        'starts_on' => now()->addDays(5)->toDateString(),
        'ends_on' => now()->addDay()->toDateString(),
    ])->assertSessionHasErrors('ends_on');
});

test('owner tidak bisa menyentuh aturan tenant lain', function () {
    $tenantLain = Tenant::factory()->create();
    $suggested = makeRuleVariant($tenantLain);

    $ruleOrangLain = UpsellRule::factory()->create([
        'tenant_id' => $tenantLain->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    // TenantScope membuat baris toko lain tidak pernah ditemukan route-model
    // binding — 404, bukan 403, dengan alasan yang sama seperti di MediaController.
    delete("/owner/upsell-rules/{$ruleOrangLain->id}")->assertNotFound();

    expect(UpsellRule::withoutGlobalScopes()->count())->toBe(1);
});

// --- Pratinjau slot kasir ([BL-092]) ---

test('pratinjau memperlihatkan saran otomatis dan aturan manual dalam satu daftar', function () {
    // Barang tertekan: kedaluwarsa dekat → ditemukan mesin, bukan ditulis owner.
    makeRuleVariant($this->tenant, ['expiry_date' => now()->addDays(2)->toDateString()]);

    $pilihanOwner = makeRuleVariant($this->tenant);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'trigger_variant_id' => null,
        'suggested_variant_id' => $pilihanOwner->id,
    ]);

    get('/owner/upsell-rules')
        ->assertInertia(fn ($page) => $page
            ->component('Owner/UpsellRules/Index')
            ->missing('preview')
            ->loadDeferredProps('pratinjau', fn ($reload) => $reload
                ->where('preview.enabled', true)
                ->where('preview.max_per_transaction', config('upsell.max_per_transaction'))
                // Kedua sumber berdampingan — inilah separuh kenyataan yang
                // dulu tidak punya layar sama sekali.
                ->where('preview.cart_level.0.is_manual', true)
                ->where('preview.cart_level.0.wins_slot', true)
                ->where('preview.cart_level.1.type', 'pressed_stock')
                ->where('preview.cart_level.1.is_manual', false)
            )
        );
});

test('pratinjau menandai saran yang tergeser batas jumlah slot', function () {
    config(['upsell.max_per_transaction' => 1]);

    foreach (range(1, 3) as $i) {
        $suggested = makeRuleVariant($this->tenant);

        UpsellRule::factory()->create([
            'tenant_id' => $this->tenant->id,
            'trigger_variant_id' => null,
            'suggested_variant_id' => $suggested->id,
            'priority' => 10 - $i,
        ]);
    }

    get('/owner/upsell-rules')
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('pratinjau', fn ($reload) => $reload
                ->where('preview.cart_level.0.wins_slot', true)
                // Yang kalah tetap ditampilkan: pertanyaan owner justru "apa
                // yang tidak muncul gara-gara batas ini?".
                ->where('preview.cart_level.1.wins_slot', false)
                ->where('preview.cart_level.2.wins_slot', false)
            )
        );
});

test('pratinjau menyebutkan jenis saran yang dimatikan lewat config', function () {
    config(['upsell.types.pressed_stock' => false]);

    get('/owner/upsell-rules')
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps('pratinjau', fn ($reload) => $reload
                ->where('preview.disabled_types', ['pressed_stock'])
            )
        );
});
