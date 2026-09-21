<?php

use App\Models\CashDrawer;
use App\Models\CashDrawerMovement;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashDrawerReconciliation;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Uang keluar-masuk laci di luar penjualan (`[BL-087]`).
 *
 * Keputusan pemilik 2026-08-21: kasir SELALU boleh mencatat; yang bergantung
 * pada ambang tenant hanyalah apakah angkanya menggerakkan `expected_amount`.
 * Hampir seluruh berkas ini menguji pemisahan itu — karena di situlah fitur
 * ini bisa diam-diam berubah jadi jalan menutupi selisih.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['cash_payout_approval_threshold' => 50000]);
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
    $this->drawer = CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opening_amount' => 500_000,
        'opened_at' => now()->subHours(3),
        'closed_at' => null,
    ]);
});

function recordMovement(array $overrides = []): array
{
    return array_merge([
        'type' => CashDrawerMovement::TYPE_PAYOUT,
        'amount' => 30_000,
        'reason' => 'Beli galon air',
    ], $overrides);
}

test('pengeluaran di bawah ambang langsung berlaku', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 30_000]))
        ->assertRedirect();

    $movement = CashDrawerMovement::withoutGlobalScopes()->first();

    expect($movement->status)->toBe(CashDrawerMovement::STATUS_APPROVED)
        // Kosong = disetujui mesin, bukan dibaca manusia. Pemilik yang
        // menelusuri selisih perlu bisa membedakannya.
        ->and($movement->reviewed_by)->toBeNull()
        ->and($movement->wasAutoApproved())->toBeTrue();

    expect(app(CashDrawerReconciliation::class)->for($this->drawer)['expected_amount'])
        ->toBe(470_000.0);
});

test('pengeluaran di atas ambang tercatat tapi belum menggerakkan angka', function () {
    // Inti fiturnya. Kalau baris ini gagal, kasir yang lacinya kurang
    // Rp 200.000 tinggal mencatat pengeluaran sebesar itu dan selisihnya nol —
    // `[BL-086]` dibatalkan dari sisi sebaliknya.
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 200_000]))
        ->assertRedirect();

    expect(CashDrawerMovement::withoutGlobalScopes()->first()->status)
        ->toBe(CashDrawerMovement::STATUS_PENDING);

    $recon = app(CashDrawerReconciliation::class)->for($this->drawer);

    expect($recon['expected_amount'])->toBe(500_000.0)
        ->and($recon['pending_payout_total'])->toBe(200_000.0)
        ->and($recon['pending_movement_count'])->toBe(1);
});

test('tepat di ambang masih otomatis', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 50_000]));

    expect(CashDrawerMovement::withoutGlobalScopes()->first()->status)
        ->toBe(CashDrawerMovement::STATUS_APPROVED);
});

test('setoran masuk tidak pernah menunggu persetujuan, berapa pun nominalnya', function () {
    // Ambangnya ada untuk menahan uang yang KELUAR. Menambah uang ke laci
    // tidak bisa dipakai menutupi kekurangan — ia justru memperbesar tuntutan
    // terhadap kasir sendiri.
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement([
            'type' => CashDrawerMovement::TYPE_DEPOSIT,
            'amount' => 5_000_000,
            'reason' => 'Tambahan uang kecil dari pemilik',
        ]));

    expect(CashDrawerMovement::withoutGlobalScopes()->first()->status)
        ->toBe(CashDrawerMovement::STATUS_APPROVED);

    expect(app(CashDrawerReconciliation::class)->for($this->drawer)['expected_amount'])
        ->toBe(5_500_000.0);
});

test('ambang nol membuat setiap pengeluaran menunggu', function () {
    // Nilainya menentukan BENTUK fiturnya, bukan cuma besarannya.
    $this->tenant->update(['cash_payout_approval_threshold' => 0]);

    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 1_000]));

    expect(CashDrawerMovement::withoutGlobalScopes()->first()->status)
        ->toBe(CashDrawerMovement::STATUS_PENDING);
});

