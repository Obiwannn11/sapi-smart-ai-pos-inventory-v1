<?php

use App\Models\DiscountRule;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\Upsell\UpsellIndexBuilder;

use function Pest\Laravel\actingAs;

/**
 * Strip saran mengutip harga yang benar-benar akan ditagih (`[BL-103]` butir 1).
 *
 * Yang dijaga di sini adalah selisih yang TERLIHAT kasir tapi tidak pernah
 * terlihat basis data: `TransactionService::resolveItemPrice()` selalu
 * menghitung ulang harga di server, jadi yang ditagih selalu benar. Yang salah
 * sebelum perbaikan ini adalah angka yang dibacakan kasir kepada pelanggan —
 * dan arahnya selalu sama, strip mengutip LEBIH MAHAL daripada yang akan
 * ditagih, karena potongan hanya pernah menurunkan harga.
 */
beforeEach(function () {
    // Lantai untung 10% dipilih supaya potongan di bawah ini tidak menabraknya
    // kecuali pada test yang memang menguji lantainya.
    $this->tenant = Tenant::factory()->create(['min_margin_percent' => 10]);
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($this->owner);
});

function buildPricedIndex(Tenant $tenant): array
{
    return app(UpsellIndexBuilder::class)->build($tenant);
}

function makePricedVariant(Tenant $tenant, array $variantAttributes = [], ?Product $product = null): ProductVariant
{
    $product ??= Product::factory()->create(['tenant_id' => $tenant->id]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        ...$variantAttributes,
    ]);
}

// ── Barang tertekan ─────────────────────────────────────────────────────────

test('saran barang tertekan mengutip harga berdiskon, bukan harga katalog', function () {
    $variant = makePricedVariant($this->tenant, [
        'price' => 20000,
        'cost_price' => 10000,
        'stock' => 5,
        'expiry_date' => now()->addDays(2)->toDateString(),
    ]);

    // 20% dari 20.000 → 16.000, masih jauh di atas lantai (10.000 × 1,1).
    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 20,
    ]);

    $suggestion = collect(buildPricedIndex($this->tenant)['cart_level'])
        ->firstWhere('type', 'pressed_stock');

    expect($suggestion['suggested_variant_price'])->toBe(16000.0)
        // Baris baru: "tambahan"-nya adalah harga barang itu sendiri.
        ->and($suggestion['extra_amount'])->toBe(16000.0);
});

test('harga tertekan berhenti di lantai untung, sama seperti di grid katalog', function () {
    $variant = makePricedVariant($this->tenant, [
        'price' => 20000,
        'cost_price' => 15000,
        'stock' => 5,
        'expiry_date' => now()->addDays(2)->toDateString(),
    ]);

    // 60% → 8.000, jauh di bawah lantai 15.000 × 1,1 = 16.500.
    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 60,
    ]);

    $suggestion = collect(buildPricedIndex($this->tenant)['cart_level'])
        ->firstWhere('type', 'pressed_stock');

    expect($suggestion['suggested_variant_price'])->toBe(16500.0);
});

// ── Naik ukuran ─────────────────────────────────────────────────────────────

test('naik ukuran menghitung selisih dari dua harga efektif', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $medium = makePricedVariant($this->tenant, [
        'price' => 10000, 'cost_price' => 4000, 'stock' => 5, 'name' => 'Medium',
    ], $product);
    $large = makePricedVariant($this->tenant, [
        'price' => 15000, 'cost_price' => 6000, 'stock' => 5, 'name' => 'Large',
    ], $product);

    // Hanya varian besarnya yang berdiskon: 15.000 → 12.000.
    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $large->id,
        'percent' => 20,
    ]);

    $suggestion = collect(buildPricedIndex($this->tenant)['by_variant'][$medium->id])
        ->firstWhere('type', 'upsize');

    expect($suggestion['suggested_variant_id'])->toBe($large->id)
        ->and($suggestion['suggested_variant_price'])->toBe(12000.0)
        // Bukan 5.000 (selisih katalog): yang dibayar pelanggan cuma 2.000.
        ->and($suggestion['extra_amount'])->toBe(2000.0);
});

test('tangga ukuran tetap ditentukan harga katalog walau varian besarnya lebih murah hari ini', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);

    $medium = makePricedVariant($this->tenant, [
        'price' => 10000, 'cost_price' => 4000, 'stock' => 5, 'name' => 'Medium',
    ], $product);
    $large = makePricedVariant($this->tenant, [
        'price' => 12000, 'cost_price' => 6000, 'stock' => 5, 'name' => 'Large',
    ], $product);

    // 50% → 6.000, tertahan lantai 6.000 × 1,1 = 6.600 yang dibulatkan ke atas
    // jadi 7.000. Varian besar jadi LEBIH MURAH daripada pemicunya hari ini.
    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $large->id,
        'percent' => 50,
    ]);

    $suggestion = collect(buildPricedIndex($this->tenant)['by_variant'][$medium->id])
        ->firstWhere('type', 'upsize');

    // Saran tetap lahir: "naik ukuran" adalah urutan ukuran produk, bukan
    // urutan harga hari ini.
    expect($suggestion['suggested_variant_id'])->toBe($large->id)
        ->and($suggestion['suggested_variant_price'])->toBe(7000.0)
        ->and($suggestion['extra_amount'])->toBe(-3000.0)
        // Rasio negatif ditahan di nol, jadi skornya berhenti di puncak pita
        // 30–40 dan tidak pernah menyaingi strategi lain.
        ->and($suggestion['score'])->toBe(40.0);
});

// ── Aturan manual owner ─────────────────────────────────────────────────────

test('saran yang ditulis owner ikut mengutip harga berdiskon', function () {
    $suggested = makePricedVariant($this->tenant, [
        'price' => 25000,
        'cost_price' => 10000,
        'stock' => 5,
    ]);

    DiscountRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'product_variant_id' => $suggested->id,
        'percent' => 20,
    ]);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    $manual = collect(buildPricedIndex($this->tenant)['cart_level'])
        ->firstWhere('type', 'manual');

    expect($manual['suggested_variant_price'])->toBe(20000.0)
        ->and($manual['extra_amount'])->toBe(20000.0);
});

// ── Varian tanpa aturan ─────────────────────────────────────────────────────

test('varian tanpa aturan diskon tetap dikutip pada harga katalog', function () {
    $variant = makePricedVariant($this->tenant, [
        'price' => 18000,
        'cost_price' => 9000,
        'stock' => 5,
        'expiry_date' => now()->addDays(2)->toDateString(),
    ]);

    $suggestion = collect(buildPricedIndex($this->tenant)['cart_level'])
        ->firstWhere('type', 'pressed_stock');

    expect($suggestion['suggested_variant_id'])->toBe($variant->id)
        ->and($suggestion['suggested_variant_price'])->toBe(18000.0);
});
