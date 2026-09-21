<?php

use App\Models\Tenant;
use App\Models\User;
use App\Services\TenantLogoService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Logo usaha — satu berkas per tenant, menggantikan huruf pertama nama toko
 * di kepala sidebar owner, di topbar kasir, dan di kepala struk.
 *
 * Dua hal yang dijaga di sini sulit terlihat dari layar dan mahal kalau salah:
 * penyimpanannya yang PRIVAT (logo toko lain tidak boleh terambil hanya dengan
 * menebak URL), dan bedanya "jangan sentuh" dengan "buang" — halaman yang sama
 * juga menyimpan alamat dan nomor telepon, jadi salah membacanya berarti logo
 * hilang pada request yang tidak pernah memintanya.
 */
beforeEach(function () {
    Storage::fake('local');

    $this->tenant = Tenant::factory()->create(['name' => 'Kopi Nusantara']);
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
    $this->actingAs($this->owner);
});

it('menyimpan logo sebagai webp di disk privat', function () {
    $this->patch('/owner/settings', [
        'logo' => UploadedFile::fake()->image('logo.png', 900, 300),
    ])->assertRedirect();

    $this->tenant->refresh();

    expect($this->tenant->logo)->toStartWith("logos/{$this->tenant->id}/")
        ->and($this->tenant->logo)->toEndWith('.webp');

    Storage::disk(TenantLogoService::DISK)->assertExists($this->tenant->logo);
});

it('membagikan url logo ke setiap layar lewat prop bersama', function () {
    $this->patch('/owner/settings', [
        'logo' => UploadedFile::fake()->image('logo.png'),
    ]);

    $this->get('/owner/settings')->assertInertia(
        fn (Assert $page) => $page
            ->where('auth.tenant.logo_url', fn ($url) => str_contains($url, '/media/logo?v='))
            ->where('tenant.logo_url', fn ($url) => str_contains($url, '/media/logo?v='))
    );
});

/**
 * Sidik `v` inilah yang membuat header `immutable` di MediaController aman.
 * Rutenya tidak membawa nama berkas, jadi tanpa sidik yang ikut berganti, logo
 * lama akan menempel di peramban pemiliknya setelah ia menggantinya.
 */
it('mengganti sidik url ketika logonya diganti', function () {
    $this->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('satu.png')]);
    $first = app(TenantLogoService::class)->urlFor($this->tenant->refresh());

    $this->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('dua.png')]);
    $second = app(TenantLogoService::class)->urlFor($this->tenant->refresh());

    expect($second)->not->toBe($first);
});

it('membuang berkas lama ketika logonya diganti', function () {
    $this->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('lama.png')]);
    $old = $this->tenant->refresh()->logo;

    $this->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('baru.png')]);
    $new = $this->tenant->refresh()->logo;

    expect($new)->not->toBe($old);
    Storage::disk(TenantLogoService::DISK)->assertMissing($old);
    Storage::disk(TenantLogoService::DISK)->assertExists($new);
});

/**
 * Inti pembedaan "jangan sentuh" vs "buang". Menyimpan nomor telepon tidak
 * boleh menghapus logo yang sudah terpasang.
 */
it('mempertahankan logo saat menyimpan kolom lain tanpa berkas', function () {
    $this->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('logo.png')]);
    $path = $this->tenant->refresh()->logo;

    $this->patch('/owner/settings', ['phone' => '021-1234567'])->assertRedirect();

    $this->tenant->refresh();

    expect($this->tenant->logo)->toBe($path)
        ->and($this->tenant->phone)->toBe('021-1234567');
    Storage::disk(TenantLogoService::DISK)->assertExists($path);
});

it('menghapus logo hanya ketika remove_logo dikirim', function () {
    $this->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('logo.png')]);
    $path = $this->tenant->refresh()->logo;

    $this->patch('/owner/settings', ['remove_logo' => true])->assertRedirect();

    expect($this->tenant->refresh()->logo)->toBeNull();
    Storage::disk(TenantLogoService::DISK)->assertMissing($path);
});

it('menolak berkas yang bukan gambar', function () {
    $this->patch('/owner/settings', [
        'logo' => UploadedFile::fake()->create('logo.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('logo');

    expect($this->tenant->refresh()->logo)->toBeNull();
});

it('menyajikan logo lewat rute ber-auth', function () {
    $this->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('logo.png')]);

    $this->get('/media/logo')
        ->assertOk()
        ->assertHeader('Content-Type', 'image/webp');
});

it('menjawab 404 untuk tenant yang belum punya logo', function () {
    $this->get('/media/logo')->assertNotFound();
});

/**
 * Rutenya tidak menerima parameter tenant sama sekali — "logo siapa" dijawab
 * oleh sesi, bukan oleh URL. Tes ini menjaga bentuk itu: begitu ada yang
 * menambahkan id ke dalamnya, tetangga bisa mengambil logo toko sebelah.
 */
it('tidak pernah menyajikan logo tenant lain', function () {
    $lain = Tenant::factory()->create();
    $pemilikLain = User::factory()->create(['tenant_id' => $lain->id, 'role' => 'owner']);

    $this->actingAs($pemilikLain)
        ->patch('/owner/settings', ['logo' => UploadedFile::fake()->image('logo.png')]);

    $logoLain = $lain->refresh()->logo;

    // Pemilik pertama masih belum punya logo: rute yang sama, untuk sesi yang
    // berbeda, harus 404 — bukan menyajikan berkas milik tenant lain.
    $this->actingAs($this->owner)->get('/media/logo')->assertNotFound();

    expect($logoLain)->toStartWith("logos/{$lain->id}/");
});

it('menutup rute logo untuk tamu', function () {
    auth()->logout();

    $this->get('/media/logo')->assertRedirect('/login');
});

/**
 * Jalur yang BENAR-BENAR dipakai layar. PHP tidak mengurai body multipart pada
 * PATCH, jadi halaman ini mengirim POST dengan `_method`. Tes lain di berkas
 * ini memanggil ->patch() langsung — nyaman, tapi tidak membuktikan apa pun
 * tentang bentuk request yang sesungguhnya keluar dari peramban.
 */
it('menerima unggahan lewat post dengan _method patch', function () {
    $this->post('/owner/settings', [
        '_method' => 'patch',
        'logo' => UploadedFile::fake()->image('logo.png'),
        'phone' => '021-1234567',
    ])->assertRedirect();

    $this->tenant->refresh();

    expect($this->tenant->logo)->not->toBeNull()
        ->and($this->tenant->phone)->toBe('021-1234567');
});
