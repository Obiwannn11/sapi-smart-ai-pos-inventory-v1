<?php

use App\Models\Plan;
use Illuminate\Foundation\Testing\RefreshDatabase;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

beforeEach(function () {
    Plan::query()->delete();
    Plan::factory()->create(['slug' => 'paid-1', 'name' => 'Paid 1', 'base_price' => 100_000]);
});

test('landing tidak menjanjikan masuk lewat Google', function () {
    // Tidak ada Socialite, tidak ada rute OAuth, dan `AuthController@register`
    // hanya menerima email + kata sandi. Tombol "Lanjutkan dengan Google" yang
    // dipajang sebelumnya adalah jalur masuk yang tidak pernah ada.
    $html = get('/')->assertStatus(200)->getContent();

    // Google Fonts boleh; yang dilarang adalah janji autentikasinya.
    expect($html)->not->toContain('Lanjutkan dengan Google')
        ->and($html)->not->toContain('akun Google');
});

test('landing tidak menjanjikan katalog yang dibuatkan otomatis', function () {
    // Registrasi membuat tenant + langganan masa coba + user owner. Tidak ada
    // kategori, menu, maupun saran stok yang disemai — `[BL-034]` mencatat
    // justru sebaliknya: tiap tenant baru mendarat di aplikasi paling kosong.
    get('/')
        ->assertStatus(200)
        ->assertDontSee('otomatis membuatkan')
        ->assertDontSee('daftar menu populer')
        ->assertDontSee('saran stok awal');
});

test('landing tidak mengklaim jumlah pengguna yang tidak bisa dibuktikan', function () {
    get('/')
        ->assertStatus(200)
        ->assertDontSee('ribuan UMKM');
});

test('landing menyebut kapabilitas yang benar-benar ada', function () {
    // Dihitung dari isi berkasnya, `[BL-032]` menemukan nol sebutan untuk
    // sebagian besar yang sudah dikirim sejak Mei. Test ini mengunci sebutannya
    // tetap ada, bukan mengunci kalimatnya.
    $html = get('/')->assertStatus(200)->getContent();

    foreach (['tagihan terbuka', 'sesi kas', 'modifier', 'antrian', 'saran jual', 'pesan mandiri', 'MCP'] as $capability) {
        expect(mb_strtolower($html))->toContain(mb_strtolower($capability));
    }
});

test('landing tidak memajang testimoni dari pelanggan yang tidak ada', function () {
    // Tiga kutipan bernama (Andi/Senja Coffee, Santi/Roti Enak, Budi/Toko
    // Kelontong Modern) berdiri di atas potret stok, sementara basis data hanya
    // berisi dua tenant demo. Dijawab pemilik 2026-08-20: memang karangan, dan
    // diganti bagian "Untuk Siapa SAPI Dibuat" yang tidak mengaku sebagai
    // kesaksian siapa pun (`[BL-078]`).
    $html = get('/')->assertStatus(200)->getContent();

    foreach (['Senja Coffee', 'Roti Enak', 'Toko Kelontong Modern', 'Testimoni', 'avatar_'] as $fabrication) {
        expect($html)->not->toContain($fabrication);
    }

    foreach (['andi', 'budi', 'santi'] as $name) {
        expect(file_exists(public_path("avatar_{$name}.webp")))->toBeFalse()
            ->and(file_exists(public_path("avatar_{$name}.png")))->toBeFalse();
    }
});

test('bagian pengganti testimoni menyebut siapa yang dilayani, bukan siapa yang memuji', function () {
    get('/')
        ->assertStatus(200)
        ->assertSee('Untuk Siapa')
        ->assertSee('Kafe')
        ->assertSee('Toko Kelontong &amp; Retail', false)
        ->assertSee('Usaha dengan Beberapa Kasir');
});

test('tangkapan layar landing dilayani sebagai WebP, bukan PNG mentah', function () {
    // Kelimanya bertanggal 25 Mei dan masih PNG sampai `[BL-032]` butir (3)
    // ditutup: diambil ulang dari aplikasi yang berjalan, lalu dikonversi WebP
    // q80. Yang dikunci di sini bukan kesegarannya — itu tidak bisa diuji —
    // melainkan bahwa tidak ada yang diam-diam mengembalikan PNG-nya.
    $html = get('/')->assertStatus(200)->getContent();

    $layar = ['Dashboard-owner', 'POS-Interface', 'Reports-Daily', 'Stock-Management', 'Product-List'];

    foreach ($layar as $nama) {
        expect($html)->toContain("{$nama}.webp")
            ->and($html)->not->toContain("{$nama}.png");

        $path = public_path("{$nama}.webp");

        expect(file_exists($path))->toBeTrue()
            ->and(filesize($path))->toBeLessThan(120 * 1024)
            ->and(file_exists(public_path("{$nama}.png")))->toBeFalse();
    }
});

test('tangkapan layar landing menyebutkan dimensinya supaya tata letak tidak bergeser', function () {
    // Tanpa width/height, kelima gambar 2160x1350 ini menggeser isi halaman saat
    // menyusul termuat — dan empat di antaranya `loading="lazy"`, jadi mereka
    // menyusul tepat ketika pembaca sedang membaca.
    $html = get('/')->assertStatus(200)->getContent();

    expect(substr_count($html, 'width="2160" height="1350"'))->toBe(6);
});

test('hero tidak menjanjikan ramalan stok maupun tombol yang tidak punya jalan', function () {
    // Tiga klaim di layar pertama, ketiganya diperiksa ke kode lebih dulu:
    // BadgeHelperService membandingkan ambang tetap (stok <= 5, habis, tak
    // terjual 30 hari, lewat expiry_date) dan tidak pernah meramal horizon;
    // BadgeCard.vue hanya membuka-tutup, jadi tidak ada jalan satu tekan dari
    // saran ke diskon terpasang. Lihat [BL-083].
    //
    // Yang dikunci di sini SENGAJA hanya hero. Sebutan prediksi di tab demo
    // dan FAQ masih ada dan dicatat terpisah sebagai [BL-089]; menyapunya di
    // test ini akan membuatnya lulus hanya setelah pekerjaan yang belum
    // diputuskan pemilik ikut dikerjakan.
    $html = get('/')->assertStatus(200)->getContent();

    foreach (['Aman Hingga 14 Hari', 'Eksekusi Sekarang', 'memprediksi stok Anda'] as $klaim) {
        expect($html)->not->toContain($klaim);
    }

    expect($html)->toContain('varian mendekati habis');
});
