<?php

/**
 * Satu nama untuk harga terendah yang boleh dicapai sebuah barang.
 *
 * Layar pemilik menyebutnya "lantai untung", layar kasir "batas untung", dan
 * kartu di Cara Kerja Sistem sudah berjudul "Batas Untung Minimum" sementara
 * paragraf di bawahnya masih menyebut "lantai" — dua kata untuk satu hal, di
 * dalam satu kotak yang sama. Pemiliknya memilih "batas untung".
 *
 * Yang dijaga di sini hanya tulisan yang dibaca orang. Kata "lantai" sengaja
 * dibiarkan di komentar kode dan nama test: di sana ia istilah domain, dan
 * dipakai juga oleh hal lain ("lantai skor" pada aturan saran jual).
 */
function floorWordingSource(string $relative): string
{
    return file_get_contents(resource_path("js/{$relative}"));
}

test('halaman aturan diskon menyebut batas untung, bukan lantai', function () {
    $source = floorWordingSource('Pages/Owner/DiscountRules/Index.vue');

    expect($source)->toContain('>Batas untung (modal + {{ minMarginPercent }}%)</a>')
        ->and($source)->toContain("floor_absorbed: 'Habis dimakan batas untung'")
        ->and($source)->toContain('>batas {{ formatRupiah(rule.effective?.floor) }}</span>');
});

test('chip pada baris yang terjepit berbunyi sama di pratinjau dan di tabel', function () {
    // Dua tempat, satu kalimat. Chip di tabel berdiri sendiri tanpa label
    // rumus di dekatnya, jadi keduanya menyebut "batas untung" lengkap.
    $source = floorWordingSource('Pages/Owner/DiscountRules/Index.vue');

    expect($source)->toContain('>Tertahan batas untung</span>')
        ->and($source)->toContain("\n                                            Tertahan batas untung\n")
        ->and($source)->not->toContain('Tertahan lantai');
});

test('kartu Batas Untung Minimum memakai kata yang sama dengan judulnya', function () {
    $source = floorWordingSource('Pages/Owner/Settings/Operations.vue');

    expect($source)->toContain('Batas Untung Minimum')
        ->and($source)->toContain('di bawah batas ini')
        ->and($source)->toContain('punya batas')
        ->and($source)->not->toContain('Lantai harga tiap barang')
        ->and($source)->not->toContain('di bawah lantai ini');
});
