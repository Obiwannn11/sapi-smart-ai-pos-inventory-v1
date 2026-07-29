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
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create(['kitchen_queue_enabled' => true]);

    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $product = Product::factory()->create(['tenant_id' => $this->tenant->id]);
    $this->variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 20000,
        'stock' => 50,
    ]);

    $this->cash = PaymentMethod::factory()->create([
        'tenant_id' => $this->tenant->id,
        'type' => 'cash',
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'closed_at' => null,
    ]);
});

/**
 * Payload checkout POS minimal.
 */
function queueCheckoutPayload(array $overrides = []): array
{
    return array_merge([
        'items' => [[
            'variant_id' => test()->variant->id,
            'variant_name' => 'Kopi Susu - Reguler',
            'qty' => 1,
            'unit_price' => 20000,
            'modifiers' => [],
            'notes' => null,
        ]],
        'payments' => [[
            'payment_method_id' => test()->cash->id,
            'amount' => 20000,
        ]],
        'client_uuid' => (string) Str::uuid(),
    ], $overrides);
}

/**
 * Open bill: `payments` DIHILANGKAN, bukan dikosongkan — aturan validasinya
 * `nullable|array|min:1`, sehingga array kosong ditolak sebelum sampai service.
 */
function openBillPayload(): array
{
    $payload = queueCheckoutPayload(['is_open_bill' => true]);
    unset($payload['payments']);

    return $payload;
}

// ── Gerbang & isolasi ──────────────────────────────────────────────────────

test('papan ditolak untuk tenant yang mode antriannya mati', function () {
    $this->tenant->update(['kitchen_queue_enabled' => false]);

    actingAs($this->cashier);

    get('/cashier/queue')->assertStatus(403);
});

test('papan terbuka saat mode antrian hidup', function () {
    actingAs($this->cashier);

    get('/cashier/queue')
        ->assertStatus(200)
        ->assertInertia(fn ($page) => $page->component('Cashier/Queue')->has('queue'));
});

test('papan hanya menampilkan pesanan tenant sendiri', function () {
    $mine = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
    ]);

    $otherTenant = Tenant::factory()->create(['kitchen_queue_enabled' => true]);
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'cashier']);
    Transaction::factory()->selfOrder()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
    ]);

    actingAs($this->cashier);

    get('/cashier/queue')->assertInertia(fn ($page) => $page
        ->has('queue', 1)
        ->where('queue.0.id', $mine->id)
    );
});

test('papan menggabungkan pesanan kasir dan self order', function () {
    Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'sort_index' => 2000,
    ]);
    Transaction::factory()->withFulfillment()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'source' => Transaction::SOURCE_POS,
        'sort_index' => 1000,
    ]);

    actingAs($this->cashier);

    get('/cashier/queue')->assertInertia(fn ($page) => $page
        ->has('queue', 2)
        ->where('queue.0.source', Transaction::SOURCE_POS)
        ->where('queue.1.source', Transaction::SOURCE_SELF_ORDER)
    );
});

// ── Masuk papan ────────────────────────────────────────────────────────────

test('checkout saat mode antrian hidup masuk papan dengan nomor dan urutan', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', queueCheckoutPayload())->assertSessionHas('success');

    $transaction = Transaction::first();

    expect($transaction->fulfillment_status)->toBe(Transaction::FULFILLMENT_WAITING)
        ->and($transaction->queue_number)->toBe(1)
        ->and($transaction->sort_index)->not->toBeNull();
});

test('nomor antrian bertambah untuk pesanan berikutnya', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', queueCheckoutPayload());
    post('/cashier/transactions', queueCheckoutPayload());

    expect(Transaction::orderBy('id')->pluck('queue_number')->all())->toBe([1, 2]);
});

test('nomor antrian reset harian', function () {
    // Pesanan kemarin memakai nomor 7; hari ini harus mulai dari 1 lagi.
    Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'queue_number' => 7,
        'occurred_at' => now()->subDay(),
    ]);

    actingAs($this->cashier);
    post('/cashier/transactions', queueCheckoutPayload());

    expect(Transaction::whereNotNull('client_uuid')->first()->queue_number)->toBe(1);
});

test('checkout saat mode antrian mati tidak masuk papan sama sekali', function () {
    $this->tenant->update(['kitchen_queue_enabled' => false]);

    actingAs($this->cashier);
    post('/cashier/transactions', queueCheckoutPayload())->assertSessionHas('success');

    $transaction = Transaction::first();

    expect($transaction->fulfillment_status)->toBeNull()
        ->and($transaction->queue_number)->toBeNull();
});

test('open bill saat mode antrian mati juga tidak masuk papan', function () {
    // Mengunci perubahan perilaku: versi lama memberi `waiting` pada SETIAP
    // open bill, bahkan tanpa papan yang mengerjakannya — itulah sumber
    // timbunan yang dibersihkan migrasi backfill.
    $this->tenant->update(['kitchen_queue_enabled' => false]);

    actingAs($this->cashier);

    post('/cashier/transactions', openBillPayload())->assertSessionHas('success');

    expect(Transaction::first()->fulfillment_status)->toBeNull();
});

