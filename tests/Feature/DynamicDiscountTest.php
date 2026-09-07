<?php

use App\Models\CashDrawer;
use App\Models\DiscountRule;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use App\Models\User;
use App\Services\DiscountService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;

/**
 * Diskon dinamis dengan penjaga margin (`[BL-018]`).
 *
 * Yang dijaga di sini adalah hal-hal yang, bila salah, mengeluarkan UANG tanpa
 * ada yang menyadarinya: rumus yang menembus lantai untung, pembulatan yang
 * salah arah, harga diskon yang naik sendiri saat transaksinya diedit, dan
 * penjualan rugi yang tenggelam di dalam angka "diskon biasa" di laporan.
 */

/**
 * @return array{tenant: Tenant, owner: User, cashier: User, variant: ProductVariant, cash: PaymentMethod}
 */
function makeDiscountContext(float $price = 20000, float $cost = 10000, float $margin = 10): array
{
    $tenant = Tenant::factory()->create(['min_margin_percent' => $margin]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => $price,
        'cost_price' => $cost,
        'stock' => 100,
        'expiry_date' => null,
    ]);

    foreach ([$owner, $cashier] as $user) {
        CashDrawer::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $user->id,
            'closed_at' => null,
        ]);
    }

    return [
        'tenant' => $tenant,
        'owner' => $owner,
        'cashier' => $cashier,
        'variant' => $variant,
        'cash' => PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'cash']),
    ];
}

/**
 * @param  array<string, mixed>  $extra
 */
function sellOne(ProductVariant $variant, PaymentMethod $cash, float $amount, array $extra = []): Illuminate\Testing\TestResponse
{
    return post('/cashier/transactions', [
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'qty' => 1,
            'unit_price' => $variant->price,
            'modifiers' => [],
            ...$extra,
        ]],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => $amount]],
    ]);
}

// --- Lantai margin ---

test('lantai dihitung dari harga modal dan margin milik owner, dibulatkan ke atas', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(cost: 10000, margin: 15);

    // 10.000 × 1,15 = 11.500 — sudah kelipatan 500, jadi tetap.
    expect(app(DiscountService::class)->floorFor($variant, $tenant))->toBe(11500.0);

    $tenant->update(['min_margin_percent' => 17]);

    // 10.000 × 1,17 = 11.700 → dibulatkan KE ATAS jadi 12.000. Membulatkan ke
    // bawah akan menembus lantai yang baru saja dihitung, dan itu membuat
    // seluruh penjaganya sia-sia.
    expect(app(DiscountService::class)->floorFor($variant->fresh(), $tenant->fresh()))->toBe(12000.0);
});

test('barang tanpa harga modal tidak punya lantai dan tidak pernah didiskon rumus', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext();

    // Kolomnya NOT NULL, jadi "tidak diketahui" di basis data ini berbentuk 0.
    $variant->update(['cost_price' => 0]);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 50,
    ]);

    $pricing = app(DiscountService::class)->priceFor($variant->fresh(), $tenant);

    // "Tetap untung" jadi klaim tanpa dasar bila modalnya tidak diketahui.
    expect($pricing['price'])->toBe(20000.0)
        ->and($pricing['rule'])->toBeNull();
});

test('rumus berhenti di lantai, tidak pernah menembusnya', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(price: 20000, cost: 15000, margin: 10);

    // Lantai = 15.000 × 1,1 = 16.500.
    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 60, // 20.000 → 8.000, jauh di bawah lantai
    ]);

    $pricing = app(DiscountService::class)->priceFor($variant, $tenant);

    expect($pricing['price'])->toBe(16500.0);
});

// --- Pendalaman seiring waktu ---

