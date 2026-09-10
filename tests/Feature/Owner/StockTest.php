<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $this->product->id,
        'stock' => 50,
    ]);
});

test('owner can view stock management', function () {
    $this->actingAs($this->owner)
        ->get('/owner/stock')
        ->assertStatus(200);
});

test('owner can restock variant', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/restock", [
            'qty' => 20,
            'notes' => 'Restock dari supplier',
        ])
        ->assertSessionHas('success');

    expect($this->variant->fresh()->stock)->toBe(70);
});

test('owner can adjust stock positively', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/adjust", [
            'qty' => 5,
            'notes' => 'Temuan audit',
        ])
        ->assertSessionHas('success');

    expect($this->variant->fresh()->stock)->toBe(55);
});

test('owner can adjust stock negatively', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/adjust", [
            'qty' => -3,
            'notes' => 'Barang rusak',
        ])
        ->assertSessionHas('success');

    expect($this->variant->fresh()->stock)->toBe(47);
});

test('stock adjustment cannot make stock negative', function () {
    $this->actingAs($this->owner)
        ->post("/owner/stock/{$this->variant->id}/adjust", [
            'qty' => -999,
            'notes' => 'Too many',
        ])
        ->assertSessionHas('error');

    expect($this->variant->fresh()->stock)->toBe(50);
});

/**
 * Daftar stok: satu baris per varian, disaring dan dipaginasi di server.
 *
 * Ujinya hampir selalu memakai `?q=` untuk mengunci satu produk. Bukan karena
 * pencariannya yang diuji, tapi karena `beforeEach` di atas sudah menaruh satu
 * varian bernama acak di tenant yang sama — tanpa dikunci, urutan barisnya
 * bergantung pada kata yang kebetulan dipilih faker.
 */

/**
 * Varian dengan nama yang bisa dicari, di produk sendiri.
 */
function stockVariant(Tenant $tenant, string $productName, array $attributes = [], ?Category $category = null): ProductVariant
{
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category?->id,
        'name' => $productName,
    ]);

    return ProductVariant::factory()->create([...$attributes, 'product_id' => $product->id]);
}

/**
 * SKU tiap baris yang muncul untuk satu query string, dalam urutan kirim.
 *
 * Callback-nya ditulis sebagai closure penuh, bukan arrow function: `fn`
 * menyalin variabel luarnya, jadi apa pun yang ditulis dari dalamnya tak
 * pernah sampai kembali ke pemanggil — dan ujinya lulus atas daftar kosong.
 *
 * @return list<string>
 */
function stockRowSkus(object $case, string $query): array
{
    $skus = [];

    $case->get('/owner/stock?'.$query)
        ->assertInertia(function (Assert $page) use (&$skus) {
            $page->loadDeferredProps('stock', function (Assert $reload) use (&$skus) {
                $skus = collect($reload->toArray()['props']['variants']['data'])
                    ->pluck('sku')
                    ->all();
            });
        });

    return $skus;
}

test('stock list flattens each variant with its product and category', function () {
    $category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Minuman',
    ]);
    stockVariant($this->tenant, 'Kopi Susu', [
        'name' => 'Large',
        'sku' => 'KS-L',
        'stock' => 12,
        'expiry_date' => null,
    ], $category);

    $this->actingAs($this->owner)
        ->get('/owner/stock?q=Kopi+Susu')
        ->assertInertia(fn (Assert $page) => $page
            ->component('Owner/Stock/Index')
            ->has('filters')
            ->has('categories', 1)
            // Baris dan ringkasannya ditunda ([BL-037]) — belum ada di
            // tanggapan pertama.
            ->missing('variants')
            ->missing('summary')
            ->loadDeferredProps('stock', fn (Assert $reload) => $reload
                ->has('variants.data', 1)
                ->where('variants.data.0.product_name', 'Kopi Susu')
                ->where('variants.data.0.category_name', 'Minuman')
                ->where('variants.data.0.name', 'Large')
                ->where('variants.data.0.sku', 'KS-L')
                ->where('variants.data.0.stock', 12)
                ->where('variants.data.0.expiry_date', null)
                ->has('summary')
            )
        );
});

test('stock search matches product name, variant name, and sku', function () {
    stockVariant($this->tenant, 'Teh Manis', ['name' => 'Jumbo', 'sku' => 'TM-001']);

    $rowsFor = fn (string $query) => stockRowSkus(
        $this->actingAs($this->owner),
        'q='.urlencode($query)
    );

    expect($rowsFor('Teh Manis'))->toContain('TM-001')
        ->and($rowsFor('Jumbo'))->toContain('TM-001')
        ->and($rowsFor('TM-001'))->toBe(['TM-001'])
        ->and($rowsFor('tidak ada barang begini'))->toBe([]);
});