test('open bill saat mode antrian hidup masuk papan dan ditandai belum bayar', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', openBillPayload())->assertSessionHas('success');

    $transaction = Transaction::first();

    expect($transaction->fulfillment_status)->toBe(Transaction::FULFILLMENT_WAITING)
        ->and($transaction->status)->toBe(Transaction::STATUS_PENDING);

    get('/cashier/queue')->assertInertia(fn ($page) => $page
        ->where('queue.0.is_paid', false)
        ->where('queue.0.amount_due', 20000)
    );
});

test('pelunasan open bill tidak mengubah status masak', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', openBillPayload());

    $transaction = Transaction::first();

    post("/cashier/transactions/{$transaction->id}/pay", [
        'payments' => [['payment_method_id' => $this->cash->id, 'amount' => 20000]],
    ]);

    // Memasak dan membayar dua hal berbeda: lunas tidak berarti matang.
    expect($transaction->fresh()->status)->toBe(Transaction::STATUS_COMPLETED)
        ->and($transaction->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_WAITING);

    get('/cashier/queue')->assertInertia(fn ($page) => $page->where('queue.0.is_paid', true));
});

test('transaksi offline tidak masuk papan', function () {
    // Mengunci batas lingkup Keputusan 1 supaya tidak "diperbaiki" tanpa
    // sengaja: mode antrian tidak aktif saat perangkat offline, dan penjualan
    // hasil sinkronisasi tidak menyusul masuk papan.
    $transaction = app(TransactionService::class)->commitOffline([
        'client_uuid' => (string) Str::uuid(),
        'occurred_at' => now()->subHour()->toIso8601String(),
        'device_id' => 'till-01',
        'items' => [[
            'variant_id' => $this->variant->id,
            'variant_name' => 'Kopi Susu - Reguler',
            'qty' => 1,
            'unit_price' => 20000,
            'modifiers' => [],
        ]],
        'payments' => [[
            'payment_method_id' => $this->cash->id,
            'amount' => 20000,
        ]],
    ], $this->cashier);

    expect($transaction->fulfillment_status)->toBeNull()
        ->and($transaction->queue_number)->toBeNull();
});

test('konfirmasi pembayaran self order memberi nomor antrian', function () {
    $transaction = Transaction::factory()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'source' => Transaction::SOURCE_SELF_ORDER,
        'status' => Transaction::STATUS_PENDING,
        'fulfillment_status' => null,
        'total_amount' => 20000,
    ]);

    $transaction->items()->create([
        'product_variant_id' => $this->variant->id,
        'variant_name' => 'Kopi Susu - Reguler',
        'qty' => 1,
        'unit_price' => 20000,
        'subtotal' => 20000,
    ]);

    app(TransactionService::class)->confirmSelfOrderPayment($transaction);

    // Tanpa langkah ini pesanan QR tidak akan pernah punya nomor untuk
    // dipanggil, dan seluruh guna papan runtuh.
    expect($transaction->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_WAITING)
        ->and($transaction->fresh()->queue_number)->toBe(1)
        ->and($transaction->fresh()->sort_index)->not->toBeNull();
});

// ── Aksi papan ─────────────────────────────────────────────────────────────

test('memajukan kartu lewat papan menaikkan statusnya', function () {
    $card = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
    ]);

    actingAs($this->cashier);

    post("/cashier/queue/{$card->id}/advance", ['expected_from' => Transaction::FULFILLMENT_WAITING]);

    expect($card->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_PREPARING);
});

test('kartu basi ditolak dengan pesan, bukan melompati status', function () {
    $card = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'fulfillment_status' => Transaction::FULFILLMENT_PREPARING,
    ]);

    actingAs($this->cashier);

    // Layar masih menampilkan "Mulai masak" padahal orang lain sudah menekannya.
    post("/cashier/queue/{$card->id}/advance", ['expected_from' => Transaction::FULFILLMENT_WAITING])
        ->assertSessionHas('error');

    expect($card->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_PREPARING);
});

test('kartu tenant lain tidak bisa disentuh lewat papan', function () {
    $otherTenant = Tenant::factory()->create(['kitchen_queue_enabled' => true]);
    $otherUser = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'cashier']);
    $theirs = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => $otherUser->id,
    ]);

    actingAs($this->cashier);

    // 404, bukan 403: TenantScope menyembunyikan baris tenant lain sejak route
    // model binding, jadi permintaan tak pernah sampai ke pemeriksaan eksplisit
    // di controller. Lapis keduanya tetap ada dan tetap benar — yang penting
    // kartu itu tak tersentuh.
    post("/cashier/queue/{$theirs->id}/advance", ['expected_from' => Transaction::FULFILLMENT_WAITING])
        ->assertStatus(404);

    expect($theirs->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_WAITING);
});

