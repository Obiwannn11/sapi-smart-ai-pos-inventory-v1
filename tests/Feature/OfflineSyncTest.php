<?php

use App\Models\Modifier;
use App\Models\ModifierGroup;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Support\Str;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\postJson;

/**
 * @return array{tenant: Tenant, cashier: User, variant: ProductVariant, cash: PaymentMethod}
 */
function makeOfflineContext(int $stock = 100, float $price = 25000): array
{
    $tenant = Tenant::factory()->create();
    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id, 'price' => $price, 'stock' => $stock,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id]);

    return compact('tenant', 'cashier', 'variant', 'cash');
}

/**
 * Payload satu transaksi offline. `qty`/`unit_price` membentuk item default;
 * key lain menimpa payload apa adanya.
 */
function offlinePayload(array $ctx, array $overrides = []): array
{
    $qty = $overrides['qty'] ?? 2;
    $unitPrice = $overrides['unit_price'] ?? 25000;

    return array_merge([
        'client_uuid' => (string) Str::uuid(),
        'occurred_at' => now()->subHour()->toIso8601String(),
        'device_id' => 'till-01',
        'items' => [[
            'variant_id' => $ctx['variant']->id,
            'variant_name' => 'Kopi Susu - Reguler',
            'qty' => $qty,
            'unit_price' => $unitPrice,
            'modifiers' => [],
            'notes' => null,
        ]],
        'payments' => [[
            'payment_method_id' => $ctx['cash']->id,
            'amount' => $qty * $unitPrice,
        ]],
    ], array_diff_key($overrides, array_flip(['qty', 'unit_price'])));
}

function commitOffline(array $ctx, array $payload): Transaction
{
    return app(TransactionService::class)->commitOffline($payload, $ctx['cashier']);
}

// ── Idempotensi ─────────────────────────────────────────────────────────────

it('menyimpan satu transaksi saja untuk client_uuid yang sama', function () {
    $ctx = makeOfflineContext();
    $payload = offlinePayload($ctx);

    $first = commitOffline($ctx, $payload);
    $second = commitOffline($ctx, $payload);

    expect($second->id)->toBe($first->id);
    expect(Transaction::where('client_uuid', $payload['client_uuid'])->count())->toBe(1);
});

it('tidak mengurangi stok dua kali saat payload diulang', function () {
    $ctx = makeOfflineContext(stock: 10);
    $payload = offlinePayload($ctx, ['qty' => 3]);

    commitOffline($ctx, $payload);
    commitOffline($ctx, $payload);

    expect($ctx['variant']->fresh()->stock)->toBe(7);
});

// ── Cash-only ───────────────────────────────────────────────────────────────

it('menolak pembayaran non-tunai pada transaksi offline', function () {
    $ctx = makeOfflineContext();
    $qris = PaymentMethod::factory()->qris()->create(['tenant_id' => $ctx['tenant']->id]);

    $payload = offlinePayload($ctx);
    $payload['payments'] = [['payment_method_id' => $qris->id, 'amount' => 50000]];

    expect(fn () => commitOffline($ctx, $payload))
        ->toThrow(Exception::class, 'hanya menerima pembayaran tunai');

    expect(Transaction::count())->toBe(0);
});

// ── Optimistic stock ────────────────────────────────────────────────────────

it('tetap menyimpan transaksi walau stok tidak cukup', function () {
    $ctx = makeOfflineContext(stock: 1);

    $transaction = commitOffline($ctx, offlinePayload($ctx, ['qty' => 5]));

    expect($transaction->exists)->toBeTrue();
    expect($transaction->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW);
});

it('menghitung stok minus dengan tepat dan menandai needs_review', function () {
    // Mengikat fix §3d: jangan decrement() lalu baca atribut in-memory.
    $ctx = makeOfflineContext(stock: 3);

    $transaction = commitOffline($ctx, offlinePayload($ctx, ['qty' => 5]));

    expect($ctx['variant']->fresh()->stock)->toBe(-2);
    expect($transaction->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW);
});