test('potongan near_expiry mendalam seiring tanggal kedaluwarsa mendekat', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(price: 20000, cost: 2000);

    $rule = DiscountRule::factory()->nearExpiry(percent: 10, maxPercent: 50)->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
    ]);

    $service = app(DiscountService::class);

    // Di luar jendela (7 hari) → potongan awal.
    $variant->update(['expiry_date' => now()->addDays(10)->toDateString()]);
    expect($service->effectivePercent($rule, $variant->fresh()))->toBe(10.0);

    // Hari kedaluwarsa → potongan terdalam.
    $variant->update(['expiry_date' => now()->toDateString()]);
    expect($service->effectivePercent($rule, $variant->fresh()))->toBe(50.0);

    // Di tengah jendela → di antara keduanya.
    $variant->update(['expiry_date' => now()->addDays(3)->toDateString()]);
    $mid = $service->effectivePercent($rule, $variant->fresh());
    expect($mid)->toBeGreaterThan(10.0)->toBeLessThan(50.0);
});

test('barang yang SUDAH kedaluwarsa tidak pernah didiskon', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext();

    $variant->update(['expiry_date' => now()->subDay()->toDateString()]);

    DiscountRule::factory()->nearExpiry()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
    ]);

    // Batas keamanan pangan, bukan pilihan bisnis — dijaga di service, bukan
    // diserahkan pada kedisiplinan kasir.
    expect(app(DiscountService::class)->priceFor($variant->fresh(), $tenant)['rule'])->toBeNull();
});

test('pemicu selain near_expiry potongannya rata, tidak mendalam', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(cost: 2000);

    $rule = DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'trigger' => DiscountRule::TRIGGER_DEAD_STOCK,
        'percent' => 25,
        'max_percent' => null,
    ]);

    $variant->update(['expiry_date' => now()->addDay()->toDateString()]);

    expect(app(DiscountService::class)->effectivePercent($rule, $variant->fresh()))->toBe(25.0);
});

// --- Jendela berlaku dan saklar ---

test('aturan yang dimatikan, dijadwalkan, atau kedaluwarsa tidak berlaku', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(cost: 2000);

    $service = app(DiscountService::class);

    $mati = DiscountRule::factory()->inactive()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id,
    ]);
    expect($service->priceFor($variant, $tenant)['rule'])->toBeNull();
    $mati->delete();

    DiscountRule::factory()->scheduled()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id,
    ])->delete();

    $lewat = DiscountRule::factory()->expired()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id,
    ]);
    expect($service->priceFor($variant, $tenant)['rule'])->toBeNull();
    $lewat->delete();

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'starts_on' => now()->subDay()->toDateString(),
        'ends_on' => now()->addDay()->toDateString(),
    ]);
    expect($service->priceFor($variant->fresh(), $tenant)['rule'])->not->toBeNull();
});

// --- Checkout ---

test('checkout memakai harga diskon dan mencatat seluruh jejaknya', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 5000);

    $rule = DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 25,
        'reason' => 'Stok menumpuk',
    ]);

    actingAs($cashier);
    sellOne($variant, $cash, 15000)->assertSessionHas('success');

    $item = TransactionItem::first();

    expect((float) $item->unit_price)->toBe(15000.0)
        // Harga katalog tetap utuh dan ikut tercatat — tanpa ini, "berapa yang
        // kita korbankan" harus dihitung dari harga varian HARI INI.
        ->and((float) $item->original_unit_price)->toBe(20000.0)
        ->and((float) $item->discount_amount)->toBe(5000.0)
        ->and($item->discount_rule_id)->toBe($rule->id)
        // Alasannya DISALIN, bukan cuma dirujuk: aturan bisa disunting atau
        // dihapus, dan laporan bulan lalu harus tetap menjelaskan dirinya.
        ->and($item->discount_reason)->toBe('Stok menumpuk')
        // Harga modal dan lantai dibekukan bersama barisnya.
        ->and((float) $item->cost_price_at_sale)->toBe(5000.0)
        ->and((float) $item->margin_floor_at_sale)->toBe(5500.0)
        ->and($item->below_floor_approved_by)->toBeNull();

    expect((float) Transaction::first()->total_amount)->toBe(15000.0);
});

test('tanpa aturan, harga katalog dipakai dan tidak ada potongan tercatat', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext();

    actingAs($cashier);
    sellOne($variant, $cash, 20000)->assertSessionHas('success');

    $item = TransactionItem::first();

    expect((float) $item->unit_price)->toBe(20000.0)
        ->and((float) $item->discount_amount)->toBe(0.0)
        ->and($item->discount_rule_id)->toBeNull();
});

