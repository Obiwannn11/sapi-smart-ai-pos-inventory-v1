<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;

/**
 * Laci mana yang menerima uang sebuah penjualan ([BL-028] Tahap B langkah 1).
 *
 * Yang diuji di sini adalah PENGISIAN kolomnya saat penjualan dicatat. Angka
 * rekonsiliasi yang membacanya dijaga `CashDrawerReconciliationTest`, dan
 * pengisian baris lama dijaga `CashDrawerAttributionBackfillTest`.
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();

    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => 25_000, 'stock' => 500,
    ]);

    $this->cash = PaymentMethod::factory()->create(['tenant_id' => $this->tenant->id]);
});

function attributionDrawer(User $user, ?string $openedAt = null, ?string $closedAt = null): CashDrawer
{
    return CashDrawer::factory()->create([
        'tenant_id' => $user->tenant_id,
        'user_id' => $user->id,
        'opening_amount' => 500_000,
        'opened_at' => $openedAt ?? now()->subHours(4),
        'closed_at' => $closedAt,
    ]);
}

/** Satu penjualan lewat jalur checkout sebenarnya, sebagai $user. */
function attributionSale(User $user, bool $openBill = false): Transaction
{
    actingAs($user);

    return app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => test()->variant->id,
            'variant_name' => 'Variant',
            'qty' => 2,
            'unit_price' => 25_000,
            'modifiers' => [],
        ]],
        'payments' => $openBill ? [] : [[
            'payment_method_id' => test()->cash->id,
            'amount' => 60_000,
        ]],
        'is_open_bill' => $openBill,
    ]);
}

/** Payload penjualan offline yang TERJADI pada $occurredAt. */
function attributionOfflinePayload(string $occurredAt): array
{
    return [
        'client_uuid' => (string) Str::uuid(),
        'occurred_at' => $occurredAt,
        'device_id' => 'till-01',
        'items' => [[
            'variant_id' => test()->variant->id,
            'variant_name' => 'Variant',
            'qty' => 1,
            'unit_price' => 25_000,
            'modifiers' => [],
            'notes' => null,
        ]],
        'payments' => [[
            'payment_method_id' => test()->cash->id,
            'amount' => 25_000,
        ]],
    ];
}

// --- Penjualan langsung -----------------------------------------------------

test('penjualan tunai memegang laci yang sedang terbuka milik kasirnya', function () {
    $drawer = attributionDrawer($this->cashier);

    $sale = attributionSale($this->cashier);

    expect($sale->fresh()->cash_drawer_id)->toBe($drawer->id);
});

test('penjualan tanpa sesi kas terbuka tercatat tanpa laci, bukan gagal', function () {
    // Pemilik berjualan di POS tanpa pernah membuka sesi kas — keadaan nyata,
    // bukan kegagalan.
    $sale = attributionSale($this->owner);

    expect($sale->status)->toBe(Transaction::STATUS_COMPLETED)
        ->and($sale->fresh()->cash_drawer_id)->toBeNull();
});

test('penjualan tidak jatuh ke laci kasir lain yang kebetulan sedang terbuka', function () {
    $other = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);
    attributionDrawer($other);

    $sale = attributionSale($this->cashier);

    expect($sale->fresh()->cash_drawer_id)->toBeNull();
});

// --- Tagihan terbuka --------------------------------------------------------

test('tagihan terbuka belum memegang laci mana pun saat dibuat', function () {
    attributionDrawer($this->cashier);

    $bill = attributionSale($this->cashier, openBill: true);

    expect($bill->status)->toBe(Transaction::STATUS_PENDING)
        ->and($bill->fresh()->cash_drawer_id)->toBeNull();
});

