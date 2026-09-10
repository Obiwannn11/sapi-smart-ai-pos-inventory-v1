<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Identitas akun dan pintu keluarnya tinggal di dropdown topbar, bukan di kaki
 * sidebar — sama seperti dropdown akun di cangkang kasir.
 *
 * Dua lapis, mengikuti `SidebarBrandingTest`: cangkangnya Vue dan tidak pernah
 * dirender PHP, jadi tes HTTP hanya membuktikan DATANYA sampai, sementara
 * berkas Vue-nya bisa berhenti membacanya tanpa satu tes pun gagal.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
        'email' => 'pemilik@contoh.test',
    ]);
});

test('nama dan email akun ikut props tiap halaman owner', function () {
    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('auth.user.name', $this->owner->name)
            ->where('auth.user.email', 'pemilik@contoh.test')
        );
});

test('kaki sidebar owner tidak lagi memuat identitas atau tombol keluar', function () {
    $layout = file_get_contents(resource_path('js/Layouts/OwnerLayout.vue'));

    // Yang dijaga adalah tombol keluar LAMA di kaki sidebar, dikenali dari
    // aria-label persisnya — bukan sekadar kalimat "Keluar dari akun",
    // yang kini juga jadi judul dialog konfirmasi keluar, dan bukan pula
    // `roleLabel`, yang sekarang dibaca oleh dropdown.
    expect($layout)->not->toContain('User footer')
        ->and($layout)->not->toContain('aria-label="Keluar dari akun"');
});

test('topbar owner membuka menu akun berisi tautan kasir dan tombol keluar', function () {
    $layout = file_get_contents(resource_path('js/Layouts/OwnerLayout.vue'));

    // Tautan kasir hanya boleh hidup di dalam dropdown, bukan sebagai tombol
    // telanjang di topbar seperti sebelumnya.
    expect(substr_count($layout, 'href="/cashier/pos"'))->toBe(1)
        ->and($layout)->toContain('aria-label="Menu akun"')
        ->and($layout)->toContain('{{ userName }}')
        ->and($layout)->toContain('{{ userEmail }}')
        ->and($layout)->toContain('Buka Kasir')
        ->and($layout)->toContain('@click="logout"');
});
