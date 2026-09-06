<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * [BL-037] — bagian terberat dari empat halaman tersibuk dipindahkan ke
 * `Inertia::defer()` supaya kerangka pemuatannya punya kesempatan tampil.
 *
 * Yang diuji di sini adalah pembagiannya: mana yang WAJIB sudah ada pada cat
 * pertama, dan mana yang menyusul. Pembagian itu tidak terlihat dari mana pun
 * kecuali berkas ini — begitu sebuah prop dikembalikan menjadi eager, kerangka
 * yang menggantikannya tidak akan pernah terlihat lagi dan tidak ada satu pun
 * pengujian lain yang gagal.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

/**
 * Kasir dengan sesi kas terbuka, satu produk, dan satu metode bayar — bekal
 * minimum supaya `/cashier/pos` merender halamannya alih-alih mengalihkan ke
 * pembukaan kas.
 */
function posCashier(Tenant $tenant): User
{
    $cashier = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'cashier',
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 25000,
        'stock' => 10,
    ]);

    PaymentMethod::factory()->create([
        'tenant_id' => $tenant->id,
        'type' => 'cash',
    ]);

    return $cashier;
}

test('layar kasir mengirim kategori dan metode bayar dulu, katalognya menyusul', function () {
    actingAs(posCashier($this->tenant));

    get('/cashier/pos')->assertInertia(fn (Assert $page) => $page
        ->component('Cashier/POS')
        // Kategori dan metode bayar tetap eager: chip kategori terlihat sejak
        // cat pertama, dan metode bayar harus sudah ada sebelum kasir menekan
        // Bayar — keduanya tidak boleh ikut ditunda.
        ->has('categories')
        ->has('paymentMethods', 1)
        ->missing('products')
        ->missing('upsell')
        // Satu grup, satu permintaan lanjutan: snapshot katalog offline
        // menyimpan produk beserta indeks upsell-nya, jadi keduanya harus
        // sampai bersamaan.
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('products', 1)
            ->has('upsell')
        )
    );
});

test('dashboard mengirim metrik hari ini dulu, tren dan badge menyusul', function () {
    actingAs($this->owner);

    get('/owner/dashboard')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Dashboard')
        // Metrik hari ini adalah isi utama layar ini, dan ringkasan langganan
        // ikut eager karena inilah satu-satunya pintu masuk ke halaman tagihan.
        ->has('metrics')
        ->has('subscription')
        ->missing('dailyTrend')
        ->missing('recentTransactions')
        ->missing('badges')
        // Badge dipisah ke grupnya sendiri supaya agregat terberat tidak
        // menahan tren dan daftar transaksi.
        ->loadDeferredProps('default', fn (Assert $reload) => $reload
            ->has('dailyTrend')
            ->has('recentTransactions')
            ->missing('badges')
        )
        ->loadDeferredProps('badges', fn (Assert $reload) => $reload
            ->has('badges')
        )
    );
});

test('laporan harian mengirim ringkasan dulu, daftar dan rekapnya menyusul', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 30000,
        'occurred_at' => '2026-05-03 10:00:00',
    ]);

    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 20000,
        'occurred_at' => '2026-05-03 11:00:00',
    ]);

    Transaction::factory()->voided()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 90000,
        'occurred_at' => '2026-05-03 12:00:00',
    ]);

    actingAs($this->owner);

    get('/owner/reports/daily?date=2026-05-03')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Reports/Daily')
        // Ringkasannya kini dihitung dengan agregat, bukan dengan menjumlahkan
        // koleksi yang sudah dimuat — angkanya harus tetap sama persis.
        ->where('summary.total_revenue', 50000)
        ->where('summary.total_transactions', 2)
        ->where('summary.voided_count', 1)
        ->missing('transactions')
        ->missing('paymentSummary')
        ->missing('topProducts')
        ->loadDeferredProps(['default', 'rekap'], fn (Assert $reload) => $reload
            ->has('transactions', 2)
            ->has('paymentSummary')
            ->has('topProducts')
        )
    );
});

test('riwayat transaksi mengirim filter dulu, tabelnya menyusul', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 15000,
    ]);

    actingAs($this->owner);

    get('/owner/transactions')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Transactions/Index')
        // Filter tetap eager: tanpa itu kerangka tabel tampil di atas panel
        // filter yang juga masih kosong, dan tidak ada yang bisa dilakukan
        // sambil menunggu.
        ->has('filters')
        ->missing('transactions')
        ->loadDeferredProps(fn (Assert $reload) => $reload
            ->has('transactions.data', 1)
        )
    );
});