test('tagihan yang dibuat kasir A lalu dilunasi kasir B masuk laci B', function () {
    $drawerA = attributionDrawer($this->cashier);
    $bill = attributionSale($this->cashier, openBill: true);

    // Shift berganti: A menutup lacinya, B membuka lacinya sendiri.
    $drawerA->update(['closed_at' => now()]);

    $cashierB = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);
    $drawerB = attributionDrawer($cashierB, openedAt: now()->toDateTimeString());

    actingAs($cashierB);
    app(TransactionService::class)->payOpenBill($bill, [[
        'payment_method_id' => $this->cash->id,
        'amount' => 60_000,
    ]], $cashierB);

    // Inilah aturan [BL-028] yang selama ini hanya tertulis: uangnya milik
    // laci yang MELUNASI. Turunan lama (`user_id` pembuat + tanggal tagihan
    // dibuka) akan menjawab laci A.
    expect($bill->fresh()->cash_drawer_id)->toBe($drawerB->id)
        ->and($bill->fresh()->cash_drawer_id)->not->toBe($drawerA->id);
});

test('pelunasan terlambat oleh pemilik tidak jatuh ke laci mana pun', function () {
    attributionDrawer($this->cashier);
    $bill = attributionSale($this->cashier, openBill: true);

    $bill->forceFill([
        'status' => Transaction::STATUS_UNSETTLED,
        'unsettled_at' => now(),
    ])->save();

    app(TransactionService::class)->paySettledLateBill($bill->fresh(), [[
        'payment_method_id' => $this->cash->id,
        'amount' => 60_000,
    ]], $this->owner);

    // Sesudah 24 jam tidak ada laci yang menerimanya ([BL-031]) — dan sekarang
    // kolomnya menyatakan itu alih-alih membiarkannya disimpulkan.
    expect($bill->fresh()->status)->toBe(Transaction::STATUS_COMPLETED)
        ->and($bill->fresh()->cash_drawer_id)->toBeNull();
});

// --- Penjualan offline ------------------------------------------------------

test('penjualan offline masuk laci yang terbuka saat ia TERJADI, bukan saat ia tersinkron', function () {
    // Sesi kemarin, sudah ditutup. Penjualannya terjadi di dalamnya.
    $yesterday = attributionDrawer(
        $this->cashier,
        openedAt: now()->subHours(30)->toDateTimeString(),
        closedAt: now()->subHours(22)->toDateTimeString(),
    );

    // Sesi hari ini sedang terbuka saat payload-nya baru sampai.
    $today = attributionDrawer($this->cashier, openedAt: now()->subHours(2)->toDateTimeString());

    $sale = app(TransactionService::class)->commitOffline(
        attributionOfflinePayload(now()->subHours(26)->toIso8601String()),
        $this->cashier,
    );

    expect($sale->cash_drawer_id)->toBe($yesterday->id)
        ->and($sale->cash_drawer_id)->not->toBe($today->id);
});

test('penjualan offline di luar sesi mana pun tercatat tanpa laci', function () {
    attributionDrawer($this->cashier, openedAt: now()->subHours(2)->toDateTimeString());

    $sale = app(TransactionService::class)->commitOffline(
        attributionOfflinePayload(now()->subHours(10)->toIso8601String()),
        $this->cashier,
    );

    expect($sale->cash_drawer_id)->toBeNull();
});

// --- Jalur yang memang tidak punya laci ------------------------------------

test('self order yang lunas lewat webhook tidak jatuh ke laci mana pun', function () {
    // Laci kasir terbuka, dan justru itu intinya: pembayarannya lewat Xendit,
    // tidak ada uang yang masuk laci siapa pun.
    attributionDrawer($this->cashier);

    $order = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'source' => Transaction::SOURCE_SELF_ORDER,
        'status' => Transaction::STATUS_PENDING,
        'fulfillment_status' => null,
        'total_amount' => 25_000,
    ]);

    $order->items()->create([
        'product_variant_id' => $this->variant->id,
        'variant_name' => 'Variant',
        'qty' => 1,
        'unit_price' => 25_000,
        'subtotal' => 25_000,
    ]);

    app(TransactionService::class)->confirmSelfOrderPayment($order);

    expect($order->fresh()->status)->toBe(Transaction::STATUS_COMPLETED)
        ->and($order->fresh()->cash_drawer_id)->toBeNull();
});

test('penjualan lama tetap null — migrasi ini tidak melakukan backfill', function () {
    $drawer = attributionDrawer($this->cashier);

    // Mewakili 9.204 baris yang lahir sebelum kolomnya ada.
    $old = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    expect($old->fresh()->cash_drawer_id)->toBeNull()
        ->and($drawer->transactions()->count())->toBe(0);
});
