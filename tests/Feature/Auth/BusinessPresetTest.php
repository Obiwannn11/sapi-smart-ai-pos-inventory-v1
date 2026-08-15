<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\BusinessPresetService;
use Illuminate\Testing\TestResponse;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Preset kapabilitas per jenis usaha (`[BL-034]`).
 *
 * Sebelum entri ini, jawaban "Jenis Usaha" di formulir daftar tidak mengubah
 * apa pun selain penetapan harga: warung bazar dan kafe mendarat di aplikasi
 * yang persis sama. Yang dikunci di sini bukan hanya bahwa presetnya BERLAKU,
 * tapi juga dua batasnya — bahwa ia berlaku SEKALI, dan bahwa pendaftar tetap
 * bisa menolaknya.
 */
function registerBusiness(array $overrides = []): TestResponse
{
    return post('/register', array_merge([
        'business_name' => 'Kopi Story',
        'name' => 'Pemilik',
        'email' => 'pemilik@kopistory.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ], $overrides));
}

// --- Peta presetnya sendiri ---

test('setiap jenis usaha punya preset, dan setiap fitur preset dikenali Tenant', function () {
    $presets = config('business-presets.presets');
    $features = config('business-presets.features');

    // Jenis usaha tanpa baris preset akan mendarat tanpa satu pun kapabilitas —
    // lebih buruk daripada keadaan sebelum preset ada.
    foreach (array_keys(config('pricing-dimensions.business_type.options')) as $businessType) {
        expect($presets)->toHaveKey($businessType);
    }

    $tenant = new Tenant;

    foreach ($features as $name => $definition) {
        // Nama fitur yang salah ketik akan dijawab `false` oleh hasFeature()
        // tanpa keluhan apa pun, jadi centangnya menyala di layar lalu tidak
        // menggerbangi apa-apa. Ini satu-satunya tempat kekeliruan itu terlihat.
        $tenant->forceFill([$definition['column'] => true]);
        expect($tenant->hasFeature($name))->toBeTrue();

        expect(Tenant::make()->getFillable())->toContain($definition['column']);
    }

    // Nama fitur di preset harus ada di katalog, bukan sekadar mirip.
    foreach ($presets as $businessType => $names) {
        expect(array_diff($names, array_keys($features)))->toBeEmpty();
    }
});

// --- Penerapannya saat pendaftaran ---

test('mendaftar sebagai kuliner menyalakan antrian dapur', function () {
    registerBusiness(['business_type' => 'kuliner']);

    $tenant = Tenant::where('name', 'Kopi Story')->firstOrFail();

    expect($tenant->hasFeature('kitchen_queue'))->toBeTrue()
        ->and($tenant->hasFeature('ai'))->toBeTrue()
        // Membuka tautan pemesanan publik keputusan pemiliknya sendiri, bukan
        // simpulan dari jenis usaha yang ia pilih.
        ->and($tenant->hasFeature('self_order'))->toBeFalse();
});

test('mendaftar sebagai retail tidak menyalakan antrian dapur', function () {
    registerBusiness([
        'business_name' => 'Toko Sembako',
        'business_type' => 'retail',
        'email' => 'pemilik@sembako.test',
    ]);

    $tenant = Tenant::where('name', 'Toko Sembako')->firstOrFail();

    expect($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->hasFeature('ai'))->toBeTrue();
});

test('pendaftar boleh menolak preset — daftar centang yang dikirim yang dipakai', function () {
    registerBusiness([
        'business_type' => 'kuliner',
        // Jenis usahanya kuliner, tapi dapurnya tidak dipakai.
        'features' => ['ai'],
    ]);

    $tenant = Tenant::where('name', 'Kopi Story')->firstOrFail();

    expect($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->hasFeature('ai'))->toBeTrue();
});

test('melepas semua centang menghasilkan tenant tanpa kapabilitas, bukan preset', function () {
    registerBusiness([
        'business_type' => 'kuliner',
        'features' => [],
    ]);

    $tenant = Tenant::where('name', 'Kopi Story')->firstOrFail();

    // `ai` bawaannya MENYALA di kolom database. Kalau daftar kosong diperlakukan
    // sebagai "tidak dijawab", tenant ini akan tetap mendapat AI yang baru saja
    // ia tolak — dan tak ada yang tahu sampai tagihan pertamanya.
    expect($tenant->hasFeature('ai'))->toBeFalse()
        ->and($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->hasFeature('self_order'))->toBeFalse();
});

test('nama fitur di luar katalog ditolak', function () {
    registerBusiness([
        'business_type' => 'kuliner',
        'features' => ['kitchen_queue', 'akses_penuh'],
    ])->assertSessionHasErrors('features.1');

    expect(Tenant::where('name', 'Kopi Story')->exists())->toBeFalse();
});

test('pendaftaran tanpa mengirim daftar fitur jatuh ke preset', function () {
    // Klien yang tidak tahu apa-apa tentang preset — permintaan langsung, tes
    // lama — tidak boleh mendaratkan tenant tanpa kapabilitas apa pun.
    registerBusiness(['business_type' => 'kuliner']);

    expect(Tenant::where('name', 'Kopi Story')->firstOrFail()->hasFeature('kitchen_queue'))
        ->toBeTrue();
});

test('tanpa memilih jenis usaha, preset bawaannya yang dipakai', function () {
    registerBusiness(['business_name' => 'Warung Tanpa Tipe', 'email' => 'p@warung.test']);

    $tenant = Tenant::where('name', 'Warung Tanpa Tipe')->firstOrFail();

    expect($tenant->business_type)->toBe(Tenant::BUSINESS_TYPE_DEFAULT)
        ->and($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->hasFeature('ai'))->toBeTrue();
});

// --- Batas terpentingnya: preset berlaku SEKALI ---

test('mengubah jenis usaha dari Pengaturan tidak menerapkan ulang presetnya', function () {
    // Pemilik ini sudah mematikan antrian dapur dengan sengaja.
    $tenant = Tenant::factory()->active()->create([
        'business_type' => 'lainnya',
        'kitchen_queue_enabled' => false,
        'ai_enabled' => false,
    ]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)
        ->patch('/owner/settings', ['business_type' => 'kuliner'])
        ->assertSessionHasNoErrors();

    $tenant->refresh();

    expect($tenant->business_type)->toBe('kuliner')
        // Ia sedang membetulkan keterangan tokonya, bukan meminta layar dapur
        // muncul kembali.
        ->and($tenant->hasFeature('kitchen_queue'))->toBeFalse()
        ->and($tenant->hasFeature('ai'))->toBeFalse();
});

// --- Layarnya ---

test('halaman daftar mengirim katalog dan peta presetnya', function () {
    get('/register')->assertInertia(fn ($page) => $page
        ->component('Auth/Register')
        ->has('featureCatalog', count(config('business-presets.features')))
        ->has('featureCatalog.0.name')
        ->has('featureCatalog.0.label')
        ->has('featureCatalog.0.description')
        ->where('featurePresets.kuliner', config('business-presets.presets.kuliner'))
        ->where('defaultFeatures', app(BusinessPresetService::class)->featuresFor(null))
    );
});