test('stock list filters by stock status', function () {
    stockVariant($this->tenant, 'Roti Tawar', ['name' => 'Habis', 'sku' => 'RT-0', 'stock' => 0]);
    stockVariant($this->tenant, 'Roti Tawar Kritis', ['name' => 'Kritis', 'sku' => 'RT-3', 'stock' => 3]);
    stockVariant($this->tenant, 'Roti Tawar Aman', ['name' => 'Aman', 'sku' => 'RT-9', 'stock' => 9]);

    $skusFor = fn (string $status) => stockRowSkus(
        $this->actingAs($this->owner),
        'q=Roti+Tawar&status='.$status
    );

    expect($skusFor('out'))->toBe(['RT-0'])
        ->and($skusFor('low'))->toBe(['RT-3'])
        ->and($skusFor('ok'))->toBe(['RT-9']);
});

test('stock list filters by expiry using the business day', function () {
    stockVariant($this->tenant, 'Susu Basi', [
        'sku' => 'SB-1',
        'stock' => 10,
        'expiry_date' => today()->subDay()->toDateString(),
    ]);
    stockVariant($this->tenant, 'Susu Dekat', [
        'sku' => 'SD-1',
        'stock' => 10,
        // Hari ini masih terhitung "mendekati", bukan "lewat" ([BL-082]).
        'expiry_date' => today()->toDateString(),
    ]);
    stockVariant($this->tenant, 'Susu Lama', [
        'sku' => 'SL-1',
        'stock' => 10,
        'expiry_date' => today()->addMonth()->toDateString(),
    ]);

    $skusFor = fn (string $status) => stockRowSkus(
        $this->actingAs($this->owner),
        'q=Susu&status='.$status
    );

    expect($skusFor('expired'))->toBe(['SB-1'])
        ->and($skusFor('near_expiry'))->toBe(['SD-1'])
        ->and($skusFor('ok'))->toBe(['SL-1']);
});

test('stock summary counts every status within the current search', function () {
    stockVariant($this->tenant, 'Gula Pasir', ['sku' => 'GP-0', 'stock' => 0]);
    stockVariant($this->tenant, 'Gula Batu', ['sku' => 'GB-2', 'stock' => 2]);
    stockVariant($this->tenant, 'Gula Merah', [
        'sku' => 'GM-8',
        'stock' => 8,
        'expiry_date' => today()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/stock?q=Gula')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('stock', fn (Assert $reload) => $reload
            ->where('summary.total', 3)
            ->where('summary.out', 1)
            ->where('summary.low', 1)
            ->where('summary.near_expiry', 1)
            ->where('summary.expired', 0)
            // Yang stoknya aman DAN tanggalnya masih jauh: tak satu pun di
            // antara ketiganya — varian `beforeEach` tidak terhitung karena
            // pencariannya juga mengunci ringkasannya.
            ->where('summary.ok', 0)
        ));
});

test('stock list filters by category', function () {
    $category = Category::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Snack',
    ]);
    stockVariant($this->tenant, 'Keripik', ['sku' => 'KR-1'], $category);
    stockVariant($this->tenant, 'Kerupuk', ['sku' => 'KP-1']);

    $skusFor = fn (string $filter) => stockRowSkus(
        $this->actingAs($this->owner),
        'q=Ker&category='.$filter
    );

    expect($skusFor((string) $category->id))->toBe(['KR-1'])
        ->and($skusFor('none'))->toBe(['KP-1']);
});

test('stock list only shows variants of the current tenant', function () {
    $other = Tenant::factory()->create();
    stockVariant($other, 'Barang Tetangga', ['sku' => 'XX-1']);
    stockVariant($this->tenant, 'Barang Sendiri', ['sku' => 'OK-1']);

    $this->actingAs($this->owner)
        ->get('/owner/stock?q=Barang')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('stock', fn (Assert $reload) => $reload
            ->has('variants.data', 1)
            ->where('variants.data.0.sku', 'OK-1')
            ->where('summary.total', 1)
        ));
});

test('stock list rejects unknown sort and page size', function () {
    $this->actingAs($this->owner)
        ->get('/owner/stock?sort=price;drop&dir=sideways&per_page=100000&status=meledak&category=abc')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.sort', 'urgency')
            ->where('filters.dir', 'asc')
            ->where('filters.per_page', 25)
            ->where('filters.status', '')
            ->where('filters.category', '')
        );
});

