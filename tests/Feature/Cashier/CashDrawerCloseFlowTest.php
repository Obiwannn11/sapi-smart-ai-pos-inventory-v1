<?php

use App\Models\CashDrawer;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Alur tutup kas sebagai halaman tersendiri (`[BL-086]` butir 2).
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
});

test('kasir dengan sesi terbuka mendapat halaman tutup kas beserta rekonsiliasinya', function () {
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opening_amount' => 500_000,
        'opened_at' => now()->subHours(6),
        'closed_at' => null,
    ]);

    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer/close')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Cashier/CashDrawerClose')
            ->where('openDrawer.opening_amount', '500000.00')
            // Di halaman INI angkanya memang dikirim: kasir datang ke sini
            // untuk mempertanggungjawabkan lacinya, bukan untuk mengintipnya.
            ->has('reconciliation.expected_amount')
        );
});

test('tanpa sesi terbuka, halaman tutup kas mengembalikan kasir ke halaman kas', function () {
    // Bukan 404. Tidak ada yang bisa ditutup, dan yang dibutuhkan kasir di
    // keadaan itu adalah formulir membuka kas — yang ada di halaman kas.
    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer/close')
        ->assertRedirect(route('cashier.cash-drawer.index'));
});

test('owner tidak mengelola kas, jadi ia diarahkan ke POS', function () {
    $owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    $this->actingAs($owner)
        ->get('/cashier/cash-drawer/close')
        ->assertRedirect(route('cashier.pos'));
});

test('sesi kasir lain tidak ikut terbawa ke halaman tutup kas', function () {
    $otherCashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $otherCashier->id,
        'closed_at' => null,
    ]);

    // Laci milik orang lain bukan laci yang bisa ditutup di sini — dan itu
    // pemisahan yang sama yang dijaga `[BL-028]` di sisi perhitungannya.
    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer/close')
        ->assertRedirect(route('cashier.cash-drawer.index'));
});

test('kolom hitungan terkunci setelah ringkasan dibuka, dan membukanya mengosongkan angkanya', function () {
    // Lubang yang ditutup: lihat selisih → Kembali → samakan angkanya. Kalau
    // kolomnya tetap bisa disunting sesudah ringkasan terbaca, penghitungan
    // butanya batal dalam dua klik.
    $close = file_get_contents(resource_path('js/Pages/Cashier/CashDrawerClose.vue'));

    expect($close)->toContain('const hasSeenSummary = ref(false)')
        ->and($close)->toContain('hasSeenSummary.value = true')
        ->and($close)->toContain('v-if="!amountLocked"')
        // "Hitung ulang" harus MENGOSONGKAN, bukan sekadar membuka kunci.
        ->and($close)->toMatch('~const recount = \(\) => \{\s*closingAmount\.value = 0;~');
});
