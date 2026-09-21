<?php

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\Upsell\UpsellIndexBuilder;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($this->owner);
});

function buildUpsellIndex(Tenant $tenant): array
{
    return app(UpsellIndexBuilder::class)->build($tenant);
}

/**
 * Varian tunggal milik tenant test, dengan produk yang dibuatkan otomatis.
 */
function makeVariant(Tenant $tenant, array $variantAttributes = [], array $productAttributes = []): ProductVariant
{
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        ...$productAttributes,
    ]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        ...$variantAttributes,
    ]);
}

// ── Penjaga kandidat ────────────────────────────────────────────────────────

test('varian yang sudah kedaluwarsa tidak pernah jadi kandidat', function () {
    makeVariant($this->tenant, [
        'stock' => 100,
        'expiry_date' => now()->subDay()->toDateString(),
    ]);

    $index = buildUpsellIndex($this->tenant);

    expect($index['cart_level'])->toBeEmpty();
});

test('varian stok habis tidak pernah jadi kandidat', function () {
    makeVariant($this->tenant, [
        'stock' => 0,
        'expiry_date' => now()->addDays(2)->toDateString(),
    ]);

    $index = buildUpsellIndex($this->tenant);

    expect($index['cart_level'])->toBeEmpty();
});

test('produk nonaktif tidak pernah jadi kandidat', function () {
    makeVariant(
        $this->tenant,
        ['stock' => 20, 'expiry_date' => now()->addDays(2)->toDateString()],
        ['is_active' => false],
    );

    $index = buildUpsellIndex($this->tenant);

    expect($index['cart_level'])->toBeEmpty();
});

test('indeks tidak bocor lintas tenant', function () {
    $otherTenant = Tenant::factory()->create();

    makeVariant($otherTenant, [
        'stock' => 20,
        'expiry_date' => now()->addDay()->toDateString(),
    ]);

    $index = buildUpsellIndex($this->tenant);

    expect($index['cart_level'])->toBeEmpty()
        ->and($index['by_variant'])->toBeEmpty();
});

// ── Barang tertekan ─────────────────────────────────────────────────────────

test('barang mendekati kedaluwarsa muncul sebagai saran cart level', function () {
    $variant = makeVariant($this->tenant, [
        'stock' => 12,
        'price' => 8000,
        'expiry_date' => now()->addDay()->toDateString(),
    ]);

    $index = buildUpsellIndex($this->tenant);

    expect($index['cart_level'])->toHaveCount(1)
        ->and($index['cart_level'][0]['type'])->toBe('pressed_stock')
        ->and($index['cart_level'][0]['reason'])->toBe('near_expiry')
        ->and($index['cart_level'][0]['suggested_variant_id'])->toBe($variant->id);
});

test('mendekati kedaluwarsa dinilai lebih mendesak daripada dead stock', function () {
    $nearExpiry = makeVariant($this->tenant, [
        'stock' => 5,
        'expiry_date' => now()->addDay()->toDateString(),
    ]);

    $deadStock = makeVariant($this->tenant, [
        'stock' => 5,
        'expiry_date' => null,
    ]);

    $index = buildUpsellIndex($this->tenant);

    $ids = array_column($index['cart_level'], 'suggested_variant_id');

    expect($ids)->toBe([$nearExpiry->id, $deadStock->id]);
});

test('varian dead stock dengan kedaluwarsa jauh tidak dilabeli near expiry', function () {
    makeVariant($this->tenant, [
        'stock' => 5,
        'expiry_date' => now()->addMonths(6)->toDateString(),
    ]);

    $index = buildUpsellIndex($this->tenant);

    expect($index['cart_level'][0]['reason'])->toBe('dead_stock');
});

// ── Naik ukuran ─────────────────────────────────────────────────────────────

test('naik ukuran memilih varian termurah di atas harga pemicu', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $small = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Small',
        'price' => 10000,
        'stock' => 20,
    ]);

    $medium = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Medium',
        'price' => 12000,
        'stock' => 20,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Large',
        'price' => 15000,
        'stock' => 20,
    ]);

    $index = buildUpsellIndex($this->tenant);

    $upsize = collect($index['by_variant'][$small->id] ?? [])
        ->firstWhere('type', 'upsize');

    expect($upsize)->not->toBeNull()
        ->and($upsize['suggested_variant_id'])->toBe($medium->id)
        ->and((float) $upsize['extra_amount'])->toBe(2000.0);
});

test('lompatan harga di atas batas rasio tidak disarankan', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $small = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 10000,
        'stock' => 20,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 30000, // +200%, jauh di atas max_price_gap_ratio 0.6
        'stock' => 20,
    ]);

    $index = buildUpsellIndex($this->tenant);

    $types = array_column($index['by_variant'][$small->id] ?? [], 'type');

    expect($types)->not->toContain('upsize');
});

// ── Add-on ──────────────────────────────────────────────────────────────────

