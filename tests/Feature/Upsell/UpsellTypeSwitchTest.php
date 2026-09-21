<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\UpsellRule;
use App\Models\User;
use App\Services\Upsell\UpsellIndexBuilder;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;

/**
 * Saklar per-jenis saran jual, milik tiap toko (`[BL-099]`).
 *
 * Laporan Saran Jual memisahkan angkanya per jenis SUPAYA jenis yang tak
 * pernah diterima bisa dimatikan; sampai entri ini, satu-satunya saklarnya ada
 * di `config/upsell.php` — berkas PHP yang hanya bisa disentuh orang dengan
 * akses server.
 *
 * Yang dijaga di sini bukan hanya "saklarnya bekerja", melainkan ARAH kedua
 * lapisannya. Config adalah saklar darurat global milik pemilik SaaS dan ia
 * menang; saklar tenant hanya boleh mematikan yang masih hidup secara global.
 * Kalau owner bisa menghidupkan kembali apa yang dimatikan pemilik SaaS,
 * saklar daruratnya bukan saklar darurat — dan tidak ada satu pun layar yang
 * akan memperlihatkan kesalahan itu.
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
 * Sepasang varian satu produk dengan selisih harga yang lolos batas naik
 * ukuran — cukup untuk membuat mesin menghasilkan satu saran `upsize`.
 */
function switchablePair(Tenant $tenant): ProductVariant
{
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);

    $single = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Single',
        'price' => 10000,
        'stock' => 20,
        'expiry_date' => null,
    ]);

    ProductVariant::factory()->create([
        'product_id' => $product->id,
        'name' => 'Double',
        'price' => 14000,
        'stock' => 20,
        'expiry_date' => null,
    ]);

    return $single;
}

function typesInIndex(Tenant $tenant): array
{
    $index = app(UpsellIndexBuilder::class)->build($tenant);

    $types = array_column($index['cart_level'], 'type');

    foreach ($index['by_variant'] as $suggestions) {
        $types = array_merge($types, array_column($suggestions, 'type'));
    }

    return array_values(array_unique($types));
}

// --- Bawaan ---

test('toko baru punya keempat jenis dalam keadaan hidup', function () {
    // Bawaannya HIDUP, kebalikan dari `upsell_mandatory`. Keempat jenis ini
    // sudah berjalan untuk setiap tenant sebelum saklarnya ada, jadi bawaan
    // mati akan mematikan fitur yang sedang dipakai pada hari migrasinya jalan.
    foreach (array_keys(Tenant::upsellTypeColumns()) as $type) {
        expect($this->tenant->upsellTypeEnabled($type))->toBeTrue("jenis {$type} seharusnya hidup");
    }
});

// --- Saklar tenant benar-benar mematikan ---

test('mematikan naik ukuran menghapusnya dari indeks toko itu', function () {
    $single = switchablePair($this->tenant);

    expect(typesInIndex($this->tenant))->toContain('upsize');

    $this->tenant->update(['upsell_upsize_enabled' => false]);

    expect(typesInIndex($this->tenant->fresh()))->not->toContain('upsize')
        // Yang dimatikan hanya satu jenis. Saran pemicu yang sama dari jenis
        // lain tidak boleh ikut hilang.
        ->and($single->fresh())->not->toBeNull();
});

test('mematikan aturan manual membungkam seluruh aturan owner sekaligus', function () {
    $suggested = switchablePair($this->tenant);

    UpsellRule::factory()->create([
        'tenant_id' => $this->tenant->id,
        'suggested_variant_id' => $suggested->id,
    ]);

    expect(typesInIndex($this->tenant))->toContain('manual');

    $this->tenant->update(['upsell_manual_enabled' => false]);

    expect(typesInIndex($this->tenant->fresh()))->not->toContain('manual');
});

// --- Arah kedua lapisannya ---

test('saklar toko tidak bisa menghidupkan jenis yang dimatikan pemilik SaaS', function () {
    switchablePair($this->tenant);

    // Saklar tenant-nya HIDUP — bawaannya memang begitu.
    config(['upsell.types.upsize' => false]);

    expect($this->tenant->upsellTypeEnabled('upsize'))->toBeTrue()
        ->and(typesInIndex($this->tenant))->not->toContain('upsize');
});