test('harga kiriman klien tetap diabaikan — server yang menentukan', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(cost: 2000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 25,
    ]);

    actingAs($cashier);

    // Klien mengaku harganya 1.000. Server tetap menghitung sendiri.
    post('/cashier/transactions', [
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'qty' => 1,
            'unit_price' => 1000,
            'modifiers' => [],
        ]],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => 15000]],
    ])->assertSessionHas('success');

    expect((float) TransactionItem::first()->unit_price)->toBe(15000.0);
});

// --- Penembusan lantai ---

test('kasir tidak bisa menembus lantai sama sekali', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext();

    actingAs($cashier);

    // Bukan "bisa tapi dicatat", melainkan tidak tersedia — dan ditegakkan di
    // SERVER, bukan dengan menyembunyikan tombolnya.
    sellOne($variant, $cash, 5000, [
        'override_unit_price' => 5000,
        'discount_reason' => 'Barang penyok',
    ])->assertSessionHas('error');

    expect(Transaction::where('status', Transaction::STATUS_COMPLETED)->count())->toBe(0);
});

test('owner bisa menembus lantai, dan alasannya wajib', function () {
    ['owner' => $owner, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 10000);

    actingAs($owner);

    // Tanpa alasan → ditolak. Menjual rugi tanpa alasan tertulis adalah angka
    // yang tidak bisa dijelaskan siapa pun saat laporannya dibuka.
    sellOne($variant, $cash, 5000, ['override_unit_price' => 5000])->assertSessionHas('error');

    expect(Transaction::where('status', Transaction::STATUS_COMPLETED)->count())->toBe(0);

    sellOne($variant, $cash, 5000, [
        'override_unit_price' => 5000,
        'discount_reason' => 'Kemasan rusak, daripada dibuang',
    ])->assertSessionHas('success');

    $item = TransactionItem::first();

    expect((float) $item->unit_price)->toBe(5000.0)
        ->and($item->discount_reason)->toBe('Kemasan rusak, daripada dibuang')
        // Siapa yang menyetujui DAN lantai yang berlaku saat itu — tanpa
        // keduanya, "seberapa dalam tembusnya" tak bisa dihitung ulang nanti.
        ->and($item->below_floor_approved_by)->toBe($owner->id)
        ->and((float) $item->margin_floor_at_sale)->toBe(11000.0);
});

test('harga khusus DI ATAS lantai tidak dicatat sebagai penembusan', function () {
    ['owner' => $owner, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 10000);

    actingAs($owner);

    // Lantai 11.000; 15.000 masih di atasnya. Ia diskon biasa, dan NULL-nya
    // kolom persetujuan inilah yang memisahkannya di laporan.
    sellOne($variant, $cash, 15000, [
        'override_unit_price' => 15000,
        'discount_reason' => 'Langganan lama',
    ])->assertSessionHas('success');

    expect(TransactionItem::first()->below_floor_approved_by)->toBeNull();
});

test('harga khusus di atas harga katalog ditolak', function () {
    ['owner' => $owner, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext();

    actingAs($owner);

    sellOne($variant, $cash, 30000, [
        'override_unit_price' => 30000,
        'discount_reason' => 'Salah ketik',
    ])->assertSessionHas('error');
});

// --- Tiga jalur yang mudah terlewat ---

test('mengedit transaksi tidak menaikkan harga diskon kembali ke katalog', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 5000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 25,
        'reason' => 'Stok menumpuk',
    ]);

    actingAs($cashier);
    sellOne($variant, $cash, 15000);

    $transaction = Transaction::first();

    actingAs($owner);

    // Diedit karena alasan LAIN — qty bertambah. Tanpa penyelamatan harga,
    // barisnya akan diam-diam kembali ke 20.000: riwayat berubah sendiri,
    // tanpa galat, tanpa jejak.
    put("/cashier/transactions/{$transaction->id}", [
        'items' => [['variant_id' => $variant->id, 'qty' => 2, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => 30000]],
        'reason' => 'Pelanggan menambah satu porsi.',
    ])->assertSessionHasNoErrors();

    $item = $transaction->fresh()->items->first();

    expect((float) $item->unit_price)->toBe(15000.0)
        ->and((float) $item->subtotal)->toBe(30000.0)
        ->and($item->discount_reason)->toBe('Stok menumpuk');
});

