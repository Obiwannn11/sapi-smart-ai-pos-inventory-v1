<?php

/**
 * Pengelompokan sidebar owner (`[BL-085]`).
 *
 * Sidebarnya Vue dan tidak pernah dirender PHP, jadi yang bisa dijaga di sini
 * adalah SUMBERNYA. Itu cukup untuk dua hal yang benar-benar mudah rusak:
 * item promosi diam-diam kembali ke grup Keuangan, dan gerbang akses sebuah
 * item hilang saat ia dipindahkan antar-grup — yang kedua tidak akan terlihat
 * di layar owner sama sekali, karena owner melewati setiap pemeriksaan.
 */

/**
 * Isi satu grup sidebar, dipotong dari `OwnerLayout.vue` berdasarkan labelnya.
 */
function sidebarGroupSource(string $label): string
{
    $layout = file_get_contents(resource_path('js/Layouts/OwnerLayout.vue'));

    expect($layout)->toContain("label: '{$label}'");

    $start = strpos($layout, "label: '{$label}'");
    $end = strpos($layout, '],', $start);

    return substr($layout, $start, $end - $start);
}

test('aturan saran jual dan diskon tinggal di grup penjualan, bukan keuangan', function () {
    $promosi = sidebarGroupSource('Penjualan & Promosi');
    $keuangan = sidebarGroupSource('Keuangan');

    expect($promosi)->toContain('/owner/reports/upsell')
        ->and($promosi)->toContain('/owner/upsell-rules')
        ->and($promosi)->toContain('/owner/discount-rules');

    expect($keuangan)->not->toContain('/owner/upsell-rules')
        ->and($keuangan)->not->toContain('/owner/discount-rules')
        ->and($keuangan)->not->toContain('/owner/reports/upsell');
});

test('keuangan tetap memegang laporan, transaksi, kas, dan pembayaran', function () {
    $keuangan = sidebarGroupSource('Keuangan');

    expect($keuangan)->toContain('/owner/reports/daily')
        ->and($keuangan)->toContain('/owner/reports/monthly')
        ->and($keuangan)->toContain('/owner/transactions')
        ->and($keuangan)->toContain('/owner/cash-drawers')
        ->and($keuangan)->toContain('/owner/offline-review')
        ->and($keuangan)->toContain('/owner/payment-methods')
        ->and($keuangan)->toContain('/owner/ai-analysis');
});

test('gerbang tiap item ikut pindah apa adanya', function () {
    // Inilah yang tidak boleh hilang saat item berpindah grup: menulis aturan
    // saran jual dan diskon adalah keputusan pemilik usaha (`ownerOnly`),
    // sementara MEMBACA laporannya cukup izin modul `reports`. Menyamakan
    // keduanya memberi kuasa menulis kepada siapa pun yang hanya diberi hak
    // membaca laporan — persis yang ditolak di `UpsellRuleController`.
    $promosi = sidebarGroupSource('Penjualan & Promosi');

    expect($promosi)->toMatch("~/owner/upsell-rules'.*ownerOnly: true~")
        ->and($promosi)->toMatch("~/owner/discount-rules'.*ownerOnly: true~")
        ->and($promosi)->toMatch("~/owner/reports/upsell'.*perm: 'reports'~");
});
