<?php

use App\Models\CashDrawer;
use App\Models\CashDrawerReveal;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Jejak pengungkapan angka "seharusnya di laci" (`[BL-090]`).
 *
 * Pemilik memilih **mencatat, bukan mencegah**. Yang diuji di sini karena itu
 * bukan "apakah kasir ditolak" — ia tidak ditolak — melainkan apakah
 * pembukaannya benar-benar meninggalkan baris yang bisa dibaca pemilik.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
});

function revealTrailDrawer(User $user, Tenant $tenant): CashDrawer
{
    return CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'opening_amount' => 500_000,
        'opened_at' => now()->subHours(3),
        'closed_at' => null,
    ]);
}

test('membuka angka seharusnya meninggalkan satu baris pada sesi yang sedang berjalan', function () {
    $drawer = revealTrailDrawer($this->cashier, $this->tenant);

    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/reveal')
        ->assertRedirect();

    $reveal = CashDrawerReveal::withoutGlobalScopes()->first();

    expect($reveal)->not->toBeNull()
        ->and($reveal->cash_drawer_id)->toBe($drawer->id)
        ->and($reveal->user_id)->toBe($this->cashier->id)
        ->and($reveal->tenant_id)->toBe($this->tenant->id)
        ->and($reveal->revealed_at)->not->toBeNull();
});

test('tanpa sesi terbuka tidak ada yang dicatat, dan itu bukan galat', function () {
    // Permintaan yang datang terlambat — sesinya baru saja ditutup di
    // perangkat lain. Bukan kesalahan pengguna, jadi bukan 4xx.
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/reveal')
        ->assertRedirect();

    expect(CashDrawerReveal::withoutGlobalScopes()->count())->toBe(0);
});

test('pembukaan berulang tercatat berulang', function () {
    // Klien menahan diri satu kali per pemuatan halaman, tapi servernya tidak
    // menganggap pengungkapan kedua sebagai duplikat: memuat ulang halaman
    // lalu membukanya lagi adalah peristiwa lain di jam yang lain.
    revealTrailDrawer($this->cashier, $this->tenant);

    $this->actingAs($this->cashier)->post('/cashier/cash-drawer/reveal');
    $this->actingAs($this->cashier)->post('/cashier/cash-drawer/reveal');

    expect(CashDrawerReveal::withoutGlobalScopes()->count())->toBe(2);
});

test('jejaknya menempel pada laci kasir yang membukanya, bukan laci kasir lain', function () {
    $otherCashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $otherDrawer = revealTrailDrawer($otherCashier, $this->tenant);
    $ownDrawer = revealTrailDrawer($this->cashier, $this->tenant);

    $this->actingAs($this->cashier)->post('/cashier/cash-drawer/reveal');

    $reveal = CashDrawerReveal::withoutGlobalScopes()->first();

    expect($reveal->cash_drawer_id)->toBe($ownDrawer->id)
        ->and($reveal->cash_drawer_id)->not->toBe($otherDrawer->id);
});

test('daftar sesi kas pemilik membawa hitungan dan waktu pembukaan pertama', function () {
    $drawer = revealTrailDrawer($this->cashier, $this->tenant);

    CashDrawerReveal::create([
        'tenant_id' => $this->tenant->id,
        'cash_drawer_id' => $drawer->id,
        'user_id' => $this->cashier->id,
        'revealed_at' => now()->subHours(2),
    ]);
    CashDrawerReveal::create([
        'tenant_id' => $this->tenant->id,
        'cash_drawer_id' => $drawer->id,
        'user_id' => $this->cashier->id,
        'revealed_at' => now()->subMinutes(10),
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    // Daftarnya ditunda (`[BL-037]`), jadi angkanya baru ada pada permintaan
    // lanjutan — sama seperti yang dilakukan peramban.
    $this->actingAs($owner)
        ->get('/owner/cash-drawers')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page->loadDeferredProps(
            fn (Assert $reload) => $reload
                ->where('cashDrawers.data.0.reveals_count', 2)
                // Yang dipajang waktu PERTAMA: "kapan ia pertama tahu"
                // menjawab apakah hitungannya sudah tercemar; pembukaan
                // terakhir tidak.
                ->has('cashDrawers.data.0.reveals_min_revealed_at')
        ));
});
