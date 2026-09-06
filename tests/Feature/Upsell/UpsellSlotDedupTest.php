<?php

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\Upsell\UpsellIndexBuilder;

use function Pest\Laravel\actingAs;

/**
 * Satu varian hanya boleh mengisi satu slot kasir (`[BL-101]`).
 *
 * Dedup satu-satunya di `rankForCart()` memakai `Suggestion::key()`, dan kunci
 * itu memuat JENIS sarannya. Dua strategi yang kebetulan menunjuk varian yang
 * sama karena itu menghasilkan dua kunci berbeda dan lolos berdua: kasir
 * melihat satu barang dua kali, dengan dua lencana berbeda, memakan dua dari
 * tiga slot yang ada.
 *
 * Bentuk yang paling mudah menemuinya justru bentuk yang paling wajar: owner
 * menulis aturan "dorong Espresso Double", sementara mesin sudah lebih dulu
 * memikirkan hal yang sama sebagai naik ukuran dari Espresso Single.
 *
 * Yang TIDAK boleh ikut terbawa dedup ini ada di test terakhir — saran add-on
 * menunjuk modifier, bukan varian, dan `suggested_variant_id`-nya null untuk
 * semuanya. Menyaring null sebagai satu nilai akan membuang dua add-on yang
 * benar-benar berbeda pada produk yang sama.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($this->owner);
});

/**
 * @param  list<int>  $cart
 * @return list<array<string, mixed>>
 */
function rankDedupCart(Tenant $tenant, array $cart): array
{
    $builder = app(UpsellIndexBuilder::class);

    return $builder->rankForCart($builder->build($tenant), $cart);
}

function makeDedupVariant(Product $product, array $attributes = []): ProductVariant
{
    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'stock' => 20,
        'expiry_date' => null,
        ...$attributes,
    ]);
}

test('varian yang ditunjuk aturan manual dan naik ukuran sekaligus hanya memakai satu slot', function () {
    $espresso = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Espresso',
    ]);

    $single = makeDedupVariant($espresso, ['name' => 'Single', 'price' => 10000]);
    // Selisih 40% — di bawah `upsell.upsize.max_price_gap_ratio`, jadi mesin
    // benar-benar menawarkannya sebagai naik ukuran dari Single.
    $double = makeDedupVariant($espresso, ['name' => 'Double', 'price' => 14000]);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $double->id,
        'note' => 'Dorong Double bulan ini',
    ]);

    $ranked = rankDedupCart($this->tenant, [$single->id]);

    $menunjukDouble = collect($ranked)->where('suggested_variant_id', $double->id);

    expect($menunjukDouble)->toHaveCount(1)
        // Yang bertahan yang skornya tertinggi, dan aturan manual menang
        // karena lantai skornya sendiri — bukan karena urutan strateginya.
        // Owner yang memasang sarannya harus melihat sarannya, bukan tebakan
        // mesin tentang barang yang sama.
        ->and($menunjukDouble->first()['type'])->toBe('manual');
});

test('saran yang kalah dedup juga tidak muncul di pratinjau owner', function () {
    $espresso = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Espresso',
    ]);

    $single = makeDedupVariant($espresso, ['name' => 'Single', 'price' => 10000]);
    $double = makeDedupVariant($espresso, ['name' => 'Double', 'price' => 14000]);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $double->id,
    ]);

    // `rankForCart()` mengembalikan SELURUH kandidat termasuk yang kalah slot —
    // itulah yang dibaca pratinjau ([BL-092]). Yang kalah dedup bukan "kalah
    // slot": ia bukan kandidat sama sekali, jadi menampilkannya sebagai
    // "tergeser batas 3" akan menyuruh owner menaikkan batas yang bukan
    // penyebabnya.
    $types = collect(rankDedupCart($this->tenant, [$single->id]))
        ->where('suggested_variant_id', $double->id)
        ->pluck('type');

    expect($types)->not->toContain('upsize');
});

test('dua add-on berbeda pada produk yang sama tetap hidup berdua', function () {
    // Tanpa ini strategi add-on jatuh ke fallback katalog dan hanya menawarkan
    // satu modifier termurah — tidak cukup untuk menguji apa pun di sini.
    config()->set('upsell.attach.min_support', 1);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = makeDedupVariant($product, ['price' => 10000]);

    $group = ModifierGroup::factory()->create([
        'tenant_id' => $this->tenant->id,
        'is_required' => false,
    ]);
    $product->modifierGroups()->attach($group->id);

    $keju = Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'name' => 'Extra Keju',
        'extra_price' => 5000,
    ]);
    $saus = Modifier::factory()->create([
        'modifier_group_id' => $group->id,
        'name' => 'Extra Saus',
        'extra_price' => 2000,
    ]);

    foreach ([$keju, $saus] as $modifier) {
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

    $attach = collect(rankDedupCart($this->tenant, [$variant->id]))
        ->where('type', 'attach');

    expect($attach->pluck('suggested_modifier_id')->sort()->values()->all())
        ->toBe(collect([$keju->id, $saus->id])->sort()->values()->all());
});

test('kasir memakai dedup yang sama dengan server', function () {
    // Kasir tidak memanggil `rankForCart()` — ia memilih sendiri di
    // `useUpsell.js`, di atas indeks yang sama. Perbaikan yang hanya mendarat
    // di server akan membuat pratinjau owner bersih sementara layar kasir —
    // tempat cacatnya dilaporkan — tetap memuat barang yang sama dua kali.
    $source = file_get_contents(resource_path('js/composables/useUpsell.js'));

    // `toContain()` menerima banyak jarum, bukan pesan — jadi pemeriksaannya
    // dijadikan boolean supaya keterangannya benar-benar jadi keterangan.
    expect(str_contains($source, 'onePerSuggestedVariant'))->toBeTrue(implode("\n", [
        'resources/js/composables/useUpsell.js tidak lagi mendedup per varian.',
        'Selama kasir memilih slotnya sendiri di client, dedup `[BL-101]` harus',
        'ada di KEDUA sisi — server untuk pratinjau owner dan self-order,',
        'client untuk layar kasir.',
    ]));
});
