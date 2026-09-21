<?php

use App\Models\CashDrawer;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionPayment;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/**
 * Foto bukti pembayaran non-tunai (`[BL-075]`).
 *
 * Yang dijaga di sini bukan "fotonya tersimpan" — itu bagian yang paling
 * mudah. Yang dijaga adalah tiga hal yang gagal dalam diam bila salah:
 * kewajibannya ditegakkan SERVER dan bukan cuma UI, buktinya tidak lenyap saat
 * transaksinya diedit karena alasan lain, dan foto satu toko tidak pernah bisa
 * dibuka toko lain.
 */

/**
 * @return array{tenant: Tenant, cashier: User, variant: ProductVariant, cash: PaymentMethod, qris: PaymentMethod}
 */
function makeProofContext(bool $proofEnabled = true): array
{
    $tenant = Tenant::factory()->create(['payment_proof_enabled' => $proofEnabled]);

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 25000,
        'cost_price' => 15000,
        'stock' => 50,
    ]);

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    return [
        'tenant' => $tenant,
        'cashier' => $cashier,
        'variant' => $variant,
        'cash' => PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'cash']),
        'qris' => PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'qris_static']),
    ];
}

/** Unggah satu foto dan kembalikan tokennya. */
function uploadPaymentProof(): string
{
    return postJson('/cashier/bukti-bayar', [
        'proof' => UploadedFile::fake()->image('bukti.jpg', 1200, 1600),
    ])->json('token');
}

/**
 * @param  array<int, array<string, mixed>>  $payments
 */
function checkoutWithProof(ProductVariant $variant, array $payments): Illuminate\Testing\TestResponse
{
    return post('/cashier/transactions', [
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'qty' => 1,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'payments' => $payments,
    ]);
}

beforeEach(fn () => Storage::fake('local'));

// --- Saklar per toko ---

test('kamera hanya ditawarkan pada toko yang menyalakannya', function () {
    ['cashier' => $cashier] = makeProofContext(proofEnabled: false);

    actingAs($cashier);

    get('/cashier/pos')->assertInertia(
        fn ($page) => $page->where('paymentProofEnabled', false)
    );
});

test('endpoint unggah menolak toko yang belum menyalakan fiturnya', function () {
    ['cashier' => $cashier] = makeProofContext(proofEnabled: false);

    actingAs($cashier);

    // Digerbang di server, bukan hanya dengan menyembunyikan tombolnya:
    // endpoint yang menerima berkas dari toko mana pun adalah penyimpanan
    // gratis bagi siapa saja yang punya akun kasir.
    postJson('/cashier/bukti-bayar', [
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
    ])->assertForbidden();
});

test('toko yang tidak menyalakannya tetap bisa menjual non-tunai tanpa foto', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris] = makeProofContext(proofEnabled: false);

    actingAs($cashier);

    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000],
    ])->assertSessionHas('success');
});

// --- Kewajiban, ditegakkan di server ---

test('penjualan non-tunai tanpa foto ditolak saat tokonya mewajibkan', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris] = makeProofContext();

    actingAs($cashier);

    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000],
    ])->assertSessionHasErrors('payments.0.proof_token');

    expect(Transaction::count())->toBe(0);
});

test('token yang dikarang tidak memenuhi kewajiban berfoto', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris] = makeProofContext();

    actingAs($cashier);

    // Bentuknya UUID yang sah, jadi ia lolos aturan `uuid` — yang menahannya
    // adalah pemeriksaan bahwa berkasnya benar-benar ada. Tanpa itu "wajib
    // berfoto" bisa dipenuhi dengan mengetik.
    checkoutWithProof($variant, [
        [
            'payment_method_id' => $qris->id,
            'amount' => 25000,
            'proof_token' => (string) Illuminate\Support\Str::uuid(),
        ],
    ])->assertSessionHasErrors('payments.0.proof_token');
});

test('baris tunai tidak pernah diminta foto, bahkan saat fiturnya menyala', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeProofContext();

    actingAs($cashier);

    checkoutWithProof($variant, [
        ['payment_method_id' => $cash->id, 'amount' => 25000],
    ])->assertSessionHas('success');
});

