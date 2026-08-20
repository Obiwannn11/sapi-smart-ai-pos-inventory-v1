<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\CashDrawerReconciliation;
use App\Services\OpenBillExpiryService;
use App\Services\TransactionService;

use function Pest\Laravel\actingAs;

/**
 * Umur tagihan terbuka: 24 jam, lalu kas negatif ([BL-031]).
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'owner']);
    $this->cashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 25000, 'stock' => 100,
    ]);
    $this->cash = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);

    $this->expiry = app(OpenBillExpiryService::class);
});

/**
 * Tagihan terbuka lewat jalur sebenarnya (checkout open bill), supaya stok
 * benar-benar berkurang — itulah yang membuat pemulihan stok bisa diuji.
 */
function openBill(?string $occurredAt = null): Transaction
{
    actingAs(test()->cashier);

    $bill = app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => test()->variant->id,
            'variant_name' => 'Variant',
            'qty' => 2,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => [],
        'is_open_bill' => true,
    ]);

    if ($occurredAt) {
        $bill->forceFill(['occurred_at' => $occurredAt])->save();
    }

    return $bill->fresh();
}

// --- Sapuan terjadwal ---------------------------------------------------

test('tagihan lewat 24 jam jadi kas negatif, dan stoknya TIDAK dipulihkan', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $stockAfterBill = $this->variant->fresh()->stock;

    expect($this->expiry->expire())->toBe(1);

    expect($bill->fresh()->status)->toBe(Transaction::STATUS_UNSETTLED)
        ->and($bill->fresh()->unsettled_at)->not->toBeNull()
        // Barangnya sudah dibawa pelanggan — memulihkan stok di sini akan
        // membuat pembukuan bersih tapi bohong.
        ->and($this->variant->fresh()->stock)->toBe($stockAfterBill);
});

test('tagihan yang belum 24 jam tidak disentuh', function () {
    $bill = openBill(occurredAt: now()->subHours(23)->toDateTimeString());

    expect($this->expiry->expire())->toBe(0)
        ->and($bill->fresh()->status)->toBe(Transaction::STATUS_PENDING);
});

test('umurnya dihitung dari occurred_at, bukan created_at', function () {
    // Penjualan offline kemarin yang baru tersinkron barusan: created_at hari
    // ini, occurred_at 30 jam lalu. Memakai created_at akan memberinya umur
    // yang baru lahir dan tagihan itu hidup selamanya.
    $bill = openBill(occurredAt: now()->subHours(30)->toDateTimeString());
    expect($bill->created_at->isToday())->toBeTrue();

    expect($this->expiry->expire())->toBe(1)
        ->and($bill->fresh()->status)->toBe(Transaction::STATUS_UNSETTLED);
});

test('self-order pending tidak ikut tersapu', function () {
    // Stoknya belum dikurangi dan ia punya jalur kedaluwarsanya sendiri;
    // menyapunya akan mencatat kas negatif atas barang yang tidak pernah keluar.
    $selfOrder = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'status' => Transaction::STATUS_PENDING,
        'source' => Transaction::SOURCE_SELF_ORDER,
        'occurred_at' => now()->subDays(3),
    ]);

    expect($this->expiry->expire())->toBe(0)
        ->and($selfOrder->fresh()->status)->toBe(Transaction::STATUS_PENDING);
});

test('sapuan melepaskan tagihan dari papan dapur', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $bill->forceFill(['fulfillment_status' => Transaction::FULFILLMENT_WAITING])->save();

    $this->expiry->expire();

    expect($bill->fresh()->fulfillment_status)->toBeNull();
});

test('dry-run menghitung tanpa memindahkan', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());

    expect($this->expiry->expire(dryRun: true))->toBe(1)
        ->and($bill->fresh()->status)->toBe(Transaction::STATUS_PENDING);
});

test('perintah terjadwal open-bills:expire memindahkannya', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());

    $this->artisan('open-bills:expire')->assertSuccessful();

    expect($bill->fresh()->status)->toBe(Transaction::STATUS_UNSETTLED);
});

// --- Panel tagihan kasir ------------------------------------------------

test('tagihan lewat umur hilang dari panel kasir walau sapuan belum berjalan', function () {
    openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $alive = openBill(occurredAt: now()->subHours(2)->toDateTimeString());

    // POS menolak kasir yang belum membuka kas; panelnya baru terlihat sesudah itu.
    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opened_at' => now()->subHours(4),
        'closed_at' => null,
    ]);

    actingAs($this->cashier)
        ->get(route('cashier.pos'))
        ->assertInertia(fn ($page) => $page
            ->has('cashier.openBills', 1)
            ->where('cashier.openBills.0.id', $alive->id)
        );
});

// --- Siapa yang boleh membereskan --------------------------------------

test('kasir tidak bisa melunasi tagihan yang sudah jadi kas negatif', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $this->expiry->expire();

    actingAs($this->cashier)
        ->post(route('cashier.transactions.pay', $bill), [
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 50000]],
        ])
        ->assertSessionHas('error');

    expect($bill->fresh()->status)->toBe(Transaction::STATUS_UNSETTLED);
});

test('kasir ditolak di rute pembereskan milik pemilik', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $this->expiry->expire();

    actingAs($this->cashier)
        ->post(route('owner.transactions.write-off', $bill))
        ->assertForbidden();
});

test('pemilik menerima pelunasan terlambat dan kas negatifnya tertutup', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $this->expiry->expire();

    actingAs($this->owner)
        ->post(route('owner.transactions.settle-late', $bill), [
            'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 50000]],
        ])
        ->assertSessionHas('success');

    expect($bill->fresh()->status)->toBe(Transaction::STATUS_COMPLETED)
        ->and($bill->fresh()->payments)->toHaveCount(1);
});

test('pemilik menghapuskan tagihan dan DI SINI stok kembali', function () {
    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $stockAfterBill = $this->variant->fresh()->stock;
    $this->expiry->expire();

    actingAs($this->owner)
        ->post(route('owner.transactions.write-off', $bill))
        ->assertSessionHas('success');

    expect($bill->fresh()->status)->toBe(Transaction::STATUS_VOIDED)
        ->and($this->variant->fresh()->stock)->toBe($stockAfterBill + 2);
});

// --- Rekonsiliasi kas ---------------------------------------------------

test('kas negatif muncul di rekonsiliasi tanpa mengubah expected_amount', function () {
    $drawer = CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opening_amount' => 500_000,
        'opened_at' => now()->subHours(4),
        'closed_at' => null,
    ]);

    $bill = openBill(occurredAt: now()->subHours(25)->toDateTimeString());
    $this->expiry->expire();

    $result = app(CashDrawerReconciliation::class)->for($drawer);

    expect($result['unsettled_count'])->toBe(1)
        ->and($result['unsettled_cash'])->toBe((float) $bill->total_amount)
        // Uang ini tidak pernah masuk laci; menuduh kasir kurang sebesar itu
        // adalah cacat yang sama yang [BL-028] baru saja perbaiki.
        ->and($result['expected_amount'])->toBe(500_000.0);
});
