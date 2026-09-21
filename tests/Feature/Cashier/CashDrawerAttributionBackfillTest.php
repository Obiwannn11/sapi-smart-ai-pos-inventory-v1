<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashDrawerAttributionBackfill;
use App\Services\CashDrawerReconciliation;

/**
 * Backfill `cash_drawer_id` untuk baris lama ([BL-028] Tahap B langkah 2).
 *
 * Syarat sakelar baca rekonsiliasi: tanpa backfill yang benar, sesi lama
 * menghitung nol sesudah jalur bacanya pindah ke kolom itu.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->cash = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->backfill = app(CashDrawerAttributionBackfill::class);
});

function backfillDrawer(User $user, string $openedAt, ?string $closedAt = null, int $opening = 500_000): CashDrawer
{
    return CashDrawer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'opening_amount' => $opening,
        'opened_at' => $openedAt,
        'closed_at' => $closedAt,
    ]);
}

/** Baris lama: lahir tanpa `cash_drawer_id`, persis seperti sebelum langkah 1. */
function backfillSale(User $user, int $amount, string $createdAt, array $overrides = []): Transaction
{
    $transaction = Transaction::factory()->create(array_merge([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $amount,
        'created_at' => $createdAt,
    ], $overrides));

    $transaction->payments()->create([
        'payment_method_id' => test()->cash->id,
        'amount' => $amount,
    ]);

    return $transaction;
}

test('penjualan lama di dalam jendela sesi kasirnya memperoleh laci itu', function () {
    $drawer = backfillDrawer($this->cashier, now()->subHours(10), now()->subHours(2));

    $inside = backfillSale($this->cashier, 100_000, now()->subHours(5));
    $before = backfillSale($this->cashier, 100_000, now()->subHours(12));
    $after = backfillSale($this->cashier, 100_000, now()->subHour());

    expect($this->backfill->run())->toBe(1)
        ->and($inside->fresh()->cash_drawer_id)->toBe($drawer->id)
        // Di luar sesi mana pun: dibiarkan null, tidak dipaksa ke laci terdekat.
        ->and($before->fresh()->cash_drawer_id)->toBeNull()
        ->and($after->fresh()->cash_drawer_id)->toBeNull();
});

test('laci kasir lain yang tumpang-tindih tidak menerima penjualan kasir ini', function () {
    $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);

    $mine = backfillDrawer($this->cashier, now()->subHours(8));
    $theirs = backfillDrawer($other, now()->subHours(8));

    $sale = backfillSale($this->cashier, 100_000, now()->subHours(3));

    $this->backfill->run();

    expect($sale->fresh()->cash_drawer_id)->toBe($mine->id)
        ->and($theirs->transactions()->count())->toBe(0);
});

test('jendelanya memakai tanggal efektif, bukan waktu sinkronisasi', function () {
    $yesterday = backfillDrawer($this->cashier, now()->subHours(30), now()->subHours(22));
    $today = backfillDrawer($this->cashier, now()->subHours(3));

    // Terjadi kemarin, baru tersinkron sekarang.
    $offline = backfillSale($this->cashier, 100_000, now()->subHour(), [
        'occurred_at' => now()->subHours(26),
    ]);

    $this->backfill->run();

    expect($offline->fresh()->cash_drawer_id)->toBe($yesterday->id)
        ->and($offline->fresh()->cash_drawer_id)->not->toBe($today->id);
});

test('tagihan yang belum lunas, self order, dan pelunasan terlambat tidak diberi laci', function () {
    backfillDrawer($this->cashier, now()->subHours(8));

    $pending = backfillSale($this->cashier, 100_000, now()->subHours(4), ['status' => Transaction::STATUS_PENDING]);
    $selfOrder = backfillSale($this->cashier, 100_000, now()->subHours(4), ['source' => Transaction::SOURCE_SELF_ORDER]);
    $settledLate = backfillSale($this->cashier, 100_000, now()->subHours(4), ['unsettled_at' => now()->subHours(3)]);
    $voided = backfillSale($this->cashier, 100_000, now()->subHours(4), ['status' => Transaction::STATUS_VOIDED]);

    $this->backfill->run();

    expect($pending->fresh()->cash_drawer_id)->toBeNull()
        ->and($selfOrder->fresh()->cash_drawer_id)->toBeNull()
        ->and($settledLate->fresh()->cash_drawer_id)->toBeNull()
        // Transaksi batal tetap membawa jejak lacinya, sama seperti `void()`.
        ->and($voided->fresh()->cash_drawer_id)->not->toBeNull();
});

test('laci yang sudah terisi saat penjualan tidak pernah ditimpa', function () {
    $older = backfillDrawer($this->cashier, now()->subHours(8));
    $filled = backfillDrawer($this->cashier, now()->subHours(20), now()->subHours(10));

    $sale = backfillSale($this->cashier, 100_000, now()->subHours(4), ['cash_drawer_id' => $filled->id]);

    $this->backfill->run();

    expect($sale->fresh()->cash_drawer_id)->toBe($filled->id)
        ->and($sale->fresh()->cash_drawer_id)->not->toBe($older->id);
});

test('sesi seorang kasir yang tumpang-tindih: irisannya milik sesi yang lebih baru', function () {
    $earlier = backfillDrawer($this->cashier, now()->subHours(10));
    $later = backfillDrawer($this->cashier, now()->subHours(5));

    $sale = backfillSale($this->cashier, 100_000, now()->subHours(2));

    $this->backfill->run();

    // Jawaban yang sama dengan jalur offline.
    expect($sale->fresh()->cash_drawer_id)->toBe($later->id)
        ->and(CashDrawer::coveringAt($this->cashier, now()->subHours(2))->id)->toBe($later->id)
        ->and($earlier->transactions()->count())->toBe(0);
});

test('dry run menghitung tanpa menulis, dan menjalankannya ulang tidak mengubah apa pun', function () {
    backfillDrawer($this->cashier, now()->subHours(8));
    $sale = backfillSale($this->cashier, 100_000, now()->subHours(4));

    expect($this->backfill->run(dryRun: true))->toBe(1)
        ->and($sale->fresh()->cash_drawer_id)->toBeNull()
        ->and($this->backfill->run())->toBe(1)
        ->and($this->backfill->run())->toBe(0);
});

test('angka sesi lama yang dibekukan cocok dengan rekonsiliasi sesudah backfill', function () {
    // Sesi yang ditutup sebelum langkah 1: `expected_amount` dibekukan dengan
    // turunan lama, 500rb modal + 300rb tunai − 20rb kembalian.
    $drawer = backfillDrawer($this->cashier, now()->subHours(10), now()->subHours(2));
    $drawer->update(['expected_amount' => 780_000]);

    backfillSale($this->cashier, 200_000, now()->subHours(8));
    backfillSale($this->cashier, 100_000, now()->subHours(6), ['total_amount' => 80_000, 'change_amount' => 20_000]);

    $reconciliation = app(CashDrawerReconciliation::class);

    // Tanpa backfill jalur baca yang baru menghitung nol — alasan syaratnya ada.
    expect($reconciliation->for($drawer)['cash_in'])->toBe(0.0);

    $this->backfill->run();

    expect($reconciliation->for($drawer)['expected_amount'])->toBe((float) $drawer->fresh()->expected_amount);
});