test('penembusan lantai bertahan menyeberangi pengeditan, termasuk siapa yang menyetujui', function () {
    ['owner' => $owner, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 10000);

    actingAs($owner);
    sellOne($variant, $cash, 5000, [
        'override_unit_price' => 5000,
        'discount_reason' => 'Kemasan rusak',
    ]);

    $transaction = Transaction::first();

    put("/cashier/transactions/{$transaction->id}", [
        'items' => [['variant_id' => $variant->id, 'qty' => 2, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $cash->id, 'amount' => 10000]],
        'reason' => 'Koreksi jumlah.',
    ])->assertSessionHasNoErrors();

    $item = $transaction->fresh()->items->first();

    // Menyimpan harganya tapi membuang persetujuannya akan membuat baris ini
    // terlihat seperti diskon biasa di laporan.
    expect((float) $item->unit_price)->toBe(5000.0)
        ->and($item->below_floor_approved_by)->toBe($owner->id);
});

test('penjualan offline berharga diskon bukan anomali', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 5000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 25,
    ]);

    actingAs($cashier);

    // Tanpa perbaikan ini, tiap penjualan berdiskon offline membanjiri
    // needs_review — dan sinyal yang dibangun untuk menangkap anomali sungguhan
    // jadi berisik lalu berhenti dipercaya.
    postJson('/cashier/transactions/sync', [
        'transactions' => [[
            'client_uuid' => (string) Illuminate\Support\Str::uuid(),
            'occurred_at' => now()->subMinutes(5)->toIso8601String(),
            'items' => [[
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => 15000,
            ]],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 15000]],
        ]],
    ])->assertOk()->assertJsonPath('results.0.needs_review', false);
});

test('harga offline yang tidak cocok dengan harga sah mana pun tetap ditandai', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 5000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 25,
    ]);

    actingAs($cashier);

    // 9.000 bukan harga katalog dan bukan harga diskon — snapshot katalog yang
    // basi persis terlihat begini, dan memang harus sampai ke meja owner.
    postJson('/cashier/transactions/sync', [
        'transactions' => [[
            'client_uuid' => (string) Illuminate\Support\Str::uuid(),
            'occurred_at' => now()->subMinutes(5)->toIso8601String(),
            'items' => [[
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => 9000,
            ]],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 9000]],
        ]],
    ])->assertOk()->assertJsonPath('results.0.needs_review', true);
});

test('katalog POS membawa harga efektif, bukan hanya harga katalog', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant] = makeDiscountContext(price: 20000, cost: 5000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 25,
        'reason' => 'Stok menumpuk',
    ]);

    actingAs($cashier);

    // Kalau layar kasir menampilkan harga katalog sementara server memotongnya,
    // pelanggan dimintai satu angka lalu ditagih angka lain.
    //
    // `products` prop tertunda ([BL-037]), jadi nilainya baru ada setelah
    // permintaan lanjutan.
    get('/cashier/pos')->assertInertia(fn (Assert $page) => $page
        ->component('Cashier/POS')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('products.0.variants.0.effective_price', 15000)
            // Harga katalog TIDAK ditimpa: layar perlu menunjukkan keduanya,
            // karena potongan yang tidak terlihat tidak pernah jadi alasan
            // orang membeli.
            ->where('products.0.variants.0.price', '20000.00')
            ->where('products.0.variants.0.discount_reason', 'Stok menumpuk')
            ->etc()
        )
    );
});