test('pada split bill hanya baris non-tunai yang wajib berfoto', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash, 'qris' => $qris] = makeProofContext();

    actingAs($cashier);
    $token = uploadPaymentProof();

    checkoutWithProof($variant, [
        ['payment_method_id' => $cash->id, 'amount' => 10000],
        ['payment_method_id' => $qris->id, 'amount' => 15000, 'proof_token' => $token],
    ])->assertSessionHas('success');

    $payments = TransactionPayment::query()->get()->keyBy('payment_method_id');

    // Buktinya melekat pada PEMBAYARANNYA, bukan pada penjualannya — itulah
    // sebabnya kolomnya di transaction_payments.
    expect($payments[$qris->id]->proof_path)->not->toBeNull()
        ->and($payments[$cash->id]->proof_path)->toBeNull();
});

// --- Penyimpanan ---

test('foto tersimpan sebagai WEBP di disk privat dan tidak lagi tertunda', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris, 'tenant' => $tenant] = makeProofContext();

    actingAs($cashier);
    $token = uploadPaymentProof();

    Storage::disk('local')->assertExists("payment-proofs/{$tenant->id}/pending/{$token}.webp");

    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $token],
    ])->assertSessionHas('success');

    $path = TransactionPayment::first()->proof_path;

    expect($path)->toBe("payment-proofs/{$tenant->id}/{$token}.webp");
    Storage::disk('local')->assertExists($path);
    Storage::disk('local')->assertMissing("payment-proofs/{$tenant->id}/pending/{$token}.webp");
});

test('token milik tenant lain tidak bisa diklaim', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris] = makeProofContext();
    ['cashier' => $orangLain] = makeProofContext();

    actingAs($orangLain);
    $tokenOrangLain = uploadPaymentProof();

    actingAs($cashier);

    // Path bukti dipisah per tenant, jadi token toko sebelah tidak menunjuk
    // berkas apa pun di direktori toko ini — dan kewajibannya tetap berlaku.
    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $tokenOrangLain],
    ])->assertSessionHasErrors('payments.0.proof_token');
});

// --- Penyajian ---

test('foto bukti bayar hanya bisa dibuka tenant pemiliknya', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris] = makeProofContext();

    actingAs($cashier);
    $token = uploadPaymentProof();
    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $token],
    ]);

    $payment = TransactionPayment::first();

    get("/media/bukti-bayar/{$payment->id}/full")->assertOk();

    ['cashier' => $orangLain] = makeProofContext();
    actingAs($orangLain);

    // 404, bukan 403: jawaban yang membedakan "bukan milikmu" dari "tidak ada"
    // mengubah URL ini jadi alat menghitung penjualan toko sebelah.
    get("/media/bukti-bayar/{$payment->id}/full")->assertNotFound();
});

// --- Jalur pembuat pembayaran yang lain ---

test('pelunasan tagihan terbuka juga mewajibkan foto', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris] = makeProofContext();

    actingAs($cashier);

    checkoutWithProof($variant, [])->assertSessionHasErrors();

    $openBill = post('/cashier/transactions', [
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => $variant->name,
            'qty' => 1,
            'unit_price' => 25000,
            'modifiers' => [],
        ]],
        'is_open_bill' => true,
    ]);

    $openBill->assertSessionHas('success');

    $transaction = Transaction::where('status', Transaction::STATUS_PENDING)->firstOrFail();

    // Jalur KEDUA pembuat baris pembayaran. Ia mudah terlewat justru karena
    // meja yang memesan dulu lalu membayar QRIS saat pulang tidak pernah muncul
    // dalam pengujian manual yang berhenti di layar POS.
    post("/cashier/transactions/{$transaction->id}/pay", [
        'payments' => [['payment_method_id' => $qris->id, 'amount' => 25000]],
    ])->assertSessionHasErrors('payments.0.proof_token');

    $token = uploadPaymentProof();

    post("/cashier/transactions/{$transaction->id}/pay", [
        'payments' => [['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $token]],
    ])->assertSessionHas('success');

    expect($transaction->fresh()->payments->first()->proof_path)->not->toBeNull();
});

test('sinkronisasi offline tidak pernah menuntut foto — ia cash-only', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'cash' => $cash] = makeProofContext();

    actingAs($cashier);

    // Jalur KETIGA. Kewajiban berfoto tidak berlaku di sini bukan karena
    // dilonggarkan, melainkan karena assertCashOnly() menolak setiap pembayaran
    // non-tunai jauh sebelum sampai — dan tunai tidak punya bukti untuk difoto.
    postJson('/cashier/transactions/sync', [
        'transactions' => [[
            'client_uuid' => (string) Illuminate\Support\Str::uuid(),
            'occurred_at' => now()->subMinutes(5)->toIso8601String(),
            'items' => [[
                'variant_id' => $variant->id,
                'variant_name' => $variant->name,
                'qty' => 1,
                'unit_price' => 25000,
            ]],
            'payments' => [['payment_method_id' => $cash->id, 'amount' => 25000]],
        ]],
    ])->assertOk()->assertJsonPath('synced', 1);
});