/**
 * Gelombang kedua ([BL-037], 2026-08-15): sisa tabel di halaman pelacakan.
 *
 * Yang diperiksa tetap sama — pembagian eager/tertunda — tapi untuk halaman
 * yang isinya memang cuma satu daftar, pembagian itu punya bentuk yang
 * berulang: propnya hilang di cat pertama, dan ada setelah permintaan
 * lanjutan. Satu test berdataset lebih jujur daripada dua belas test kembar,
 * karena barisnya yang bertambah saat halaman berikutnya menyusul.
 */
test('halaman berisi satu daftar menunda daftarnya', function (string $url, string $component, string $prop) {
    actingAs($this->owner);

    get($url)->assertInertia(fn (Assert $page) => $page
        ->component($component)
        ->missing($prop)
        ->loadDeferredProps(fn (Assert $reload) => $reload->has($prop))
    );
})->with([
    'kategori' => ['/owner/categories', 'Owner/Categories/Index', 'categories'],
    'modifier' => ['/owner/modifiers', 'Owner/Modifiers/Index', 'modifierGroups'],
    'metode bayar' => ['/owner/payment-methods', 'Owner/PaymentMethods/Index', 'paymentMethods'],
    'role' => ['/owner/roles', 'Owner/Roles/Index', 'roles'],
    'stok' => ['/owner/stock', 'Owner/Stock/Index', 'variants'],
    'mutasi stok' => ['/owner/stock/movements', 'Owner/Stock/Movements', 'movements'],
    'sesi kas' => ['/owner/cash-drawers', 'Owner/CashDrawers/Index', 'cashDrawers'],
    'tinjauan offline' => ['/owner/offline-review', 'Owner/OfflineReview/Index', 'transactions'],
]);

test('halaman produk mengirim penyaringnya dulu, katalognya menyusul', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    ProductVariant::factory()->create(['product_id' => $product->id]);

    actingAs($this->owner);

    get('/owner/products')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Products/Index')
        // Kategori tetap eager: penyaring yang ikut menunggu membuat layar ini
        // kosong seluruhnya, bukan sebagian.
        ->has('categories')
        ->missing('products')
        ->loadDeferredProps(fn (Assert $reload) => $reload->has('products', 1))
    );
});

test('rekap bulanan mengirim ringkasan dan tren dulu, dua rekapnya menyusul', function () {
    Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'total_amount' => 40000,
        'occurred_at' => '2026-05-12 09:00:00',
    ]);

    actingAs($this->owner);

    get('/owner/reports/monthly?month=2026-05')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Reports/Monthly')
        ->where('summary.total_revenue', 40000)
        // Deret harian TIDAK ikut ditunda: ringkasan di atas diturunkan
        // darinya, jadi kuerinya sudah jalan untuk cat pertama. Menundanya
        // hanya membuat grafiknya berkedip untuk data yang sudah ada.
        ->has('dailySeries')
        ->missing('paymentSummary')
        ->missing('topProducts')
        ->loadDeferredProps('rekap', fn (Assert $reload) => $reload
            ->has('paymentSummary')
            ->has('topProducts')
        )
    );
});

test('laporan upsell mengirim ringkasan dulu, tiga rinciannya menyusul', function () {
    actingAs($this->owner);

    get('/owner/reports/upsell')->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Reports/Upsell')
        ->has('summary')
        ->missing('byType')
        ->missing('bySurface')
        ->missing('topSuggestions')
        ->loadDeferredProps(['rekap', 'default'], fn (Assert $reload) => $reload
            ->has('byType')
            ->has('bySurface')
            ->has('topSuggestions')
        )
    );
});

test('riwayat stok satu varian mengirim varian dulu, mutasinya menyusul', function () {
    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);

    actingAs($this->owner);

    get("/owner/stock/{$variant->id}/history")->assertInertia(fn (Assert $page) => $page
        ->component('Owner/Stock/History')
        // Judul halamannya — nama varian dan stok berjalannya — tidak boleh
        // ikut menunggu riwayatnya.
        ->where('variant.id', $variant->id)
        ->missing('movements')
        ->loadDeferredProps(fn (Assert $reload) => $reload->has('movements.data'))
    );
});