test('stock list paginates', function () {
    $product = Product::factory()->create([
        'tenant_id' => $this->tenant->id,
        'name' => 'Paket Borongan',
    ]);
    ProductVariant::factory()->count(30)->create([
        'product_id' => $product->id,
        'stock' => 20,
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/stock?q=Paket+Borongan')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('stock', fn (Assert $reload) => $reload
            ->has('variants.data', 25)
            ->where('variants.total', 30)
            ->where('variants.last_page', 2)
        ));

    // Jumlah baris per halaman dipilih dari daftar tetap di kaki tabelnya.
    $this->actingAs($this->owner)
        ->get('/owner/stock?q=Paket+Borongan&per_page=10')
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.per_page', 10)
            ->loadDeferredProps('stock', fn (Assert $reload) => $reload
                ->has('variants.data', 10)
                ->where('variants.last_page', 3)
            )
        );
});

test('stock list puts what needs attention first', function () {
    stockVariant($this->tenant, 'Zebra Aman', ['sku' => 'Z-OK', 'stock' => 40]);
    stockVariant($this->tenant, 'Zebra Kritis', ['sku' => 'Z-LOW', 'stock' => 2]);
    stockVariant($this->tenant, 'Zebra Habis', ['sku' => 'Z-OUT', 'stock' => 0]);

    // Tanpa `sort` apa pun: urutan bawaannya urgensi, bukan abjad — kalau
    // abjad yang menang, "Zebra Aman" akan ada di baris pertama.
    $this->actingAs($this->owner)
        ->get('/owner/stock?q=Zebra')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('stock', fn (Assert $reload) => $reload
            ->where('variants.data.0.sku', 'Z-OUT')
            ->where('variants.data.1.sku', 'Z-LOW')
            ->where('variants.data.2.sku', 'Z-OK')
        ));
});

/**
 * Ember `pressed` — tujuan tautan "Lihat di Stok" pada kartu Penyelamat Stok.
 *
 * Yang diuji bukan penyaringnya sendiri melainkan JANJINYA: baris yang muncul
 * di sini harus himpunan yang sama dengan yang barusan dibaca pemilik di
 * beranda. Sebelum ember ini ada, tautan itu mendarat di `near_expiry` — yang
 * menjatuhkan seluruh barang dead stock dan memungut varian yang tidak pernah
 * disebut kartunya.
 */
test('the pressed bucket matches what the dashboard card lists', function () {
    stockVariant($this->tenant, 'Susu Dekat', [
        'sku' => 'PS-NEAR',
        'stock' => 4,
        'expiry_date' => today()->addDays(2)->toDateString(),
    ]);
    // Tak punya kedaluwarsa dan belum pernah terjual — masuk lewat jalur dead
    // stock, dan justru inilah yang dijatuhkan `near_expiry`.
    stockVariant($this->tenant, 'Susu Diam', ['sku' => 'PS-DEAD', 'stock' => 4]);
    // Sudah lewat tanggalnya: kasir tidak boleh menawarkannya, jadi ia bukan
    // barang yang "harus keluar hari ini" — tempatnya ember `expired`.
    stockVariant($this->tenant, 'Susu Basi', [
        'sku' => 'PS-GONE',
        'stock' => 4,
        'expiry_date' => today()->subDay()->toDateString(),
    ]);
    // Stoknya nol: tidak ada yang bisa diselamatkan.
    stockVariant($this->tenant, 'Susu Habis', [
        'sku' => 'PS-EMPTY',
        'stock' => 0,
        'expiry_date' => today()->addDay()->toDateString(),
    ]);

    $skus = stockRowSkus($this->actingAs($this->owner), 'q=Susu&status=pressed');
    sort($skus);

    expect($skus)->toBe(['PS-DEAD', 'PS-NEAR']);
});

test('the pressed bucket has its own summary count', function () {
    stockVariant($this->tenant, 'Keju Dekat', [
        'sku' => 'KJ-NEAR',
        'stock' => 4,
        'expiry_date' => today()->addDays(2)->toDateString(),
    ]);
    stockVariant($this->tenant, 'Keju Basi', [
        'sku' => 'KJ-GONE',
        'stock' => 4,
        'expiry_date' => today()->subDay()->toDateString(),
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/stock?q=Keju')
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps('stock', fn (Assert $reload) => $reload
            ->where('summary.pressed', 1)
            ->where('summary.expired', 1)
        ));
});
