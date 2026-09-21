<?php

use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

/**
 * Penjaga [BL-004]: seluruh antarmuka berbahasa Indonesia, jadi pesan validasi
 * bawaan Laravel pun harus mengikutinya.
 *
 * Test di sini memeriksa DUA hal yang berbeda dan sama-sama perlu: kalimatnya
 * berbahasa Indonesia, DAN nama kolomnya sudah diterjemahkan. Terjemahan yang
 * hanya menyentuh kalimatnya akan menghasilkan "Kolom business_name wajib
 * diisi" — separuh jadi, dan justru terasa lebih janggal daripada tidak
 * diterjemahkan sama sekali.
 */
test('locale aplikasi adalah bahasa Indonesia', function () {
    expect(app()->getLocale())->toBe('id')
        // Fallback tetap Inggris: kunci yang terlewat lebih baik muncul sebagai
        // kalimat Inggris daripada sebagai "validation.required".
        ->and(config('app.fallback_locale'))->toBe('en');
});

test('pesan wajib isi berbahasa Indonesia dan menyebut nama kolom yang dikenali', function () {
    post('/register', [])->assertSessionHasErrors([
        'business_name' => 'Kolom Nama Usaha wajib diisi.',
        'email' => 'Kolom Email wajib diisi.',
        'password' => 'Kolom Kata Sandi wajib diisi.',
    ]);
});

test('pesan email duplikat berbahasa Indonesia', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'budi@usaha.test']);

    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Budi',
        'email' => 'budi@usaha.test',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasErrors(['email' => 'Email ini sudah dipakai akun lain.']);
});

test('pesan panjang minimum kata sandi berbahasa Indonesia', function () {
    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Budi',
        'email' => 'budi@usaha.test',
        'password' => 'pendek',
        'password_confirmation' => 'pendek',
    ])->assertSessionHasErrors(['password' => 'Kata sandi harus terdiri dari setidaknya 8 karakter.']);
});

test('pesan konfirmasi kata sandi berbahasa Indonesia', function () {
    post('/register', [
        'business_name' => 'Warung Sapi',
        'name' => 'Budi',
        'email' => 'budi@usaha.test',
        'password' => 'password',
        'password_confirmation' => 'berbeda',
    ])->assertSessionHasErrors(['password' => 'Konfirmasi kata sandi tidak cocok.']);
});

test('form tambah staf — kolom yang teramati di [BL-004] — kini berbahasa Indonesia', function () {
    $tenant = Tenant::factory()->active()->create();
    Subscription::factory()->seats(5)->create(['tenant_id' => $tenant->id]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'kasir@usaha.test']);

    // Persis skenario yang dicatat di backlog: email duplikat + kata sandi
    // kurang dari 8 karakter.
    actingAs($owner)->post('/owner/staff', [
        'name' => 'Kasir',
        'email' => 'kasir@usaha.test',
        'password' => 'pendek',
    ])->assertSessionHasErrors([
        'email' => 'Email ini sudah dipakai akun lain.',
        'password' => 'Kata sandi harus terdiri dari setidaknya 8 karakter.',
    ]);
});

test('form login panel platform ikut berbahasa Indonesia', function () {
    // Ditambahkan ke [BL-004] pada 2026-07-21: pesan gagal autentikasinya sudah
    // Indonesia, tapi pesan validasi kolomnya belum.
    post('/platform/login', [])->assertSessionHasErrors([
        'email' => 'Kolom Email wajib diisi.',
        'password' => 'Kolom Kata Sandi wajib diisi.',
    ]);
});

test('pesan kredensial salah datang dari berkas bahasa, bukan ditulis di controller', function () {
    PlatformUser::factory()->owner()->create(['email' => 'pemilik@sapi.test']);

    post('/platform/login', [
        'email' => 'pemilik@sapi.test',
        'password' => 'salah-sekali',
    ])->assertSessionHasErrors(['email' => __('auth.failed')]);

    expect(__('auth.failed'))->toBe('Email atau kata sandi salah.');
});

test('pesan pemulihan kata sandi berbahasa Indonesia', function () {
    expect(__('passwords.sent'))->toBe('Kami telah mengirimkan tautan pemulihan kata sandi ke email Anda.')
        ->and(__('passwords.reset'))->toBe('Kata sandi Anda berhasil diubah.');
});

test('nama bulan ikut berbahasa Indonesia', function () {
    // Efek samping yang menguntungkan dari locale global: tanggal yang dirender
    // lewat translatedFormat() — mis. tenggat penangguhan di halaman langganan —
    // ikut berbahasa Indonesia.
    expect(now()->setDate(2026, 8, 20)->translatedFormat('j F Y'))->toBe('20 Agustus 2026');
});

test('tidak ada kunci terjemahan yang terlewat antara Inggris dan Indonesia', function () {
    $en = require base_path('lang/en/validation.php');
    $id = require base_path('lang/id/validation.php');

    $hilang = array_diff(array_keys($en), array_keys($id));

    // Kalau Laravel menambah aturan validasi baru saat upgrade, test ini yang
    // mengingatkan sebelum pengguna melihat kalimat Inggris nyempil.
    expect($hilang)->toBeEmpty();
});