it('tidak menandai needs_review saat stok cukup dan harga sama', function () {
    $ctx = makeOfflineContext(stock: 10);

    $transaction = commitOffline($ctx, offlinePayload($ctx, ['qty' => 2]));

    expect($transaction->sync_status)->toBeNull();
    expect($ctx['variant']->fresh()->stock)->toBe(8);
});

// ── Harga & anomali data ────────────────────────────────────────────────────

it('menandai needs_review saat harga offline beda dari harga DB', function () {
    $ctx = makeOfflineContext(price: 30000);

    // Perangkat menjual dengan harga katalog lama.
    $transaction = commitOffline($ctx, offlinePayload($ctx, ['unit_price' => 25000]));

    expect($transaction->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW);
    // Harga yang dibayar pelanggan dipertahankan, bukan ditimpa harga DB.
    expect((float) $transaction->items->first()->unit_price)->toBe(25000.0);
});

it('menandai needs_review saat unit_price nol padahal harga DB tidak nol', function () {
    $ctx = makeOfflineContext(price: 25000);

    $transaction = commitOffline($ctx, offlinePayload($ctx, ['unit_price' => 0]));

    expect($transaction->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW);
});

it('tetap menyimpan transaksi saat variant sudah terhapus', function () {
    $ctx = makeOfflineContext();
    $payload = offlinePayload($ctx);
    $ctx['variant']->delete(); // soft delete — barisnya tetap ada

    $transaction = commitOffline($ctx, $payload);

    expect($transaction->exists)->toBeTrue();
    expect($transaction->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW);
    expect($transaction->items)->toHaveCount(1);
    // Tetap tertaut ke variant yang di-soft-delete: penjualan bisa ditelusuri.
    expect($transaction->items->first()->product_variant_id)->toBe($ctx['variant']->id);
});

// ── occurred_at ─────────────────────────────────────────────────────────────

it('mempertahankan occurred_at dan memakainya untuk kode transaksi', function () {
    $ctx = makeOfflineContext();
    $yesterday = now()->subDay()->setTime(14, 30);

    $transaction = commitOffline($ctx, offlinePayload($ctx, [
        'occurred_at' => $yesterday->toIso8601String(),
    ]));

    expect($transaction->occurred_at->toDateString())->toBe($yesterday->toDateString());
    expect($transaction->code)->toStartWith('TRX-'.$yesterday->format('Ymd'));
    expect($transaction->synced_at)->not->toBeNull();
    expect($transaction->channel)->toBe(Transaction::CHANNEL_OFFLINE);
});

it('menolak occurred_at di masa depan', function () {
    $ctx = makeOfflineContext();

    expect(fn () => commitOffline($ctx, offlinePayload($ctx, [
        'occurred_at' => now()->addHour()->toIso8601String(),
    ])))->toThrow(Exception::class, 'masa depan');

    expect(Transaction::count())->toBe(0);
});

it('menerima occurred_at dalam toleransi clock skew', function () {
    $ctx = makeOfflineContext();

    $transaction = commitOffline($ctx, offlinePayload($ctx, [
        'occurred_at' => now()->addMinutes(2)->toIso8601String(),
    ]));

    expect($transaction->exists)->toBeTrue();
});

it('menolak occurred_at yang terlalu lampau', function () {
    $ctx = makeOfflineContext();

    expect(fn () => commitOffline($ctx, offlinePayload($ctx, [
        'occurred_at' => now()->subDays(60)->toIso8601String(),
    ])))->toThrow(Exception::class, 'terlalu lampau');

    expect(Transaction::count())->toBe(0);
});

// ── Validasi payload §3f ────────────────────────────────────────────────────

it('menolak variant milik tenant lain tanpa menyentuh stoknya', function () {
    $ctx = makeOfflineContext();
    $otherTenant = Tenant::factory()->create();
    $otherProduct = Product::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherVariant = ProductVariant::factory()->create([
        'product_id' => $otherProduct->id, 'price' => 25000, 'stock' => 50,
    ]);

    $payload = offlinePayload($ctx);
    $payload['items'][0]['variant_id'] = $otherVariant->id;

    // Penipuan struktural, bukan anomali operasional → tolak (taksonomi §3f).
    expect(fn () => commitOffline($ctx, $payload))
        ->toThrow(Exception::class, 'tidak dikenal');

    expect($otherVariant->fresh()->stock)->toBe(50);
    expect(Transaction::count())->toBe(0);
});

