<?php

/**
 * Identitas pesanan — nama / nomor meja / kode panggil ([BL-026]).
 *
 * Yang dijaga di sini bukan "kolomnya terisi" (kolomnya sudah lama ada dan
 * sudah dipakai jalur self-order), melainkan tiga hal yang dulu tidak ada:
 * kasir bisa MENGIRIM identitas dari POS, nomor panggil LEPAS dari papan
 * dapur, dan mode identitas tidak pernah punya kuasa menolak penjualan.
 */

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\patch;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create([
        'kitchen_queue_enabled' => false,
        'order_identity_mode' => Tenant::ORDER_IDENTITY_NONE,
    ]);

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

function identityCheckoutPayload(array $overrides = []): array
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

// ── Yang hilang persis satu lapis: kasir mengirim identitas ────────────────

test('checkout bayar langsung menyimpan nomor meja dari kasir', function () {
    $this->tenant->update(['order_identity_mode' => Tenant::ORDER_IDENTITY_TABLE]);

    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload([
        'table_number' => '12',
    ]))->assertRedirect();

    expect(Transaction::latest('id')->first())
        ->table_number->toBe('12')
        ->status->toBe(Transaction::STATUS_COMPLETED);
});

test('checkout bayar langsung menyimpan nama pelanggan dari kasir', function () {
    $this->tenant->update(['order_identity_mode' => Tenant::ORDER_IDENTITY_NAME]);

    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload([
        'customer_name' => 'Budi',
    ]))->assertRedirect();

    expect(Transaction::latest('id')->first()->customer_name)->toBe('Budi');
});

test('tagihan terbuka menyimpan nomor meja, bukan hanya nama', function () {
    $payload = identityCheckoutPayload([
        'is_open_bill' => true,
        'customer_name' => 'Budi',
        'table_number' => '7',
    ]);
    unset($payload['payments']);

    actingAs($this->cashier);

    post('/cashier/transactions', $payload)->assertRedirect();

    expect(Transaction::latest('id')->first())
        ->customer_name->toBe('Budi')
        ->table_number->toBe('7')
        ->status->toBe(Transaction::STATUS_PENDING);
});

test('nomor meja lebih panjang dari kolomnya ditolak validasi', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload([
        'table_number' => '12345678901',
    ]))->assertSessionHasErrors('table_number');

    expect(Transaction::count())->toBe(0);
});

// ── Nomor panggil lepas dari papan dapur ───────────────────────────────────

test('mode kode memberi nomor panggil tanpa menyalakan papan dapur', function () {
    $this->tenant->update([
        'kitchen_queue_enabled' => false,
        'order_identity_mode' => Tenant::ORDER_IDENTITY_CODE,
    ]);

    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload())->assertRedirect();

    $transaction = Transaction::latest('id')->first();

    // Inti pemisahannya: nomor panggil TIDAK memasukkan pesanan ke papan.
    expect($transaction->queue_number)->toBe(1)
        ->and($transaction->fulfillment_status)->toBeNull();
});

test('nomor panggil berurut per hari dalam mode kode', function () {
    $this->tenant->update(['order_identity_mode' => Tenant::ORDER_IDENTITY_CODE]);

    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload());
    post('/cashier/transactions', identityCheckoutPayload());

    expect(Transaction::orderBy('id')->pluck('queue_number')->all())->toBe([1, 2]);
});

test('tanpa papan dapur dan tanpa mode kode, tidak ada nomor panggil', function () {
    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload())->assertRedirect();

    expect(Transaction::latest('id')->first()->queue_number)->toBeNull();
});

test('papan dapur tetap memberi nomor panggil meski mode identitas none', function () {
    $this->tenant->update([
        'kitchen_queue_enabled' => true,
        'order_identity_mode' => Tenant::ORDER_IDENTITY_NONE,
    ]);

    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload())->assertRedirect();

    expect(Transaction::latest('id')->first())
        ->queue_number->toBe(1)
        ->fulfillment_status->toBe(Transaction::FULFILLMENT_WAITING);
});

// ── Mode identitas tidak boleh menolak penjualan ───────────────────────────

test('penjualan tetap sah saat kasir melewati identitas', function () {
    $this->tenant->update(['order_identity_mode' => Tenant::ORDER_IDENTITY_TABLE]);

    actingAs($this->cashier);

    post('/cashier/transactions', identityCheckoutPayload())->assertRedirect();

    expect(Transaction::latest('id')->first())
        ->status->toBe(Transaction::STATUS_COMPLETED)
        ->table_number->toBeNull();
});

// ── Pengaturan owner ───────────────────────────────────────────────────────

test('owner bisa menyetel mode identitas pesanan', function () {
    $owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($owner);

    patch('/owner/settings', [
        'order_identity_mode' => Tenant::ORDER_IDENTITY_CODE,
    ])->assertRedirect();

    expect($this->tenant->fresh()->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_CODE);
});

test('mode identitas asing ditolak', function () {
    $owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($owner);

    patch('/owner/settings', ['order_identity_mode' => 'nomor_hp'])
        ->assertSessionHasErrors('order_identity_mode');

    expect($this->tenant->fresh()->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_NONE);
});

test('menyimpan pengaturan lain tidak mengembalikan mode identitas ke none', function () {
    $this->tenant->update(['order_identity_mode' => Tenant::ORDER_IDENTITY_TABLE]);

    $owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);

    actingAs($owner);

    patch('/owner/settings', ['phone' => '021-1234567'])->assertRedirect();

    expect($this->tenant->fresh()->order_identity_mode)->toBe(Tenant::ORDER_IDENTITY_TABLE);
});

test('mode identitas dibagikan ke halaman kasir', function () {
    $this->tenant->update(['order_identity_mode' => Tenant::ORDER_IDENTITY_TABLE]);

    actingAs($this->cashier);

    get('/cashier/pos')->assertInertia(
        fn ($page) => $page->where('auth.tenant.order_identity_mode', Tenant::ORDER_IDENTITY_TABLE)
    );
});