test('mengedit transaksi tidak menghapus foto bukti bayarnya', function () {
    ['cashier' => $cashier, 'tenant' => $tenant, 'variant' => $variant, 'qris' => $qris] = makeProofContext();

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($cashier);
    $token = uploadPaymentProof();
    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $token],
    ]);

    $transaction = Transaction::firstOrFail();
    $pathSebelum = $transaction->payments->first()->proof_path;

    actingAs($owner);

    // Baris pembayaran dihapus lalu dibangun ulang dari kiriman client, dan
    // client edit tidak pernah mengirim bukti. Tanpa penyelamatan di
    // TransactionEditService, mengoreksi jumlah item akan menghapus buktinya
    // sebagai efek samping — tanpa galat, tanpa jejak.
    $this->put("/cashier/transactions/{$transaction->id}", [
        'items' => [[
            'variant_id' => $variant->id,
            'qty' => 2,
            'modifiers' => [],
        ]],
        'payments' => [['payment_method_id' => $qris->id, 'amount' => 50000]],
        'reason' => 'Pelanggan menambah satu porsi.',
    ])->assertSessionHasNoErrors();

    expect($transaction->fresh()->payments->first()->proof_path)->toBe($pathSebelum);
    Storage::disk('local')->assertExists($pathSebelum);
});

test('pembayaran yang diubah jadi non-tunai lewat edit wajib berfoto', function () {
    ['cashier' => $cashier, 'tenant' => $tenant, 'variant' => $variant, 'cash' => $cash, 'qris' => $qris] = makeProofContext();

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($cashier);
    checkoutWithProof($variant, [
        ['payment_method_id' => $cash->id, 'amount' => 25000],
    ])->assertSessionHas('success');

    $transaction = Transaction::firstOrFail();

    actingAs($owner);

    // Celah yang tertinggal saat `[BL-075]` ditutup: penjualan tunai yang
    // diubah jadi QRIS lewat pengeditan lolos tanpa bukti apa pun, karena
    // kewajibannya hanya dipasang pada dua jalur online yang lain.
    $this->put("/cashier/transactions/{$transaction->id}", [
        'items' => [['variant_id' => $variant->id, 'qty' => 1, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $qris->id, 'amount' => 25000]],
    ])->assertSessionHasErrors('payments.0.proof_token');

    expect($transaction->fresh()->payments->first()->payment_method_id)->toBe($cash->id);
});

test('edit ke non-tunai lolos begitu fotonya dilampirkan', function () {
    ['cashier' => $cashier, 'tenant' => $tenant, 'variant' => $variant, 'cash' => $cash, 'qris' => $qris] = makeProofContext();

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($cashier);
    checkoutWithProof($variant, [
        ['payment_method_id' => $cash->id, 'amount' => 25000],
    ]);

    $transaction = Transaction::firstOrFail();

    actingAs($owner);
    $token = uploadPaymentProof();

    $this->put("/cashier/transactions/{$transaction->id}", [
        'items' => [['variant_id' => $variant->id, 'qty' => 1, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $token]],
        'reason' => 'Pelanggan batal bayar tunai, pindah QRIS.',
    ])->assertSessionHasNoErrors();

    $payment = $transaction->fresh()->payments->first();

    expect($payment->payment_method_id)->toBe($qris->id)
        ->and($payment->proof_path)->toBe("payment-proofs/{$tenant->id}/{$token}.webp");
    Storage::disk('local')->assertExists($payment->proof_path);
});

test('metode non-tunai yang sudah ada tidak dituntut foto ulang saat diedit', function () {
    ['cashier' => $cashier, 'tenant' => $tenant, 'variant' => $variant, 'qris' => $qris] = makeProofContext(proofEnabled: false);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($cashier);
    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000],
    ])->assertSessionHas('success');

    $transaction = Transaction::firstOrFail();

    // Saklarnya baru dinyalakan SESUDAH penjualan itu terjadi — keadaan yang
    // pasti dialami setiap toko yang mengaktifkan fiturnya.
    $tenant->update(['payment_proof_enabled' => true]);

    actingAs($owner);

    // Mewajibkan foto di sini akan mengunci layar edit: QRIS kemarin tidak
    // punya apa pun untuk dipotret hari ini, dan yang gagal bukan fotonya
    // melainkan koreksi qty-nya. Persis larangan butir offline (5).
    $this->put("/cashier/transactions/{$transaction->id}", [
        'items' => [['variant_id' => $variant->id, 'qty' => 2, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $qris->id, 'amount' => 50000]],
        'reason' => 'Salah input qty.',
    ])->assertSessionHasNoErrors();

    expect($transaction->fresh()->items->first()->qty)->toBe(2);
});