it('menolak variant_id yang tidak ada sama sekali', function () {
    $ctx = makeOfflineContext();
    $payload = offlinePayload($ctx);
    $payload['items'][0]['variant_id'] = 999999;

    expect(fn () => commitOffline($ctx, $payload))
        ->toThrow(Exception::class, 'tidak dikenal');

    expect(Transaction::count())->toBe(0);
});

it('menolak metode pembayaran milik tenant lain', function () {
    $ctx = makeOfflineContext();
    $otherTenant = Tenant::factory()->create();
    $otherCash = PaymentMethod::factory()->create(['tenant_id' => $otherTenant->id]);

    $payload = offlinePayload($ctx);
    $payload['payments'] = [['payment_method_id' => $otherCash->id, 'amount' => 50000]];

    expect(fn () => commitOffline($ctx, $payload))
        ->toThrow(Exception::class, 'tidak ditemukan');

    expect(Transaction::count())->toBe(0);
});

it('menghitung ulang total dan mengabaikan total_amount kiriman client', function () {
    $ctx = makeOfflineContext(price: 25000);

    $payload = offlinePayload($ctx, ['qty' => 2, 'unit_price' => 25000]);
    $payload['total_amount'] = 1000; // dipalsukan

    $transaction = commitOffline($ctx, $payload);

    expect((float) $transaction->total_amount)->toBe(50000.0);
    expect($transaction->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW);
});

it('menolak qty nol atau negatif', function () {
    $ctx = makeOfflineContext();

    expect(fn () => commitOffline($ctx, offlinePayload($ctx, ['qty' => 0])))
        ->toThrow(Exception::class, 'Kuantitas');

    expect(Transaction::count())->toBe(0);
});

it('menolak transaksi offline tanpa item', function () {
    $ctx = makeOfflineContext();
    $payload = offlinePayload($ctx);
    $payload['items'] = [];

    expect(fn () => commitOffline($ctx, $payload))
        ->toThrow(Exception::class, 'tanpa item');
});

it('menolak transaksi offline tanpa pembayaran', function () {
    $ctx = makeOfflineContext();
    $payload = offlinePayload($ctx);
    $payload['payments'] = [];

    expect(fn () => commitOffline($ctx, $payload))
        ->toThrow(Exception::class, 'wajib menyertakan pembayaran tunai');
});

// ── Modifier ────────────────────────────────────────────────────────────────

it('menyimpan modifier dan menghitungnya ke dalam subtotal', function () {
    $ctx = makeOfflineContext(price: 20000);
    $group = ModifierGroup::factory()->create(['tenant_id' => $ctx['tenant']->id]);
    $modifier = Modifier::factory()->create([
        'modifier_group_id' => $group->id, 'extra_price' => 5000,
    ]);

    $payload = offlinePayload($ctx, ['qty' => 2, 'unit_price' => 20000]);
    $payload['items'][0]['modifiers'] = [[
        'id' => $modifier->id, 'name' => $modifier->name, 'extra_price' => 5000,
    ]];

    $transaction = commitOffline($ctx, $payload);

    // (20000 + 5000) * 2
    expect((float) $transaction->total_amount)->toBe(50000.0);
    expect($transaction->sync_status)->toBeNull();
    expect($transaction->items->first()->modifiers)->toHaveCount(1);
});

// ── Endpoint batch ──────────────────────────────────────────────────────────

it('menyinkronkan batch dan melaporkan hasil per client_uuid', function () {
    $ctx = makeOfflineContext(stock: 100);
    actingAs($ctx['cashier']);

    $fresh = offlinePayload($ctx);
    $duplicate = offlinePayload($ctx);
    commitOffline($ctx, $duplicate); // sudah tersinkron sebelumnya

    $response = postJson(route('cashier.transactions.sync'), [
        'transactions' => [$fresh, $duplicate],
    ]);

    $response->assertOk();
    $results = collect($response->json('results'));

    expect($results->firstWhere('client_uuid', $fresh['client_uuid'])['status'])->toBe('synced');
    expect($results->firstWhere('client_uuid', $duplicate['client_uuid'])['status'])->toBe('duplicate');
    expect($response->json('synced'))->toBe(2);
    expect($response->json('failed'))->toBe(0);
});

