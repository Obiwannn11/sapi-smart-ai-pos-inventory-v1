<?php

/**
 * Teks konsol platform.
 *
 * Halaman-halaman ini boleh memakai istilah teknis — pembacanya operator
 * layanan, bukan pemilik warung. Yang dijaga hanya satu hal: tidak ada dua
 * kata untuk satu hal di layar yang sama.
 *
 * Berkas Vue tidak pernah dirender PHP, jadi dijaga langsung di sumbernya,
 * mengikuti pola `CatalogCopyTest`.
 */
function platformPageSource(string $relative): string
{
    return file_get_contents(resource_path("js/Pages/Platform/{$relative}"));
}

test('fitur kasir dinamai fitur, bukan kapabilitas', function () {
    $show = platformPageSource('Tenants/Show.vue');

    // Deskripsi panelnya sudah berbunyi "Fitur yang menyala untuk toko ini".
    expect($show)->not->toContain('Kapabilitas')
        ->and($show)->toContain('title="Fitur sistem kasir"')
        ->and(platformPageSource('Tenants/Index.vue'))->not->toContain('kapabilitas kasir');
});

test('layar tidak memperkenalkan kata "seat"', function () {
    // `PlanContentsTest` melarangnya di halaman publik; konsol ini memakai
    // "pengguna" di seluruh kalimat lainnya.
    expect(platformPageSource('Tenants/Show.vue'))->not->toContain('seat yang sudah dibeli');
});

test('label periode tidak memajang kode formatnya', function () {
    // Placeholder-nya sudah memperagakan "2026-08".
    expect(platformPageSource('Tenants/Show.vue'))->not->toContain('Periode (YYYY-MM)')
        ->and(platformPageSource('Tenants/Show.vue'))->toContain('placeholder="2026-08"');
});

test('halaman masuk dan pemulihan kata sandi memakai satu wordmark', function (string $relative) {
    expect(platformPageSource($relative))->not->toContain('Platform Console');
})->with([
    'Login.vue',
    'TwoFactorChallenge.vue',
    'ForgotPassword.vue',
    'ResetPassword.vue',
]);
