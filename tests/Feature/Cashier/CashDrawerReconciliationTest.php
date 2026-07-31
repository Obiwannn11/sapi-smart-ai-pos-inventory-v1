<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashDrawerReconciliation;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->cash = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->qris = PaymentMethod::factory()->qris()->create(['tenant_id' => $this->tenant->id]);

    $this->service = app(CashDrawerReconciliation::class);
});

/**
 * Penjualan milik $user, dibayar dengan $method, opsional ber-occurred_at
 * (mensimulasikan penjualan offline yang tersinkron belakangan).
 */
function sale(User $user, PaymentMethod $method, int $amount, int $change = 0, ?string $occurredAt = null): Transaction
{
    $transaction = Transaction::factory()->create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $amount - $change,
        'change_amount' => $change,
        'occurred_at' => $occurredAt,
    ]);

    $transaction->payments()->create([
        'payment_method_id' => $method->id,
        'amount' => $amount,
    ]);

    return $transaction;
}

function openDrawerFor(User $user, int $opening = 500_000): CashDrawer
{
    return CashDrawer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'opening_amount' => $opening,
        'opened_at' => now()->subHours(4),
        'closed_at' => null,
    ]);
}

test('expected amount includes cash sales, not just the opening float', function () {
    $drawer = openDrawerFor($this->cashier, 500_000);

    sale($this->cashier, $this->cash, 200_000);
    sale($this->cashier, $this->cash, 100_000, change: 20_000);

    $result = $this->service->for($drawer);

    expect($result['cash_in'])->toBe(300_000.0)
        ->and($result['change_out'])->toBe(20_000.0)
        // 500rb modal + 300rb tunai masuk − 20rb kembalian
        ->and($result['expected_amount'])->toBe(780_000.0);
});

test('non cash payments stay out of the drawer but are still reported', function () {
    $drawer = openDrawerFor($this->cashier, 500_000);

    sale($this->cashier, $this->cash, 150_000);
    sale($this->cashier, $this->qris, 400_000);

    $result = $this->service->for($drawer);

    expect($result['cash_in'])->toBe(150_000.0)
        ->and($result['non_cash_in'])->toBe(400_000.0)
        ->and($result['expected_amount'])->toBe(650_000.0)
        ->and($result['transaction_count'])->toBe(2);
});

test('overlapping cashiers each reconcile only their own drawer', function () {
    $other = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $drawer = openDrawerFor($this->cashier, 500_000);
    $otherDrawer = openDrawerFor($other, 300_000);

    sale($this->cashier, $this->cash, 200_000);
    sale($other, $this->cash, 1_000_000);

    // Versi lama menyaring tenant_id saja, sehingga KEDUA laci mengaku
    // memegang 1.2 juta yang sama.
    expect($this->service->for($drawer)['expected_amount'])->toBe(700_000.0)
        ->and($this->service->for($otherDrawer)['expected_amount'])->toBe(1_300_000.0);
});

test('offline sale that happened before the shift is not counted into it', function () {
    $drawer = openDrawerFor($this->cashier, 500_000);

    // Terjadi kemarin, baru tersinkron sekarang: created_at ada di dalam sesi
    // ini, tapi uangnya masuk laci shift kemarin.
    sale($this->cashier, $this->cash, 250_000, occurredAt: now()->subDay()->toDateTimeString());
    sale($this->cashier, $this->cash, 100_000);

    $result = $this->service->for($drawer);

    expect($result['cash_in'])->toBe(100_000.0)
        ->and($result['expected_amount'])->toBe(600_000.0);
});

test('pending and voided transactions never reach the drawer', function () {
    $drawer = openDrawerFor($this->cashier, 500_000);

    sale($this->cashier, $this->cash, 100_000);

    $pending = sale($this->cashier, $this->cash, 999_000);
    $pending->update(['status' => Transaction::STATUS_PENDING]);

    $voided = sale($this->cashier, $this->cash, 888_000);
    $voided->update(['status' => Transaction::STATUS_VOIDED]);

    $result = $this->service->for($drawer);

    expect($result['expected_amount'])->toBe(600_000.0)
        ->and($result['transaction_count'])->toBe(1);
});

test('cash drawer page ships the reconciliation the summary screen renders', function () {
    openDrawerFor($this->cashier, 500_000);

    sale($this->cashier, $this->cash, 200_000, change: 20_000);
    sale($this->cashier, $this->qris, 75_000);

    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer')
        ->assertInertia(fn ($page) => $page
            ->component('Cashier/CashDrawer')
            // Angka bulat kehilangan ".0"-nya lewat json_encode, jadi
            // dibandingkan sebagai integer di sini.
            ->where('reconciliation.opening_amount', 500_000)
            ->where('reconciliation.cash_in', 200_000)
            ->where('reconciliation.change_out', 20_000)
            ->where('reconciliation.non_cash_in', 75_000)
            ->where('reconciliation.expected_amount', 680_000)
            ->where('reconciliation.transaction_count', 2)
        );
});

test('cash drawer page sends no reconciliation when there is no open session', function () {
    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer')
        ->assertInertia(fn ($page) => $page->where('reconciliation', null));
});

test('closing the drawer stores the reconciled expected amount', function () {
    openDrawerFor($this->cashier, 500_000);

    sale($this->cashier, $this->cash, 200_000, change: 20_000);

    $this->actingAs($this->cashier)
        ->post('/cashier/cash-drawer/close', ['closing_amount' => 680_000])
        ->assertSessionHas('success');

    $drawer = CashDrawer::where('user_id', $this->cashier->id)->first();

    expect((float) $drawer->expected_amount)->toBe(680_000.0)
        ->and((float) $drawer->difference)->toBe(0.0);
});