test('kehendak toko tetap tersimpan walau jenisnya sedang mati global', function () {
    switchablePair($this->tenant);

    config(['upsell.types.upsize' => false]);

    // Pilihan owner disimpan apa adanya; config yang menang saat DIBACA.
    // Menolak menyimpannya berarti pilihan itu hilang begitu pemilik SaaS
    // menyalakan jenisnya kembali.
    patch(route('owner.settings.operations.update'), ['upsell_upsize_enabled' => true]);

    expect($this->tenant->fresh()->upsell_upsize_enabled)->toBeTrue()
        ->and(typesInIndex($this->tenant->fresh()))->not->toContain('upsize');

    config(['upsell.types.upsize' => true]);

    expect(typesInIndex($this->tenant->fresh()))->toContain('upsize');
});

// --- Batas tenant ---

test('saklar satu toko tidak menyentuh toko lain', function () {
    switchablePair($this->tenant);

    $lain = Tenant::factory()->create();
    $pemilikLain = User::factory()->create([
        'tenant_id' => $lain->id,
        'role' => 'owner',
    ]);
    switchablePair($lain);

    $this->tenant->update(['upsell_upsize_enabled' => false]);

    expect(typesInIndex($this->tenant->fresh()))->not->toContain('upsize');

    // Berpindah pengguna, bukan sekadar berpindah argumen: katalognya dibaca
    // lewat TenantScope, yang membaca pengguna yang sedang masuk — bukan
    // tenant yang dioper ke `build()`. Menguji isolasi tanpa berpindah
    // pengguna akan LULUS karena alasan yang salah (indeksnya kosong untuk
    // semua orang), dan menyembunyikan kebocoran yang sebenarnya dicari.
    actingAs($pemilikLain);

    expect(typesInIndex($lain))->toContain('upsize');
});

// --- Layar Setelan ---

test('halaman Cara Kerja Sistem mengirim keempat saklar', function () {
    $this->tenant->update(['upsell_attach_enabled' => false]);

    get(route('owner.settings.operations.index'))
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Settings/Operations')
            ->where('features.upsell_attach_enabled', false)
            ->where('features.upsell_pressed_stock_enabled', true)
            ->where('features.upsell_upsize_enabled', true)
            ->where('features.upsell_manual_enabled', true)
        );
});

test('jenis yang mati global dikirim sebagai terkunci, bukan disembunyikan', function () {
    // Saklarnya tetap tampil di layar. Menyembunyikannya membuat owner
    // mengira jenis itu tidak pernah ada.
    config(['upsell.types.pressed_stock' => false]);

    get(route('owner.settings.operations.index'))
        ->assertInertia(fn ($page) => $page
            ->where('upsellTypesLockedGlobally', ['pressed_stock'])
            ->where('features.upsell_pressed_stock_enabled', true)
        );
});

test('owner bisa menyimpan keempat saklar dari satu formulir', function () {
    patch(route('owner.settings.operations.update'), [
        'upsell_attach_enabled' => false,
        'upsell_pressed_stock_enabled' => false,
        'upsell_upsize_enabled' => true,
        'upsell_manual_enabled' => true,
    ]);

    $tenant = $this->tenant->fresh();

    expect($tenant->upsell_attach_enabled)->toBeFalse()
        ->and($tenant->upsell_pressed_stock_enabled)->toBeFalse()
        ->and($tenant->upsell_upsize_enabled)->toBeTrue()
        ->and($tenant->upsell_manual_enabled)->toBeTrue();
});

test('menyimpan saklar jenis tidak menyentuh setelan lain di halaman yang sama', function () {
    // Cerminan penjaga di `SettingsSplitTest`: satu field tidak boleh ikut
    // menulis field tetangganya hanya karena berbagi satu endpoint.
    $this->tenant->update(['upsell_mandatory' => true, 'kitchen_queue_enabled' => true]);

    patch(route('owner.settings.operations.update'), ['upsell_attach_enabled' => false]);

    $tenant = $this->tenant->fresh();

    expect($tenant->upsell_attach_enabled)->toBeFalse()
        ->and($tenant->upsell_mandatory)->toBeTrue()
        ->and($tenant->kitchen_queue_enabled)->toBeTrue();
});

// --- Laporan ---

test('laporan menandai jenis yang sedang mati, apa pun asal matinya', function () {
    // Di layar ini kedua lapisan sengaja digabung: pertanyaannya cuma
    // "apakah nol ini berarti gagal atau berarti mati", dan untuk itu asal
    // matinya tidak penting. Bedanya terlihat satu tautan jauhnya.
    $this->tenant->update(['upsell_attach_enabled' => false]);
    config(['upsell.types.pressed_stock' => false]);

    get(route('owner.reports.upsell'))
        ->assertInertia(fn ($page) => $page
            ->component('Owner/Reports/Upsell')
            ->where('inactiveTypes', ['attach', 'pressed_stock'])
        );
});
