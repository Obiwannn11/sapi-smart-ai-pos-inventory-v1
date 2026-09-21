<?php

use App\Models\CashDrawer;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashDrawerReconciliation;
use Database\Seeders\CashDrawerScenarioSeeder;
use Database\Seeders\DatabaseSeeder;

/**
 * Tiga pemicu `[BL-028]` Tahap B di tenant demo `owner@sapi.test` — kasir kedua,
 * dua sesi sehari, dan sesi yang ditutup paksa — dengan angka laci yang
 * seharusnya ditampilkan untuk masing-masing.
 */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(CashDrawerScenarioSeeder::class);

    $this->tenant = Tenant::where('slug', 'kopi-nusantara')->firstOrFail();
    $this->owner = User::where('email', 'owner@sapi.test')->firstOrFail();
    [$this->kasirA, $this->kasirB] = User::where('tenant_id', $this->tenant->id)
        ->where('role', 'cashier')->orderBy('id')->get()->all();

    $this->reconciliation = app(CashDrawerReconciliation::class);
});

function scenarioDrawer(string $label): CashDrawer
{
    return CashDrawer::where('notes', 'like', CashDrawerScenarioSeeder::MARKER.' '.$label.'%')->firstOrFail();
}

test('dua kasir dengan sesi bersamaan hanya menghitung uangnya sendiri', function () {
    $drawerA = scenarioDrawer('A: laci bersamaan');
    $morningB = scenarioDrawer('B: shift pagi');

    expect($drawerA->user_id)->toBe($this->kasirA->id)
        ->and($morningB->user_id)->toBe($this->kasirB->id)
        // 300rb modal + 200rb tunai − 50rb kembalian; 80rb milik B tidak ikut.
        ->and($this->reconciliation->for($drawerA)['expected_amount'])->toBe(450_000.0)
        // 200rb modal + 80rb tunai; QRIS 45rb dilaporkan tapi tidak masuk laci.
        ->and($this->reconciliation->for($morningB)['expected_amount'])->toBe(280_000.0)
        ->and($this->reconciliation->for($morningB)['non_cash_in'])->toBe(45_000.0);
});

test('satu kasir dengan dua sesi sehari tidak mencampur isi kedua lacinya', function () {
    $morningB = scenarioDrawer('B: shift pagi');
    $eveningB = scenarioDrawer('B: shift sore');

    expect($morningB->opened_at->isSameDay($eveningB->opened_at))->toBeTrue()
        ->and($this->reconciliation->for($morningB)['transaction_count'])->toBe(2)
        ->and($this->reconciliation->for($eveningB)['transaction_count'])->toBe(2);
});

test('tagihan yang dibuat kasir A lalu dilunasi kasir B masuk laci sore B, bukan laci A', function () {
    $drawerA = scenarioDrawer('A: laci bersamaan');
    $eveningB = scenarioDrawer('B: shift sore');

    $bill = Transaction::withoutGlobalScopes()
        ->where('user_id', $this->kasirA->id)
        ->where('cash_drawer_id', $eveningB->id)
        ->firstOrFail();

    // Tanggal efektifnya jatuh di jendela laci A — turunan lama akan
    // menaruhnya di sana.
    expect($bill->created_at->between($drawerA->opened_at, $drawerA->closed_at))->toBeTrue()
        // 250rb modal + 120rb + 100rb tunai − 5rb kembalian.
        ->and($this->reconciliation->for($eveningB)['expected_amount'])->toBe(465_000.0)
        ->and((float) $eveningB->expected_amount)->toBe(465_000.0)
        ->and((float) $eveningB->difference)->toBe(-10_000.0);
});

test('sesi yang ditutup paksa punya angka seharusnya tapi tidak pernah mengaku dihitung', function () {
    $forced = scenarioDrawer('B: ditutup paksa');

    expect($forced->closed_by_system)->toBeTrue()
        ->and($forced->closing_amount)->toBeNull()
        ->and($forced->difference)->toBeNull()
        // 150rb modal + 60rb tunai. Penjualan B sesudah sesi ini tertutup
        // tidak ikut.
        ->and((float) $forced->expected_amount)->toBe(210_000.0)
        ->and($this->reconciliation->for($forced)['expected_amount'])->toBe(210_000.0);

    $orphan = Transaction::withoutGlobalScopes()
        ->where('user_id', $this->kasirB->id)
        ->where('created_at', '>', $forced->closed_at)
        ->sole();

    expect($orphan->cash_drawer_id)->toBeNull();
});

test('owner melihat rekap sesi sore dengan pelunasan tagihan kasir A di dalamnya', function () {
    $eveningB = scenarioDrawer('B: shift sore');

    $this->actingAs($this->owner)
        ->get(route('cashier.cash-drawer.summary', $eveningB))
        ->assertInertia(fn ($page) => $page
            ->component('Cashier/CashDrawerSummary')
            ->where('transactionCount', 2)
        );
});

test('menjalankan seeder skenario dua kali tidak menggandakan apa pun', function () {
    $drawers = CashDrawer::where('tenant_id', $this->tenant->id)->count();
    $transactions = Transaction::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count();

    $this->seed(CashDrawerScenarioSeeder::class);

    expect(CashDrawer::where('tenant_id', $this->tenant->id)->count())->toBe($drawers)
        ->and(Transaction::withoutGlobalScopes()->where('tenant_id', $this->tenant->id)->count())->toBe($transactions);
});