it('tidak membiarkan satu payload rusak menggagalkan seluruh batch', function () {
    $ctx = makeOfflineContext(stock: 100);
    actingAs($ctx['cashier']);

    $good = offlinePayload($ctx);
    $poison = offlinePayload($ctx);
    $poison['payments'] = [['payment_method_id' => 999999, 'amount' => 50000]];

    $response = postJson(route('cashier.transactions.sync'), [
        'transactions' => [$poison, $good],
    ]);

    $response->assertOk();
    $results = collect($response->json('results'));

    expect($results->firstWhere('client_uuid', $poison['client_uuid'])['status'])->toBe('error');
    expect($results->firstWhere('client_uuid', $good['client_uuid'])['status'])->toBe('synced');

    // Yang sehat tetap tersimpan meski ada tetangga yang gagal.
    expect(Transaction::where('client_uuid', $good['client_uuid'])->exists())->toBeTrue();
    expect(Transaction::where('client_uuid', $poison['client_uuid'])->exists())->toBeFalse();
});

it('menolak batch tanpa client_uuid', function () {
    $ctx = makeOfflineContext();
    actingAs($ctx['cashier']);

    $payload = offlinePayload($ctx);
    unset($payload['client_uuid']);

    postJson(route('cashier.transactions.sync'), ['transactions' => [$payload]])
        ->assertStatus(422)
        ->assertJsonValidationErrors('transactions.0.client_uuid');
});

it('menolak sync dari tamu yang belum login', function () {
    $ctx = makeOfflineContext();

    postJson(route('cashier.transactions.sync'), [
        'transactions' => [offlinePayload($ctx)],
    ])->assertStatus(401);
});

// ── Laporan berbasis occurred_at (Fase D) ───────────────────────────────────

it('menghitung penjualan offline pada hari terjadinya, bukan hari sync', function () {
    $ctx = makeOfflineContext();
    $yesterday = now()->subDay();

    // Disinkronkan sekarang, tapi terjual kemarin.
    commitOffline($ctx, offlinePayload($ctx, [
        'occurred_at' => $yesterday->copy()->setTime(10, 0)->toIso8601String(),
    ]));

    $soldYesterday = Transaction::where('status', Transaction::STATUS_COMPLETED)
        ->whereEffectiveDate($yesterday->toDateString())
        ->count();

    $soldToday = Transaction::where('status', Transaction::STATUS_COMPLETED)
        ->whereEffectiveDate(now()->toDateString())
        ->count();

    expect($soldYesterday)->toBe(1);
    // Tidak boleh menggelembungkan angka hari ini hanya karena baru tersinkron.
    expect($soldToday)->toBe(0);
});

it('tetap menghitung transaksi online memakai created_at', function () {
    $ctx = makeOfflineContext();
    actingAs($ctx['cashier']);

    app(TransactionService::class)->checkout([
        'items' => [[
            'variant_id' => $ctx['variant']->id,
            'variant_name' => 'Kopi', 'qty' => 1, 'unit_price' => 25000, 'modifiers' => [],
        ]],
        'payments' => [['payment_method_id' => $ctx['cash']->id, 'amount' => 25000]],
    ]);

    // occurred_at null → COALESCE jatuh ke created_at.
    expect(Transaction::whereEffectiveDate(now()->toDateString())->count())->toBe(1);
});

it('memunculkan badge Perlu Koreksi untuk transaksi bermasalah', function () {
    $ctx = makeOfflineContext(stock: 1);
    commitOffline($ctx, offlinePayload($ctx, ['qty' => 5]));

    actingAs($ctx['cashier']);
    $badges = app(App\Services\BadgeHelperService::class)->generate($ctx['tenant']);

    $reviewBadge = collect($badges)->firstWhere('type', 'needs_review');

    expect($reviewBadge)->not->toBeNull();
    expect($reviewBadge['count'])->toBe(1);
});