test('memotret ulang saat edit mengganti foto lama dan membuang berkasnya', function () {
    ['cashier' => $cashier, 'tenant' => $tenant, 'variant' => $variant, 'qris' => $qris] = makeProofContext();

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($cashier);
    $lama = uploadPaymentProof();
    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $lama],
    ]);

    $transaction = Transaction::firstOrFail();

    actingAs($owner);
    $baru = uploadPaymentProof();

    $this->put("/cashier/transactions/{$transaction->id}", [
        'items' => [['variant_id' => $variant->id, 'qty' => 1, 'modifiers' => []]],
        'payments' => [['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $baru]],
        'reason' => 'Foto sebelumnya buram.',
    ])->assertSessionHasNoErrors();

    expect($transaction->fresh()->payments->first()->proof_path)
        ->toBe("payment-proofs/{$tenant->id}/{$baru}.webp");

    // Yang digantikan tidak boleh tertinggal di disk: retensi tanpa batas
    // berlaku untuk bukti yang MELEKAT, bukan untuk versi yang sudah dibuang.
    Storage::disk('local')->assertMissing("payment-proofs/{$tenant->id}/{$lama}.webp");
});

test('layar edit menerima id pembayaran dan penandanya, cukup untuk memanggil rute media', function () {
    ['cashier' => $cashier, 'tenant' => $tenant, 'variant' => $variant, 'qris' => $qris] = makeProofContext();

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($cashier);
    $token = uploadPaymentProof();
    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $token],
    ]);

    $transaction = Transaction::firstOrFail();
    $payment = $transaction->payments->first();

    actingAs($owner);

    // Pratinjau bukti di modal edit berdiri di atas dua medan ini saja: `id`
    // untuk menyusun /media/bukti-bayar/{id}/thumb, dan `proof_path` sebagai
    // penanda ada-tidaknya. Menyembunyikan salah satunya — lewat $hidden, atau
    // lewat perpindahan ke API Resource — akan menghilangkan pratinjaunya tanpa
    // satu pun galat. Serialisasi modelnya sama untuk riwayat kasir, jadi satu
    // penjaga menutup kedua layar.
    get("/owner/transactions/{$transaction->id}")->assertInertia(
        fn ($page) => $page
            ->where('transaction.payments.0.id', $payment->id)
            ->where('transaction.payments.0.proof_path', $payment->proof_path)
    );
});

// --- Kebersihan disk ---

test('foto tertunda yang tidak pernah diklaim dibuang, yang sudah melekat tidak', function () {
    ['cashier' => $cashier, 'variant' => $variant, 'qris' => $qris, 'tenant' => $tenant] = makeProofContext();

    actingAs($cashier);

    $terlantar = uploadPaymentProof();

    $dipakai = uploadPaymentProof();
    checkoutWithProof($variant, [
        ['payment_method_id' => $qris->id, 'amount' => 25000, 'proof_token' => $dipakai],
    ])->assertSessionHas('success');

    $pending = "payment-proofs/{$tenant->id}/pending/{$terlantar}.webp";
    touch(Storage::disk('local')->path($pending), now()->subDays(3)->getTimestamp());

    $this->artisan('payment-proofs:prune-unclaimed')->assertExitCode(0);

    Storage::disk('local')->assertMissing($pending);
    // Yang sudah melekat pada pembayaran tidak pernah disentuh perintah ini:
    // retensi foto yang diklaim adalah keputusan produk, bukan kebersihan.
    Storage::disk('local')->assertExists("payment-proofs/{$tenant->id}/{$dipakai}.webp");
});

test('foto tertunda yang masih baru tidak ikut dibuang', function () {
    ['cashier' => $cashier, 'tenant' => $tenant] = makeProofContext();

    actingAs($cashier);
    $token = uploadPaymentProof();

    // Batas 24 jam, bukan satu jam: menghapus foto milik modal pembayaran yang
    // masih terbuka berarti menggagalkan penjualan yang sedang berlangsung.
    $this->artisan('payment-proofs:prune-unclaimed')->assertExitCode(0);

    Storage::disk('local')->assertExists("payment-proofs/{$tenant->id}/pending/{$token}.webp");
});
