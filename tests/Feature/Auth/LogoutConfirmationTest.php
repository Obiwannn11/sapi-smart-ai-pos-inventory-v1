<?php

/**
 * Keluar selalu ditanya dulu, di semua jenis akun.
 *
 * Tesnya memeriksa berkas, bukan HTTP: rutenya sendiri tidak berubah sama
 * sekali (`AuthTest` dan `PlatformTwoFactorTest` sudah menjaganya), yang
 * berubah hanya siapa yang boleh memanggilnya. Pola ini mengikuti
 * `SidebarBrandingTest` — cangkangnya Vue dan tidak pernah dirender PHP, jadi
 * satu-satunya cara menjaganya dari sisi tes adalah membaca berkasnya.
 */

/** Berkas yang punya tombol keluar, beserta rute yang ditutupnya. */
$shells = [
    'js/Components/CashierTopbar.vue',
    'js/Layouts/OwnerLayout.vue',
    'js/Layouts/PlatformLayout.vue',
    'js/Pages/Cashier/CashDrawerSummary.vue',
    'js/Pages/Auth/VerifyEmail.vue',
];

test('tidak ada tombol keluar yang memanggil rute logout secara langsung', function () use ($shells) {
    foreach ($shells as $shell) {
        $source = file_get_contents(resource_path($shell));

        expect($source)->not->toContain("router.post('/logout')")
            ->and($source)->not->toContain("router.post('/platform/logout')")
            ->and($source)->not->toContain("post('/logout')");
    }
});

test('setiap cangkang meminta konfirmasi lewat useLogoutConfirm', function () use ($shells) {
    foreach ($shells as $shell) {
        $source = file_get_contents(resource_path($shell));

        expect($source)->toContain('useLogoutConfirm')
            ->and($source)->toContain('requestLogout({');
    }
});

test('dialognya dipasang sekali per cangkang, bukan sekali per tombol', function () {
    $mounts = [
        'js/Components/CashierTopbar.vue' => 1,
        'js/Layouts/OwnerLayout.vue' => 1,
        'js/Layouts/PlatformLayout.vue' => 1,
        'js/Pages/Auth/VerifyEmail.vue' => 1,
        // Halaman ini sudah merender <CashierTopbar>, yang membawa dialognya.
        // Memasang yang kedua akan menumpuk dua dialog di atas satu sama lain.
        'js/Pages/Cashier/CashDrawerSummary.vue' => 0,
    ];

    foreach ($mounts as $shell => $expected) {
        $source = file_get_contents(resource_path($shell));

        expect(substr_count($source, '<LogoutConfirmDialog />'))->toBe($expected, $shell);
    }
});

test('cache offline hanya dibersihkan oleh cangkang penyewa', function () {
    $composable = file_get_contents(resource_path('js/composables/useLogoutConfirm.js'));

    // Pembersihannya hidup di satu tempat, dan hanya berjalan setelah dijawab.
    expect($composable)->toContain('clearPrivateOfflineData')
        ->and($composable)->toContain('clearOfflineData');

    // Konsol platform dan halaman verifikasi email tidak punya cache penyewa
    // untuk dibersihkan, jadi keduanya mematikannya secara eksplisit.
    foreach (['js/Layouts/PlatformLayout.vue', 'js/Pages/Auth/VerifyEmail.vue'] as $shell) {
        expect(file_get_contents(resource_path($shell)))->toContain('clearOfflineData: false');
    }

    // Sebaliknya, cangkang kasir dan owner tidak boleh mematikannya.
    foreach (['js/Components/CashierTopbar.vue', 'js/Layouts/OwnerLayout.vue'] as $shell) {
        expect(file_get_contents(resource_path($shell)))->not->toContain('clearOfflineData: false');
    }
});

test('konsol platform tetap menutup sesinya lewat rute platform', function () {
    $layout = file_get_contents(resource_path('js/Layouts/PlatformLayout.vue'));

    expect($layout)->toContain("endpoint: '/platform/logout'");
});
