<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use App\Services\PaymentMethodRecap;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

/**
 * [BL-109] — rekap metode pembayaran menjumlahkan uang yang DIBAYARKAN, bukan
 * yang diserahkan.
 *
 * Bentuk yang diuji di sini selalu sama, dan sengaja diambil dari transaksi
 * yang benar-benar ada di produksi saat cacatnya ketahuan: belanja Rp 39.000,
 * pelanggan menyerahkan Rp 89.000, kembalian Rp 50.000. Rekap yang benar
 * menulis Rp 39.000; yang keliru menulis Rp 89.000 — dan selisihnya persis
 * uang yang sudah kembali ke tangan pelanggan.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

/**
 * Satu penjualan tunai berikut baris pembayarannya.
 *
 * `$tendered` adalah uang yang diserahkan; kembaliannya diturunkan dari
 * selisihnya, sama seperti TransactionService.
 */
function cashSale(
    Tenant $tenant,
    User $cashier,
    float $total,
    float $tendered,
    ?PaymentMethod $method = null,
    ?Carbon\Carbon $occurredAt = null,
): Transaction {
    $method ??= PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    $transaction = Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => $total,
        'change_amount' => $tendered - $total,
        'occurred_at' => $occurredAt ?? now(),
    ]);

    TransactionPayment::factory()->create([
        'transaction_id' => $transaction->id,
        'payment_method_id' => $method->id,
        'amount' => $tendered,
    ]);

    return $transaction;
}

test('dashboard recap reports cash net of change, not the money handed over', function () {
    cashSale($this->tenant, $this->owner, total: 39000, tendered: 89000);

    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('metrics.today_revenue', 39000)
            // Angka inilah yang dulu menulis 89.000 di kartu tepat di bawah
            // omzet hari ini, dan membuat cacatnya terbaca dalam satu pandangan.
            // 39000, bukan 39000.0: JSON tidak membawa `.0` pulang.
            ->where('metrics.today_by_payment_method.0.total', 39000)
            ->where('metrics.month_by_payment_method.0.total', 39000)
            ->etc()
        );
});

test('non-cash rows are left alone: they never give change back', function () {
    $qris = PaymentMethod::factory()->qris()->create(['tenant_id' => $this->tenant->id]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);

    cashSale($this->tenant, $this->owner, total: 39000, tendered: 89000, method: $cash);

    $exact = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => 25000,
        'change_amount' => 0,
        'occurred_at' => now(),
    ]);
    TransactionPayment::factory()->create([
        'transaction_id' => $exact->id,
        'payment_method_id' => $qris->id,
        'amount' => 25000,
    ]);

    $recap = collect(app(PaymentMethodRecap::class)->for(
        Transaction::where('status', Transaction::STATUS_COMPLETED),
        $this->tenant->id,
    ))->keyBy('type');

    expect($recap['cash']['total'])->toBe(39000.0)
        ->and($recap['qris_static']['total'])->toBe(25000.0);
});

test('monthly report and its CSV agree with the daily report', function () {
    cashSale($this->tenant, $this->owner, total: 39000, tendered: 89000);

    // Rekapnya ditunda ([BL-037]), jadi ia baru ada di muatan kedua.
    $this->actingAs($this->owner)
        ->get('/owner/reports/daily')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('rekap', fn (Assert $reload) => $reload
                ->where('paymentSummary.0.total', 39000)
                ->etc()
            )
            ->etc()
        );

    $this->actingAs($this->owner)
        ->get('/owner/reports/monthly')
        ->assertInertia(fn (Assert $page) => $page
            ->loadDeferredProps('rekap', fn (Assert $reload) => $reload
                ->where('paymentSummary.0.total', 39000)
                ->etc()
            )
            ->etc()
        );

    $csv = $this->actingAs($this->owner)
        ->get('/owner/reports/monthly/export')
        ->streamedContent();

    // Yang diuji bukan sekadar "39000 muncul": omzetnya juga 39.000, jadi
    // angka itu ada di blok ringkasan apa pun yang terjadi. Yang membedakan
    // adalah 89.000 tidak boleh ada di mana pun.
    expect($csv)->toContain('39000')
        ->and($csv)->not->toContain('89000');
});

test('mobile drawer summary reports the same net figure its close() already used', function () {
    $drawer = CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'opened_at' => now()->subHour(),
    ]);

    cashSale($this->tenant, $this->owner, total: 39000, tendered: 89000);

    Sanctum::actingAs($this->owner);

    $this->getJson("/api/v1/mobile/cash-drawer/{$drawer->id}/summary")
        ->assertOk()
        ->assertJsonPath('data.payment_summary.0.total', 39000);
});

test('change is split across cash methods by their share, and never touches the rest', function () {
    $cashOne = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Laci Depan']);
    $cashTwo = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id, 'name' => 'Laci Belakang']);

    // Satu penjualan, dua baris tunai: Rp 75.000 dan Rp 25.000 untuk belanja
    // Rp 60.000. Kembalian Rp 40.000 dibagi 3:1, jadi 45.000 dan 15.000.
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => 60000,
        'change_amount' => 40000,
        'occurred_at' => now(),
    ]);

    foreach ([[$cashOne, 75000], [$cashTwo, 25000]] as [$method, $amount]) {
        TransactionPayment::factory()->create([
            'transaction_id' => $transaction->id,
            'payment_method_id' => $method->id,
            'amount' => $amount,
        ]);
    }

    $recap = collect(app(PaymentMethodRecap::class)->for(
        Transaction::where('status', Transaction::STATUS_COMPLETED),
        $this->tenant->id,
    ))->keyBy('id');

    expect($recap[$cashOne->id]['total'])->toBe(45000.0)
        ->and($recap[$cashTwo->id]['total'])->toBe(15000.0)
        // Dan jumlahnya kembali ke omzet, yang adalah seluruh maksud [BL-109].
        ->and($recap->sum('total'))->toBe(60000.0);
});

test('another tenant cash never leaks into the recap', function () {
    $other = Tenant::factory()->create();
    $otherOwner = User::factory()->create(['tenant_id' => $other->id, 'role' => 'owner']);

    cashSale($this->tenant, $this->owner, total: 39000, tendered: 89000);
    cashSale($other, $otherOwner, total: 17000, tendered: 20000);

    $recap = app(PaymentMethodRecap::class)->for(
        Transaction::withoutGlobalScopes()
            ->where('tenant_id', $this->tenant->id)
            ->where('status', Transaction::STATUS_COMPLETED),
        $this->tenant->id,
    );

    expect($recap)->toHaveCount(1)
        ->and($recap[0]['total'])->toBe(39000.0);
});
