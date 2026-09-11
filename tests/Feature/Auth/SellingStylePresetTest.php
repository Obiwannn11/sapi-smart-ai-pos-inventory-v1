<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessPresetService;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Paket setelan awal per cara berjualan (`[BL-035]`).
 *
 * "Mode bazar" ternyata bukan mode: tidak ada flag, tidak ada rute yang
 * digerbangi, tidak ada perilaku baru. Yang ada adalah bundel nilai awal, dan
 * yang dikunci di sini adalah empat batasnya — bahwa kuncinya TERPISAH dari
 * `business_type` yang milik penetapan harga, bahwa setelan tersembunyi datang
 * dari paket dan bukan dari daftar centang yang tak pernah memuatnya, bahwa
 * pendaftar tetap bisa menolaknya, dan bahwa penerapan ulang hanya terjadi
 * kalau DIMINTA.
 */
function registerWithStyle(array $overrides = []): TestResponse
{
    return post('/register', array_merge([
        'business_name' => 'Ayam Kriuk Bazar',
        'name' => 'Pemilik',
        'email' => 'pemilik@ayamkriuk.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ], $overrides));
}

// --- Peta paketnya sendiri ---

test('setiap cara berjualan punya paket, dan isinya dikenali katalog', function () {
    $presets = app(BusinessPresetService::class);

    foreach ($presets->styleNames() as $style) {
        $preset = $presets->presetFor($style);

        // Cara berjualan tanpa paket akan mendarat tanpa setelan apa pun.
        expect(config("business-presets.presets.{$style}"))->not->toBeNull();
        expect($preset['features'])->toBeArray();
    }
});

test('pajak dan angka kebijakan tidak boleh jadi anggota paket mana pun', function () {
    // Bukan preferensi gaya berjualan: status pajak urusan hukum, dan margin
    // minimum serta ambang pengeluaran kas adalah angka kebijakan. Paket yang
    // ikut menyetelnya akan salah lebih sering daripada benar.
    $columns = array_column(config('business-presets.settings'), 'column');

    expect($columns)->not->toContain('tax_enabled')
        ->and($columns)->not->toContain('tax_rate')
        ->and($columns)->not->toContain('tax_mode')
        ->and($columns)->not->toContain('min_margin_percent')
        ->and($columns)->not->toContain('cash_payout_approval_threshold');
});

// --- Kuncinya terpisah dari penetapan harga ---

test('cara berjualan tersimpan tanpa mengubah jenis usaha yang dipakai harga', function () {
    registerWithStyle([
        'business_type' => 'kuliner',
        'selling_style' => 'gerai_acara',
    ]);

    $tenant = Tenant::where('name', 'Ayam Kriuk Bazar')->firstOrFail();

    // Penjual di CFD tetap `kuliner` di mata tarif — yang berbeda cuma cara ia
    // bekerja. Kalau baris ini gagal, "gerai acara" sudah bocor ke dimensi
    // harga dan akan ikut membeku di `invoices.pricing_context`.
    expect($tenant->business_type)->toBe('kuliner')
        ->and($tenant->selling_style)->toBe('gerai_acara');
});

test('cara berjualan bukan pilihan jenis usaha yang sah', function () {
    // Penjagaan arah sebaliknya: `gerai_acara` tidak boleh diterima sebagai
    // `business_type`, karena di sanalah ia akan menyentuh harga.
    registerWithStyle(['business_type' => 'gerai_acara'])
        ->assertSessionHasErrors('business_type');
});

// --- Isi paket gerai acara ---

test('paket gerai acara menyalakan antrian dan memakai kode panggil', function () {
    registerWithStyle(['selling_style' => 'gerai_acara']);

    $tenant = Tenant::where('name', 'Ayam Kriuk Bazar')->firstOrFail();

    expect($tenant->hasFeature('kitchen_queue'))->toBeTrue()
        ->and($tenant->hasFeature('ai'))->toBeTrue()
        // Tautan pemesanan publik tetap keputusan pemiliknya sendiri.
        ->and($tenant->hasFeature('self_order'))->toBeFalse()
        // Inilah "nomor antrian ditonjolkan" dari catatan pemilik: kasir tidak
        // mengetik apa pun, sistem yang mengalokasikan nomornya.
        ->and($tenant->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_CODE)
        // Menahan tombol bayar di antrean acara adalah racun.
        ->and($tenant->upsell_mandatory)->toBeFalse()
        // Satu langkah tambahan di tiap penjualan non-tunai; antrean panjang
        // paling dirugikan.
        ->and($tenant->hasFeature('payment_proof'))->toBeFalse();
});

test('paket warung menetap memanggil dengan nama, bukan kode', function () {
    registerWithStyle([
        'business_name' => 'Kopi Story',
        'email' => 'p@kopistory.test',
        'selling_style' => 'warung_menetap',
    ]);

    expect(Tenant::where('name', 'Kopi Story')->firstOrFail()->order_identity_mode)
        ->toBe(Tenant::ORDER_IDENTITY_NAME);
});

// --- Jembatan dari jenis usaha ---

test('tanpa cara berjualan, jenis usaha jadi tebakannya', function () {
    // Klien yang tidak tahu apa-apa tentang cara berjualan — permintaan
    // langsung, tes lama — tidak boleh mendaratkan tenant tanpa setelan.
    registerWithStyle(['business_type' => 'kuliner']);

    $tenant = Tenant::where('name', 'Ayam Kriuk Bazar')->firstOrFail();

    expect($tenant->selling_style)->toBe('warung_menetap')
        ->and($tenant->hasFeature('kitchen_queue'))->toBeTrue();
});

test('tanpa jawaban apa pun, cara berjualan bawaannya yang dipakai', function () {
    registerWithStyle();

    $tenant = Tenant::where('name', 'Ayam Kriuk Bazar')->firstOrFail();

    expect($tenant->selling_style)->toBe(app(BusinessPresetService::class)->defaultStyle())
        ->and($tenant->hasFeature('kitchen_queue'))->toBeFalse();
});

test('cara berjualan di luar daftar ditolak', function () {
    registerWithStyle(['selling_style' => 'mode_bazar'])
        ->assertSessionHasErrors('selling_style');

    expect(Tenant::where('name', 'Ayam Kriuk Bazar')->exists())->toBeFalse();
});

// --- Batas: yang tersembunyi datang dari paket, bukan dari daftar centang ---

test('setelan tersembunyi tidak bisa dikirim lewat daftar centang', function () {
    // Formulir tidak pernah menawarkannya, jadi menerimanya di sini berarti
    // membuka jalur agar permintaan buatan tangan menyetel sesuatu yang tidak
    // punya tombol.
    registerWithStyle([
        'selling_style' => 'gerai_acara',
        'features' => ['kitchen_queue', 'payment_proof'],
    ])->assertSessionHasErrors('features.1');
});

test('melepas semua centang tidak ikut mematikan setelan yang tak pernah ditanyakan', function () {
    // Jebakan yang paling mudah terjadi: daftar centang cuma memuat setelan
    // yang tampil, jadi memperlakukan ketidakhadiran sebagai "dilepas" akan
    // memaksa mati setiap setelan tersembunyi — termasuk yang paketnya ingin
    // nyalakan. Di sini `order_identity_mode` yang membuktikannya: ia bukan
    // boolean dan tidak pernah ada di daftar centang.
    registerWithStyle([
        'selling_style' => 'gerai_acara',
        'features' => [],
    ]);

    $tenant = Tenant::where('name', 'Ayam Kriuk Bazar')->firstOrFail();

    expect($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->hasFeature('ai'))->toBeFalse()
        // Yang tidak ditanyakan tetap mengikuti paket.
        ->and($tenant->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_CODE);
});

test('pendaftar boleh menolak isi paket yang tampil', function () {
    registerWithStyle([
        'selling_style' => 'gerai_acara',
        'features' => ['ai'],
        'order_identity_mode' => Tenant::ORDER_IDENTITY_NONE,
    ]);

    $tenant = Tenant::where('name', 'Ayam Kriuk Bazar')->firstOrFail();

    // Paket cuma mengisi nilai awal; jawaban terakhir pendaftar yang menang.
    expect($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_NONE);
});

// --- Penerapan ulang: hanya kalau diminta ---

test('pemilik bisa menerapkan ulang sebuah paket dari Pengaturan', function () {
    $tenant = Tenant::factory()->active()->create([
        'business_type' => 'kuliner',
        'selling_style' => 'warung_menetap',
        'kitchen_queue_enabled' => false,
        'order_identity_mode' => Tenant::ORDER_IDENTITY_NAME,
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->post('/owner/settings/operations/preset', ['selling_style' => 'gerai_acara'])
        ->assertSessionHasNoErrors();

    $tenant->refresh();

    expect($tenant->selling_style)->toBe('gerai_acara')
        ->and($tenant->hasFeature('kitchen_queue'))->toBeTrue()
        ->and($tenant->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_CODE)
        // Jenis usaha tidak ikut berubah — paket tidak menyentuh harga.
        ->and($tenant->business_type)->toBe('kuliner');
});

test('menerapkan paket tanpa menyebut namanya ditolak', function () {
    // Tidak ada jalur "terapkan yang sekarang" yang bisa terpanggil tanpa
    // pilihan sadar. Kalau `required` ini hilang, tombolnya bisa terpicu oleh
    // permintaan kosong.
    $tenant = Tenant::factory()->active()->create(['kitchen_queue_enabled' => false]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->post('/owner/settings/operations/preset', [])
        ->assertSessionHasErrors('selling_style');

    expect($tenant->refresh()->hasFeature('kitchen_queue'))->toBeFalse();
});

test('mengubah jenis usaha tetap tidak menerapkan ulang paket apa pun', function () {
    // Batas terpenting `[BL-034]`, diperiksa ulang sesudah `[BL-035]` menambah
    // jalur penerapan kedua: jalur itu harus SATU-SATUNYA yang bisa menyalakan
    // sesuatu, dan membetulkan jenis usaha bukan salah satunya.
    $tenant = Tenant::factory()->active()->create([
        'business_type' => 'lainnya',
        'selling_style' => 'gerai_acara',
        'kitchen_queue_enabled' => false,
        'order_identity_mode' => Tenant::ORDER_IDENTITY_NONE,
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->patch('/owner/settings', ['business_type' => 'kuliner'])
        ->assertSessionHasNoErrors();

    $tenant->refresh();

    expect($tenant->business_type)->toBe('kuliner')
        ->and($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_NONE)
        // Cara berjualan pun tidak boleh ikut tergeser oleh jenis usaha baru.
        ->and($tenant->selling_style)->toBe('gerai_acara');
});

// --- Layarnya ---

test('halaman daftar mengirim cara berjualan dan ringkasan yang tak ditanyakan', function () {
    $presets = app(BusinessPresetService::class);

    get('/register')->assertInertia(fn ($page) => $page
        ->component('Auth/Register')
        ->has('sellingStyles', count($presets->styleNames()))
        ->has('sellingStyles.0.name')
        ->has('sellingStyles.0.label')
        ->has('sellingStyles.0.description')
        ->where('businessTypeStyles.kuliner', 'warung_menetap')
        ->where('defaultStyle', $presets->defaultStyle())
        ->has('choiceOptions.order_identity_mode')
        // Syarat pembalikan aturan: yang tidak ditanyakan wajib disebutkan.
        ->has('hiddenSummaries.gerai_acara')
        ->where('hiddenSummaries.gerai_acara', $presets->hiddenSummaryFor('gerai_acara'))
    );

    expect($presets->hiddenSummaryFor('gerai_acara'))->not->toBeEmpty();
});

test('halaman Cara Kerja Sistem mengirim paket beserta isinya untuk pratinjau', function () {
    $tenant = Tenant::factory()->active()->create(['selling_style' => 'gerai_acara']);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->get('/owner/settings/operations')
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Settings/Operations')
            ->has('sellingStyles')
            // Isinya dikirim utuh supaya layar bisa menghitung sendiri apa yang
            // akan berubah SEBELUM tombolnya ditekan.
            ->has('stylePresets.gerai_acara.features')
            ->has('stylePresets.gerai_acara.settings')
            ->has('settingCatalog')
            ->where('currentStyle', 'gerai_acara')
        );
});