test('add on mengambil modifier yang paling sering menyertai varian pemicu', function () {
    config()->set('upsell.attach.min_support', 1);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 10000,
        'stock' => 20,
    ]);

    $group = ModifierGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_required' => false,
    ]);
    $product->modifierGroups()->attach($group->id);

    $populer = Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'name' => 'Extra Keju',
        'extra_price' => 5000,
    ]);

    $jarang = Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'name' => 'Extra Saus',
        'extra_price' => 2000,
    ]);

    // Riwayat: keju dua kali, saus sekali.
    foreach ([[$populer, 2], [$jarang, 1]] as [$modifier, $times]) {
        for ($i = 0; $i < $times; $i++) {
            $transaction = Transaction::factory()->create([
                'tenant_id' => $this->tenant->id,
                'user_id' => $this->owner->id,
                'status' => Transaction::STATUS_COMPLETED,
            ]);

            $item = TransactionItem::create([
                'transaction_id' => $transaction->id,
                'product_variant_id' => $variant->id,
                'variant_name' => 'X',
                'qty' => 1,
                'unit_price' => 10000,
                'subtotal' => 10000,
            ]);

            $item->modifiers()->create([
                'modifier_id' => $modifier->id,
                'modifier_name' => $modifier->name,
                'extra_price' => $modifier->extra_price,
            ]);
        }
    }

    $index = buildUpsellIndex($this->tenant);

    $attach = collect($index['by_variant'][$variant->id] ?? [])
        ->firstWhere('type', 'attach');

    expect($attach)->not->toBeNull()
        ->and($attach['suggested_modifier_id'])->toBe($populer->id)
        ->and($attach['reason'])->toBe('cooccurrence');
});

test('modifier dari grup wajib tidak pernah disarankan', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 10000,
        'stock' => 20,
    ]);

    $group = ModifierGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_required' => true,
    ]);
    $product->modifierGroups()->attach($group->id);

    Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'extra_price' => 5000,
    ]);

    $index = buildUpsellIndex($this->tenant);

    $types = array_column($index['by_variant'][$variant->id] ?? [], 'type');

    expect($types)->not->toContain('attach');
});

test('modifier dari grup yang tidak terpasang ke produk tidak disarankan', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 10000,
        'stock' => 20,
    ]);

    // Grup milik tenant yang sama, tapi TIDAK di-attach ke produk ini.
    $group = ModifierGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_required' => false,
    ]);

    Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'extra_price' => 5000,
    ]);

    $index = buildUpsellIndex($this->tenant);

    $types = array_column($index['by_variant'][$variant->id] ?? [], 'type');

    expect($types)->not->toContain('attach');
});

test('tanpa riwayat sama sekali jatuh ke modifier termurah dan ditandai catalog', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 10000,
        'stock' => 20,
    ]);

    $group = ModifierGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_required' => false,
    ]);
    $product->modifierGroups()->attach($group->id);

    $murah = Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'extra_price' => 2000,
    ]);

    Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'extra_price' => 9000,
    ]);

    $index = buildUpsellIndex($this->tenant);

    $attach = collect($index['by_variant'][$variant->id] ?? [])
        ->firstWhere('type', 'attach');

    expect($attach)->not->toBeNull()
        ->and($attach['suggested_modifier_id'])->toBe($murah->id)
        ->and($attach['reason'])->toBe('catalog');
});

// ── Batas jumlah & jalur keranjang ──────────────────────────────────────────

test('jumlah saran per keranjang dibatasi config', function () {
    config()->set('upsell.max_per_transaction', 1);

    $pemicu = makeVariant($this->tenant, ['stock' => 20, 'price' => 10000]);

    makeVariant($this->tenant, [
        'stock' => 5,
        'expiry_date' => now()->addDay()->toDateString(),
    ]);
    makeVariant($this->tenant, [
        'stock' => 5,
        'expiry_date' => now()->addDays(2)->toDateString(),
    ]);

    $suggestions = app(UpsellIndexBuilder::class)->forCart($this->tenant, [$pemicu->id]);

    expect($suggestions)->toHaveCount(1);
});

test('saran untuk barang yang sudah ada di keranjang dibuang', function () {
    $pemicu = makeVariant($this->tenant, [
        'stock' => 20,
        'expiry_date' => now()->addDay()->toDateString(),
    ]);

    $suggestions = app(UpsellIndexBuilder::class)->forCart($this->tenant, [$pemicu->id]);

    expect($suggestions)->toBeEmpty();
});

test('saklar mati mengosongkan indeks', function () {
    config()->set('upsell.enabled', false);

    makeVariant($this->tenant, [
        'stock' => 5,
        'expiry_date' => now()->addDay()->toDateString(),
    ]);

    $index = buildUpsellIndex($this->tenant);

    expect($index['cart_level'])->toBeEmpty()
        ->and($index['by_variant'])->toBeEmpty();
});