test('katalog POS tidak mencari aturan diskon sekali per varian', function () {
    ['tenant' => $tenant, 'cashier' => $cashier, 'variant' => $variant] = makeDiscountContext(price: 20000, cost: 5000);

    // Satu varian berdiskon di antara tiga puluh — bentuk katalog yang
    // sebenarnya. Mayoritas barang TIDAK sedang didiskon, dan justru merekalah
    // yang mahal: aturan yang tidak ketemu di peta bisa terbaca sebagai "belum
    // dicari", lalu dicari satu per satu di jalur terpanas aplikasi.
    ProductVariant::factory()->count(29)->create([
        'product_id' => $variant->product_id,
        'price' => 20000,
        'cost_price' => 5000,
        'stock' => 100,
        'expiry_date' => null,
    ]);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'percent' => 25,
        'reason' => 'Stok menumpuk',
    ]);

    actingAs($cashier);

    $discountQueries = 0;

    DB::listen(function ($query) use (&$discountQueries) {
        if (str_contains($query->sql, 'discount_rules')) {
            $discountQueries++;
        }
    });

    get('/cashier/pos')->assertInertia(fn (Assert $page) => $page
        ->component('Cashier/POS')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            // Harganya tetap benar; yang diuji di sini adalah ONGKOSNYA.
            ->where('products.0.variants.0.effective_price', 15000)
            ->etc()
        )
    );

    // Katalog dan tiap strategi upsell boleh memuat aturannya masing-masing
    // sekaligus. Yang tidak boleh adalah angka ini ikut tumbuh bersama jumlah
    // varian — dengan tiga puluh varian, N+1-nya sendiri sudah lewat ambang ini.
    expect($discountQueries)->toBeLessThan(10);
});

test('harga dari peta aturan yang sudah dimuat tidak menyentuh basis data', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(price: 20000, cost: 5000);

    $service = app(DiscountService::class);

    $queries = 0;

    DB::listen(function () use (&$queries) {
        $queries++;
    });

    // Peta kosong berarti varian ini memang tidak punya aturan hari ini. Itu
    // jawaban akhir — bukan tanda bahwa pencariannya belum dilakukan.
    $pricing = $service->priceFromRules($variant, $tenant, collect());

    expect($queries)->toBe(0)
        ->and($pricing['price'])->toBe(20000.0)
        ->and($pricing['discount'])->toBe(0.0)
        ->and($pricing['rule'])->toBeNull()
        ->and($pricing['reason'])->toBe(DiscountService::REASON_NO_RULE);
});

// --- Laporan ---

test('laporan memisahkan penjualan di bawah lantai dari diskon biasa', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'variant' => $variant, 'cash' => $cash] = makeDiscountContext(price: 20000, cost: 10000);

    $lain = ProductVariant::factory()->create([
        'product_id' => $variant->product_id,
        'price' => 20000,
        'cost_price' => 5000,
        'stock' => 100,
    ]);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $lain->id,
        'percent' => 25,
        'reason' => 'Promo',
    ]);

    actingAs($owner);

    // Satu diskon biasa (di atas lantai) dan satu penjualan rugi.
    sellOne($lain, $cash, 15000);
    sellOne($variant, $cash, 5000, [
        'override_unit_price' => 5000,
        'discount_reason' => 'Kemasan rusak',
    ]);

    // Prop-nya ditunda ([BL-037]), jadi nilainya baru ada setelah permintaan
    // lanjutan.
    get('/owner/reports/daily')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Reports/Daily')
        ->loadDeferredProps('rekap', fn (Assert $reload) => $reload
            ->where('discountSummary.total_given', 20000)
            ->where('discountSummary.items_discounted', 2)
            // Angka yang paling ingin dilihat owner, dan tanpa pemisahan ini ia
            // tenggelam: penjualan rugi terlihat persis seperti diskon 5% sehat.
            ->where('discountSummary.below_floor_total', 15000)
            ->where('discountSummary.below_floor_items', 1)
            ->where('discountSummary.below_floor_lines.0.reason', 'Kemasan rusak')
            ->where('discountSummary.below_floor_lines.0.approved_by', $owner->name)
            ->etc()
        )
    );
});

// --- Halaman owner ---

