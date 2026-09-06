<?php

use App\Models\AiAnalysis;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ai\VariantLinkResolver;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Pemetaan nama varian di hasil analisis ke barangnya (`[BL-100]` tahap 2 & 3).
 *
 * Hasil analisis menyebut varian dengan namanya — "margin `Iced` 70%" — dan
 * sampai entri ini jejaknya berhenti di situ: owner membuka Produk di tab lain
 * dan mencocokkan dengan mata.
 *
 * Yang dijaga di sini terutama BATASNYA, bukan tautannya. Nama tidak dijamin
 * unik dan tidak dijamin masih ada, jadi pemetaan nama ke satu id bisa
 * mengembalikan nol, satu, atau banyak — dan ketiganya harus punya perilaku
 * sendiri. Yang "banyak" tidak boleh diam-diam memilih yang pertama: owner
 * yang diantar ke barang keliru tidak punya cara tahu.
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
 * Nama helper di berkas test bersifat GLOBAL begitu seluruh suite jalan —
 * `makeVariant` sudah dipakai UpsellSuggestionTest, dan tabrakannya baru
 * muncul saat berkasnya dijalankan bersama, bukan saat berkas ini sendirian.
 */
function makeLinkableVariant(Tenant $tenant, string $productName, string $variantName, array $productAttributes = []): ProductVariant
{
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'name' => $productName,
        ...$productAttributes,
    ]);

    return ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => $variantName,
    ]);
}

function resolveVariantLinks(Tenant $tenant, array $names): array
{
    return app(VariantLinkResolver::class)->resolve($tenant, $names);
}

// --- Tiga keluaran pemetaan ---

test('nama yang cocok dengan tepat satu varian jadi tautan', function () {
    $variant = makeLinkableVariant($this->tenant, 'Cafe Latte', 'Iced');

    expect(resolveVariantLinks($this->tenant, ['Iced']))->toBe([
        'Iced' => [
            'state' => 'linked',
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
        ],
    ]);
});

test('nama yang tidak punya varian sama sekali ditandai, bukan didiamkan', function () {
    // Keputusan pemilik 2026-09-06. "Produk ini sudah tidak ada di katalog"
    // justru sering informasi yang dicari — mendiamkannya membuat owner
    // mengira sarannya belum ditindaklanjuti.
    expect(resolveVariantLinks($this->tenant, ['Croissant Plain']))
        ->toBe(['Croissant Plain' => ['state' => 'missing']]);
});

test('nama yang cocok dengan dua varian tidak masuk peta sama sekali', function () {
    // Dua produk berbeda boleh punya varian bernama sama. Memilih salah satu
    // diam-diam berarti mengantar owner ke barang yang keliru.
    makeLinkableVariant($this->tenant, 'Cafe Latte', 'Iced');
    makeLinkableVariant($this->tenant, 'Matcha Latte', 'Iced');

    expect(resolveVariantLinks($this->tenant, ['Iced']))->toBe([]);
});

// --- Batas-batas yang mudah salah ---

test('varian produk nonaktif tetap ditautkan, bukan dianggap hilang', function () {
    // Yang ditandai `missing` adalah barang yang sudah TIDAK ADA, bukan yang
    // sedang dimatikan — katalognya menampilkan keduanya dan punya penyaring
    // statusnya sendiri.
    $variant = makeLinkableVariant($this->tenant, 'Croissant', 'Plain', ['is_active' => false]);

    expect(resolveVariantLinks($this->tenant, ['Plain'])['Plain'])
        ->toBe([
            'state' => 'linked',
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
        ]);
});

test('varian tenant lain tidak pernah jadi tautan', function () {
    $lain = Tenant::factory()->create();
    makeLinkableVariant($lain, 'Cafe Latte', 'Iced');

    // Bukan sekadar "tidak tertaut": ia harus jatuh ke `missing`, karena dari
    // sudut pandang toko ini nama itu memang tidak ada di katalognya.
    expect(resolveVariantLinks($this->tenant, ['Iced']))
        ->toBe(['Iced' => ['state' => 'missing']]);
});

test('nama kosong dan spasi berlebih tidak melahirkan baris peta', function () {
    makeLinkableVariant($this->tenant, 'Cafe Latte', 'Iced');

    $map = resolveVariantLinks($this->tenant, ['  Iced  ', '', '   ', 'Iced']);

    expect(array_keys($map))->toBe(['Iced']);
});

// --- Yang dikirim ke layar ---

test('halaman analisis mengirim peta untuk nama yang tercatat di analisisnya', function () {
    $variant = makeLinkableVariant($this->tenant, 'Cafe Latte', 'Iced');

    AiAnalysis::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_COMPLETED,
        'params' => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()],
        'result' => 'Margin Iced 70%. Croissant Plain sudah kedaluwarsa.',
        'context_variants' => ['Iced', 'Croissant Plain'],
    ]);

    get('/owner/ai-analysis')
        ->assertInertia(fn ($page) => $page
            ->component('Owner/AiAnalysis/Index')
            ->where('variantLinks.Iced.state', 'linked')
            ->where('variantLinks.Iced.variant_id', $variant->id)
            ->where('variantLinks.Croissant Plain.state', 'missing')
        );
});

test('analisis tanpa catatan nama tidak melahirkan peta apa pun', function () {
    // Analisis lama lahir sebelum kolomnya ada, dan konteksnya tidak pernah
    // disimpan — jadi tidak ada cara jujur mengetahui nama mana yang dulu
    // disodorkan ke model. Namanya tetap teks biasa.
    makeLinkableVariant($this->tenant, 'Cafe Latte', 'Iced');

    AiAnalysis::create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'type' => AiAnalysis::TYPE_GENERAL,
        'status' => AiAnalysis::STATUS_COMPLETED,
        'params' => ['from' => now()->subDays(7)->toDateString(), 'to' => now()->toDateString()],
        'result' => 'Margin Iced 70%.',
        'context_variants' => null,
    ]);

    get('/owner/ai-analysis')
        ->assertInertia(fn ($page) => $page->where('variantLinks', []));
});

// --- Tujuan tautannya ---

test('tautan varian mendarat pada barangnya di katalog', function () {
    $variant = makeLinkableVariant($this->tenant, 'Cafe Latte', 'Iced');

    get("/owner/products?variant={$variant->id}")
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Products/Index')
            ->where('filters.variant', $variant->id)
            ->where('focus.product_id', $variant->product_id)
            ->where('focus.label', 'Cafe Latte - Iced')
        );
});

test('tautan ke varian yang sudah dihapus mengaku, bukan diam', function () {
    // `filters.variant` tetap terisi sementara `focus` null. Katalog penuh
    // yang muncul tanpa keterangan terbaca seperti tautannya tidak berfungsi.
    get('/owner/products?variant=999999')
        ->assertInertia(fn ($page) => $page
            ->where('filters.variant', 999999)
            ->where('focus', null)
        );
});

test('tautan ke varian tenant lain diperlakukan seperti varian yang tidak ada', function () {
    $lain = Tenant::factory()->create();
    $variant = makeLinkableVariant($lain, 'Cafe Latte', 'Iced');

    get("/owner/products?variant={$variant->id}")
        ->assertInertia(fn ($page) => $page->where('focus', null));
});

test('kunjungan biasa tidak membawa fokus apa pun', function () {
    get('/owner/products')
        ->assertInertia(fn ($page) => $page
            ->where('filters.variant', null)
            ->where('focus', null)
        );
});
