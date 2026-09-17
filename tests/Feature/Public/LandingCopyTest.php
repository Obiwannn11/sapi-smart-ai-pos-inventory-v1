<?php

use function Pest\Laravel\get;

/**
 * Klaim di halaman publik yang belum tercakup `LandingClaimsTest`.
 *
 * Berkas ini melanjutkan pekerjaan yang sama: bukan menjaga kalimatnya,
 * melainkan menjaga bahwa halaman ini tidak menjanjikan hal yang tidak
 * dilakukan kodenya. Ketiga temuan di bawah diperiksa ke kode lebih dulu.
 */
test('tidak ada janji horizon stok dalam bentuk apa pun', function (string $klaim) {
    // `BadgeHelperService` membandingkan ambang tetap. "Aman Hingga 14 Hari"
    // sudah dijaga `LandingClaimsTest`; gelembung "Cara SAPI" memakai kalimat
    // lain dan luput sampai sekarang.
    expect(get('/')->assertStatus(200)->getContent())->not->toContain($klaim);
})->with([
    'hari ke depan',
    'aman untuk 10 hari',
]);

test('halaman publik tidak mengklaim AI yang dilatih per toko', function (string $klaim) {
    // `AiProviderFactory` memanggil API model pihak ketiga (SumoPod, Gemini,
    // OpenAI, Anthropic). Tidak ada pelatihan model di basis kode ini.
    expect(get('/')->assertStatus(200)->getContent())->not->toContain($klaim);
})->with([
    'dilatih khusus',
    'AI dilatih',
    'enkripsi standar industri',
]);

test('pendaftaran tidak mengaku disiapkan AI, dan tidak menyebut lama yang tak pernah diukur', function (string $klaim) {
    // Langkah kedua di bagian yang sama menyatakan katalog disusun sendiri
    // oleh pemiliknya; `[BL-032]` sudah membuang klaim serupa sebelumnya.
    expect(get('/')->assertStatus(200)->getContent())->not->toContain($klaim);
})->with([
    'AI-Powered Setup',
    'menyiapkan segalanya untuk Anda',
    'Hanya dalam <span class="text-primary">3 Menit</span>',
]);

test('jawaban offline menyebut kemampuan yang memang ada', function () {
    $html = get('/')->assertStatus(200)->getContent();

    // Penjualan offline tersimpan di IndexedDB dan tersinkron sendiri; yang
    // memang butuh koneksi adalah non-tunai dan tagihan terbuka.
    expect($html)->not->toContain('mode cache terbatas')
        ->and($html)->toContain('terkirim sendiri begitu koneksi kembali');
});

test('kata jualan tanpa isi tidak kembali', function (string $frasa) {
    expect(get('/')->assertStatus(200)->getContent())->not->toContain($frasa);
})->with([
    'bukan sekadar aplikasi kasir biasa',
    'Scale-up',
    'Bukti Nyata',
    'real-time tanpa ribet',
    'setiap detik',
    'ribuan SKU',
]);