it('mengizinkan owner menandai transaksi sudah ditinjau', function () {
    $ctx = makeOfflineContext(stock: 1);
    $transaction = commitOffline($ctx, offlinePayload($ctx, ['qty' => 5]));

    $owner = User::factory()->create(['tenant_id' => $ctx['tenant']->id, 'role' => 'owner']);
    actingAs($owner);

    $this->post(route('owner.offline-review.resolve', $transaction))
        ->assertRedirect();

    expect($transaction->fresh()->sync_status)->toBeNull();
});

it('mencegah owner tenant lain menandai transaksi ini', function () {
    $ctx = makeOfflineContext(stock: 1);
    $transaction = commitOffline($ctx, offlinePayload($ctx, ['qty' => 5]));

    $otherOwner = User::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id, 'role' => 'owner',
    ]);
    actingAs($otherOwner);

    // 404, bukan 403: TenantScope pada Transaction membuat route-model-binding
    // tidak menemukan baris milik tenant lain sama sekali. Ditolak lebih awal
    // DAN tanpa membocorkan bahwa transaksi itu ada. Cek 403 di controller tetap
    // dipertahankan sebagai lapis kedua bila scope tidak aktif.
    $this->post(route('owner.offline-review.resolve', $transaction))->assertStatus(404);

    expect($transaction->fresh()->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW);
});

// ── Pajak ([BL-065]) ────────────────────────────────────────────────────────

it('memajaki penjualan offline dengan setelan tenant saat sinkronisasi', function () {
    $ctx = makeOfflineContext();
    $ctx['tenant']->update([
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => 'PPN',
    ]);

    // Perangkat menghitung total yang sama seperti server: 50.000 + 11%.
    $transaction = commitOffline($ctx, offlinePayload($ctx, [
        'total_amount' => 55500,
        'payments' => [['payment_method_id' => $ctx['cash']->id, 'amount' => 55500]],
    ]));

    expect((float) $transaction->subtotal_amount)->toBe(50000.0)
        ->and((float) $transaction->tax_amount)->toBe(5500.0)
        ->and((float) $transaction->total_amount)->toBe(55500.0)
        // Cocok dengan hitungan perangkat, jadi tidak perlu ditinjau.
        ->and($transaction->sync_status)->toBeNull();
});

it('menandai needs_review saat perangkat memakai tarif yang sudah berubah', function () {
    $ctx = makeOfflineContext();
    $ctx['tenant']->update([
        'tax_enabled' => true,
        'tax_mode' => Tenant::TAX_MODE_EXCLUSIVE,
        'tax_rate' => 11,
        'tax_label' => 'PPN',
    ]);

    // Perangkat seharian offline masih memakai tarif lama 10% dan mengirim
    // 55.000. Server menghitung 55.500. Selisihnya tidak ditolak — penjualan
    // ini sudah terjadi secara fisik dan struknya sudah dibawa pulang — tapi
    // pemilik harus melihatnya.
    $transaction = commitOffline($ctx, offlinePayload($ctx, [
        'total_amount' => 55000,
        'payments' => [['payment_method_id' => $ctx['cash']->id, 'amount' => 55000]],
    ]));

    expect($transaction->sync_status)->toBe(Transaction::SYNC_NEEDS_REVIEW)
        // Yang tercatat tetap hitungan SERVER, bukan hitungan perangkat.
        ->and((float) $transaction->total_amount)->toBe(55500.0);
});

it('tidak memajaki penjualan offline milik tenant tanpa pajak', function () {
    $ctx = makeOfflineContext();

    $transaction = commitOffline($ctx, offlinePayload($ctx, ['total_amount' => 50000]));

    expect((float) $transaction->total_amount)->toBe(50000.0)
        ->and((float) $transaction->subtotal_amount)->toBe(50000.0)
        ->and((float) $transaction->tax_amount)->toBe(0.0)
        ->and($transaction->tax_mode)->toBeNull()
        ->and($transaction->sync_status)->toBeNull();
});
