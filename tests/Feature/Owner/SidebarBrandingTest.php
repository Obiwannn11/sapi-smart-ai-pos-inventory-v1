<?php

use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Kepala sidebar owner menyebut nama TOKO, bukan nama produk (`[BL-084]`).
 *
 * Dua lapis, karena tidak ada satu pun lapis yang cukup sendirian: shell-nya
 * Vue dan tidak pernah dirender PHP, jadi tes HTTP hanya bisa membuktikan
 * DATANYA sampai — sementara berkas Vue-nya bisa saja berhenti membacanya
 * tanpa satu tes pun gagal. Karena itu yang kedua memeriksa berkasnya.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['name' => 'Kopi Nusantara']);
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

test('nama toko ikut props tiap halaman owner', function () {
    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->where('auth.tenant.name', 'Kopi Nusantara'));
});

test('sidebar owner tidak lagi menuliskan mereknya sendiri', function () {
    $layout = file_get_contents(resource_path('js/Layouts/OwnerLayout.vue'));

    // Cadangan `'SAPI POS'` di dalam <script> tetap sah — yang dilarang adalah
    // merek yang dipajang sebagai teks tetap di dalam template.
    expect($layout)->not->toContain('>SAPI<')
        ->and($layout)->toContain('auth.tenant?.name')
        ->and($layout)->toContain('{{ tenantName }}');
});