test('dahulukan menaruh kartu di puncak papan', function () {
    $first = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'sort_index' => 1000,
    ]);
    $last = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'sort_index' => 3000,
    ]);

    actingAs($this->cashier);

    post("/cashier/queue/{$last->id}/move-to-top");

    get('/cashier/queue')->assertInertia(fn ($page) => $page
        ->where('queue.0.id', $last->id)
        ->where('queue.1.id', $first->id)
    );
});

// ── Kebersihan papan ───────────────────────────────────────────────────────

test('void mengeluarkan pesanan dari papan', function () {
    $card = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'status' => Transaction::STATUS_COMPLETED,
    ]);

    app(TransactionService::class)->void($card);

    expect($card->fresh()->fulfillment_status)->toBeNull();

    actingAs($this->cashier);
    get('/cashier/queue')->assertInertia(fn ($page) => $page->has('queue', 0));
});

test('pesanan kemarin tidak muncul di papan hari ini', function () {
    Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'occurred_at' => now()->subDay(),
    ]);

    actingAs($this->cashier);

    // Tanpa batas hari, pesanan `ready` yang terlupakan saat tutup lapak akan
    // berdampingan dengan pesanan hari ini — dan nomornya bertabrakan karena
    // nomor antrian reset harian.
    get('/cashier/queue')->assertInertia(fn ($page) => $page->has('queue', 0));
});

test('migrasi backfill membersihkan timbunan lama tanpa menyentuh pesanan hari ini', function () {
    $makeStale = function (array $attributes, ?string $createdAt = null) {
        $transaction = Transaction::factory()->create(array_merge([
            'tenant_id' => $this->tenant->id,
            'user_id' => $this->cashier->id,
            'fulfillment_status' => Transaction::FULFILLMENT_WAITING,
        ], $attributes));

        if ($createdAt) {
            // Query builder, bukan update(): created_at tidak fillable.
            Transaction::withoutGlobalScopes()
                ->where('id', $transaction->id)
                ->update(['created_at' => $createdAt]);
        }

        return $transaction;
    };

    // (1) Open bill POS yang sudah lunas — sumber timbunan aslinya.
    $paidOpenBill = $makeStale([
        'status' => Transaction::STATUS_COMPLETED,
        'source' => Transaction::SOURCE_POS,
    ]);

    // (1) Transaksi yang dibatalkan.
    $voided = $makeStale(['status' => Transaction::STATUS_VOIDED]);

    // (2) Self-order lunas dari bulan lalu yang tak pernah ada yang memajukan.
    $oldSelfOrder = $makeStale([
        'status' => Transaction::STATUS_COMPLETED,
        'source' => Transaction::SOURCE_SELF_ORDER,
    ], now()->subMonth()->toDateTimeString());

    // Pesanan hari ini yang sungguh sedang berjalan — TIDAK boleh tersentuh.
    $liveToday = $makeStale([
        'status' => Transaction::STATUS_PENDING,
        'source' => Transaction::SOURCE_SELF_ORDER,
    ]);

    (require database_path('migrations/2026_07_29_142735_backfill_stale_fulfillment_status.php'))->up();

    expect($paidOpenBill->fresh()->fulfillment_status)->toBeNull()
        ->and($voided->fresh()->fulfillment_status)->toBeNull()
        ->and($oldSelfOrder->fresh()->fulfillment_status)->toBeNull()
        ->and($liveToday->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_WAITING);
});

// ── Gerbang langganan ──────────────────────────────────────────────────────

test('papan tetap bisa dimajukan saat masa tenggang', function () {
    $this->tenant->update(['status' => Tenant::STATUS_GRACE]);

    $card = Transaction::factory()->selfOrder()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
    ]);

    actingAs($this->cashier);

    // Menyelesaikan pesanan yang uangnya sudah diterima bukan "layanan baru".
    // Tanpa `cashier.queue.*` di ALWAYS_ALLOWED, dapur membeku di tengah
    // antrean pada hari langganan lewat jatuh tempo.
    post("/cashier/queue/{$card->id}/advance", ['expected_from' => Transaction::FULFILLMENT_WAITING]);

    expect($card->fresh()->fulfillment_status)->toBe(Transaction::FULFILLMENT_PREPARING);
});

test('masa tenggang tetap menolak penjualan baru meski papan terbuka', function () {
    $this->tenant->update(['status' => Tenant::STATUS_GRACE]);

    actingAs($this->cashier);

    // Pengecualian papan tidak boleh melebar jadi pintu belakang untuk
    // transaksi baru.
    post('/cashier/transactions', queueCheckoutPayload())->assertSessionHas('error');

    expect(Transaction::count())->toBe(0);
});