test('halaman aturan diskon hanya untuk owner', function () {
    ['owner' => $owner, 'cashier' => $cashier] = makeDiscountContext();

    actingAs($cashier);
    get('/owner/discount-rules')->assertForbidden();

    actingAs($owner);
    get('/owner/discount-rules')->assertOk();
});

test('owner bisa menambah, menyunting, menghentikan, dan menghapus aturan', function () {
    ['owner' => $owner, 'variant' => $variant] = makeDiscountContext();

    actingAs($owner);

    post('/owner/discount-rules', [
        'product_variant_id' => $variant->id,
        'trigger' => DiscountRule::TRIGGER_MANUAL,
        'percent' => 15,
        'reason' => 'Promo pembukaan',
    ])->assertSessionHas('success');

    $rule = DiscountRule::sole();

    put("/owner/discount-rules/{$rule->id}", [
        'product_variant_id' => $variant->id,
        'trigger' => DiscountRule::TRIGGER_MANUAL,
        'percent' => 20,
        'reason' => 'Promo pembukaan diperpanjang',
    ])->assertSessionHas('success');

    expect((float) $rule->fresh()->percent)->toBe(20.0);

    post("/owner/discount-rules/{$rule->id}/toggle")->assertSessionHas('success');
    expect($rule->fresh()->is_active)->toBeFalse();

    delete("/owner/discount-rules/{$rule->id}")->assertSessionHas('success');
    expect(DiscountRule::count())->toBe(0);
});

test('aturan wajib beralasan', function () {
    ['owner' => $owner, 'variant' => $variant] = makeDiscountContext();

    actingAs($owner);

    post('/owner/discount-rules', [
        'product_variant_id' => $variant->id,
        'trigger' => DiscountRule::TRIGGER_MANUAL,
        'percent' => 15,
    ])->assertSessionHasErrors('reason');
});

test('potongan terdalam hanya sah untuk pemicu mendekati kedaluwarsa', function () {
    ['owner' => $owner, 'variant' => $variant] = makeDiscountContext();

    actingAs($owner);

    // Menerimanya diam-diam pada pemicu lain akan membuat owner mengira
    // potongannya membesar padahal tidak.
    post('/owner/discount-rules', [
        'product_variant_id' => $variant->id,
        'trigger' => DiscountRule::TRIGGER_MANUAL,
        'percent' => 10,
        'max_percent' => 40,
        'reason' => 'Promo',
    ])->assertSessionHasErrors('max_percent');
});

test('aturan tidak boleh menunjuk varian milik tenant lain', function () {
    ['owner' => $owner] = makeDiscountContext();
    ['variant' => $variantOrangLain] = makeDiscountContext();

    actingAs($owner);

    post('/owner/discount-rules', [
        'product_variant_id' => $variantOrangLain->id,
        'trigger' => DiscountRule::TRIGGER_MANUAL,
        'percent' => 15,
        'reason' => 'Promo',
    ])->assertSessionHasErrors('product_variant_id');
});

test('aturan tenant lain tidak berlaku di sini', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(cost: 2000);
    ['tenant' => $tenantLain] = makeDiscountContext();

    DiscountRule::factory()->create([
        'tenant_id' => $tenantLain->id,
        'product_variant_id' => $variant->id,
        'percent' => 50,
    ]);

    expect(app(DiscountService::class)->priceFor($variant, $tenant)['rule'])->toBeNull();
});

// --- Sebab sebuah aturan diam ---
//
// Layar Aturan Diskon dulu hanya menulis "Tidak berlaku (cek stok/kedaluwarsa)"
// untuk kelima keadaan di bawah, sehingga owner menebak sendiri mana yang
// sedang terjadi — padahal penanganannya berbeda-beda. `priceFor()` sudah tahu
// sebabnya; tes ini yang menjaga sebab itu tetap terkirim.

test('barang yang sudah kedaluwarsa menyebut sebabnya', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(cost: 2000);

    $variant->update(['expiry_date' => now()->subDay()]);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'percent' => 20,
    ]);

    $pricing = app(DiscountService::class)->priceFor($variant->fresh(), $tenant);

    expect($pricing['rule'])->toBeNull()
        ->and($pricing['reason'])->toBe(DiscountService::REASON_EXPIRED);
});