test('alasan wajib diisi', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['reason' => '']))
        ->assertSessionHasErrors('reason');

    expect(CashDrawerMovement::withoutGlobalScopes()->count())->toBe(0);
});

test('nominal nol ditolak', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 0]))
        ->assertSessionHasErrors('amount');
});

test('pemilik menyetujui, dan barulah angkanya bergerak', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 200_000]));

    $movement = CashDrawerMovement::withoutGlobalScopes()->first();
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    $this->actingAs($owner)
        ->post("/owner/cash-drawer-movements/{$movement->id}/approve")
        ->assertRedirect();

    expect($movement->fresh()->status)->toBe(CashDrawerMovement::STATUS_APPROVED)
        ->and($movement->fresh()->reviewed_by)->toBe($owner->id)
        ->and($movement->fresh()->wasAutoApproved())->toBeFalse();

    expect(app(CashDrawerReconciliation::class)->for($this->drawer)['expected_amount'])
        ->toBe(300_000.0);
});

test('yang ditolak tidak pernah menggerakkan angka', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 200_000]));

    $movement = CashDrawerMovement::withoutGlobalScopes()->first();
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    $this->actingAs($owner)->post("/owner/cash-drawer-movements/{$movement->id}/reject");

    $recon = app(CashDrawerReconciliation::class)->for($this->drawer);

    expect($movement->fresh()->status)->toBe(CashDrawerMovement::STATUS_REJECTED)
        ->and($recon['expected_amount'])->toBe(500_000.0)
        // Dan ia berhenti dihitung sebagai yang menunggu.
        ->and($recon['pending_movement_count'])->toBe(0);
});

test('keputusan yang sudah diambil tidak bisa diputar balik dari layar ini', function () {
    // Membalik persetujuan berarti `expected_amount` sebuah sesi berubah
    // SESUDAH kasirnya menghitung uang dan menandatangani selisihnya.
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 200_000]));

    $movement = CashDrawerMovement::withoutGlobalScopes()->first();
    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    $this->actingAs($owner)->post("/owner/cash-drawer-movements/{$movement->id}/approve");
    $this->actingAs($owner)
        ->post("/owner/cash-drawer-movements/{$movement->id}/reject")
        ->assertSessionHas('error');

    expect($movement->fresh()->status)->toBe(CashDrawerMovement::STATUS_APPROVED);
});

test('kasir tidak bisa menyetujui pengeluarannya sendiri', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 200_000]));

    $movement = CashDrawerMovement::withoutGlobalScopes()->first();

    $this->actingAs($this->cashier)
        ->post("/owner/cash-drawer-movements/{$movement->id}/approve")
        ->assertStatus(403);

    expect($movement->fresh()->status)->toBe(CashDrawerMovement::STATUS_PENDING);
});

test('pemilik tenant lain tidak bisa menyentuhnya', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 200_000]));

    $movement = CashDrawerMovement::withoutGlobalScopes()->first();

    $otherOwner = User::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'role' => 'owner',
    ]);

    $this->actingAs($otherOwner)
        ->post("/owner/cash-drawer-movements/{$movement->id}/approve")
        ->assertStatus(404);

    expect($movement->fresh()->status)->toBe(CashDrawerMovement::STATUS_PENDING);
});

test('layar kasir membawa ambang dan daftar mutasinya', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement());

    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('payoutThreshold', 50000)
            ->has('movements', 1)
        );
});

test('daftar sesi kas pemilik membawa yang menunggu keputusan', function () {
    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/movements', recordMovement(['amount' => 200_000]));

    $owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);

    $this->actingAs($owner)
        ->get('/owner/cash-drawers')
        ->assertStatus(200)
        // Tidak ditunda, tidak seperti daftar sesinya: bagian yang menuntut
        // tindakan harus ada sejak cat pertama.
        ->assertInertia(fn (Assert $page) => $page->has('pendingMovements', 1));
});