test('modal yang masih nol menyebut sebabnya, bukan menyalahkan stok', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext();

    // Kolomnya NOT NULL, jadi "modal tak diketahui" di lapangan berbentuk nol —
    // varian yang dibuat buru-buru dan harga modalnya belum pernah diisi.
    $variant->update(['cost_price' => 0]);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'percent' => 20,
    ]);

    $pricing = app(DiscountService::class)->priceFor($variant->fresh(), $tenant);

    expect($pricing['rule'])->toBeNull()
        ->and($pricing['reason'])->toBe(DiscountService::REASON_UNKNOWN_COST);
});

test('lantai yang sudah setinggi katalog dibedakan dari potongan yang terlalu kecil', function () {
    // Lantai menelan seluruh potongan: modal 20.000 + margin 10% = 22.000,
    // sudah di atas harga katalognya sendiri.
    ['tenant' => $tenant, 'variant' => $mahal] = makeDiscountContext(price: 20000, cost: 20000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $mahal->id, 'percent' => 50,
    ]);

    expect(app(DiscountService::class)->priceFor($mahal, $tenant)['reason'])
        ->toBe(DiscountService::REASON_FLOOR_ABSORBED);

    // Lantainya rendah, yang kurang justru potongannya: 1% dari 20.000 hilang
    // ditelan pembulatan ke atas kelipatan 500.
    ['tenant' => $tenantKedua, 'variant' => $murah] = makeDiscountContext(price: 20000, cost: 1000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenantKedua->id, 'product_variant_id' => $murah->id, 'percent' => 1,
    ]);

    expect(app(DiscountService::class)->priceFor($murah, $tenantKedua)['reason'])
        ->toBe(DiscountService::REASON_CUT_TOO_SMALL);
});

test('varian tanpa aturan apa pun tetap menyebut sebabnya', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(cost: 2000);

    expect(app(DiscountService::class)->priceFor($variant, $tenant)['reason'])
        ->toBe(DiscountService::REASON_NO_RULE);
});

test('potongan yang berlaku tapi tertahan lantai ditandai clamped', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(price: 20000, cost: 10000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'percent' => 60,
    ]);

    $pricing = app(DiscountService::class)->priceFor($variant, $tenant);

    // 60% dari 20.000 = 8.000, tapi lantainya 11.000.
    expect($pricing['price'])->toBe(11000.0)
        ->and($pricing['clamped'])->toBeTrue()
        ->and($pricing['reason'])->toBeNull();
});

test('potongan yang tidak menyentuh lantai tidak ditandai clamped', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(price: 20000, cost: 2000);

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'percent' => 10,
    ]);

    $pricing = app(DiscountService::class)->priceFor($variant, $tenant);

    expect($pricing['clamped'])->toBeFalse()
        ->and($pricing['reason'])->toBeNull();
});

test('halaman aturan diskon mengirim sebab dan penanda lantai ke layar', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'variant' => $variant] = makeDiscountContext(
        price: 20000, cost: 10000,
    );

    DiscountRule::factory()->create([
        'tenant_id' => $tenant->id, 'product_variant_id' => $variant->id, 'percent' => 60,
    ]);

    actingAs($owner);

    get('/owner/discount-rules')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/DiscountRules/Index')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->where('rules.0.effective.clamped', true)
            ->where('rules.0.effective.reason', null)
            ->etc()
        )
    );
});

test('jendela tanggal aturan diskon sampai ke layar sebagai Y-m-d', function () {
    ['tenant' => $tenant, 'variant' => $variant] = makeDiscountContext(cost: 2000);

    $rule = DiscountRule::factory()->create([
        'tenant_id' => $tenant->id,
        'product_variant_id' => $variant->id,
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-20',
    ]);

    expect($rule->fresh()->toArray())->toMatchArray([
        'starts_on' => '2026-09-10',
        'ends_on' => '2026-09-20',
    ]);
});
