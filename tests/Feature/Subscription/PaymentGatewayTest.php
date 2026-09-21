<?php

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\Gateways\FakeGateway;
use App\Services\Billing\Gateways\PaymentGateway;
use App\Services\Billing\Gateways\PaymentGatewayManager;
use App\Services\Billing\InvoiceSettlement;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

/**
 * Alur bayar lewat payment gateway — `[BL-059]`.
 *
 * Yang diuji di sini bukan bahwa tombolnya bekerja, melainkan bahwa jalur uang
 * ini tidak punya cara untuk melunasi tagihan yang tidak dibayar: tanda tangan
 * palsu, notifikasi berulang, nominal yang kurang, instruksi yang kedaluwarsa,
 * dan driver tiruan yang tersasar ke produksi.
 */

/**
 * @return array{tenant: Tenant, owner: User, cashier: User, invoice: Invoice}
 */
function paymentContext(): array
{
    $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_GRACE]);

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->subDay()->toDateString(),
    ]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'cashier' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']),
        'invoice' => Invoice::factory()->create([
            'tenant_id' => $tenant->id,
            'subscription_id' => $subscription->id,
            'amount' => 100000,
            'status' => Invoice::STATUS_UNPAID,
        ]),
    ];
}

/**
 * Notifikasi yang ditandatangani seperti penyedia sungguhan menandatanganinya.
 *
 * @param  array<string, mixed>  $payload
 * @return array{0: string, 1: array<string, string>}
 */
function signedCallback(array $payload): array
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);

    return [$body, [FakeGateway::signatureHeader() => app(FakeGateway::class)->sign($body)]];
}

function sendCallback(array $payload, ?string $signature = null): Illuminate\Testing\TestResponse
{
    [$body, $headers] = signedCallback($payload);

    return postJson(
        '/webhook/pembayaran/fake',
        $payload,
        $signature === null ? $headers : [FakeGateway::signatureHeader() => $signature],
    );
}

// ── Menerbitkan instruksi ────────────────────────────────────────────────────

test('an owner can issue a payment instruction for their own invoice', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    actingAs($owner);

    get("/langganan/tagihan/{$invoice->id}/bayar")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Pay')
            ->where('invoice.amount', 100000)
            ->where('gateway.is_simulated', true));

    post("/langganan/tagihan/{$invoice->id}/bayar", ['channel' => 'va_bca'])->assertRedirect();

    $attempt = PaymentAttempt::first();

    expect($attempt->invoice_id)->toBe($invoice->id)
        ->and($attempt->tenant_id)->toBe($invoice->tenant_id)
        ->and($attempt->status)->toBe(PaymentAttempt::STATUS_PENDING)
        ->and($attempt->payload['type'])->toBe('virtual_account')
        // Tagihannya belum tersentuh — menerbitkan instruksi bukan membayar.
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('a second request reuses the open instruction instead of issuing another', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    actingAs($owner);
    post("/langganan/tagihan/{$invoice->id}/bayar", ['channel' => 'qris']);
    post("/langganan/tagihan/{$invoice->id}/bayar", ['channel' => 'va_bni']);

    // Nomor VA kedua untuk tagihan yang sama adalah cara termudah membuat
    // tenant mentransfer ke nomor yang sudah tidak ditunggu siapa-siapa.
    expect(PaymentAttempt::count())->toBe(1)
        ->and(PaymentAttempt::first()->channel)->toBe('qris');
});

test('an expired instruction no longer blocks a new one', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    PaymentAttempt::factory()->expired()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    actingAs($owner);
    post("/langganan/tagihan/{$invoice->id}/bayar", ['channel' => 'qris']);

    expect(PaymentAttempt::count())->toBe(2);
});

test('an unknown channel is refused', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    actingAs($owner);
    post("/langganan/tagihan/{$invoice->id}/bayar", ['channel' => 'gopay'])
        ->assertSessionHasErrors('channel');

    expect(PaymentAttempt::count())->toBe(0);
});

test('another tenant invoice is forbidden, and so is its instruction', function () {
    ['owner' => $owner] = paymentContext();
    ['invoice' => $foreignInvoice] = paymentContext();

    $foreignAttempt = PaymentAttempt::factory()->create([
        'invoice_id' => $foreignInvoice->id,
        'tenant_id' => $foreignInvoice->tenant_id,
    ]);

    actingAs($owner);

    get("/langganan/tagihan/{$foreignInvoice->id}/bayar")->assertForbidden();
    get("/langganan/pembayaran/{$foreignAttempt->id}")->assertForbidden();
});

test('a cashier cannot start a payment', function () {
    ['cashier' => $cashier, 'invoice' => $invoice] = paymentContext();

    actingAs($cashier);

    // Digerbang `role:owner` di rutenya, sama seperti unggah bukti bayar —
    // staf tidak menyelesaikan kewajiban komersial usaha tempatnya bekerja.
    get("/langganan/tagihan/{$invoice->id}/bayar")->assertForbidden();
});

// ── Webhook: keaslian ────────────────────────────────────────────────────────

test('a callback with a bad signature is refused and settles nothing', function () {
    ['invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    sendCallback([
        'external_id' => $attempt->external_id,
        'status' => 'paid',
        'amount' => 100000,
    ], signature: 'tanda-tangan-karangan')->assertForbidden();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PENDING);
});

test('a callback without any signature is refused', function () {
    ['invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    postJson('/webhook/pembayaran/fake', [
        'external_id' => $attempt->external_id,
        'status' => 'paid',
        'amount' => 100000,
    ])->assertForbidden();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('an unknown gateway is a 404', function () {
    postJson('/webhook/pembayaran/sumopod', [])->assertNotFound();
});

// ── Webhook: melunasi ────────────────────────────────────────────────────────

test('a signed paid callback settles through the one settlement door', function () {
    ['tenant' => $tenant, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    sendCallback([
        'external_id' => $attempt->external_id,
        'status' => 'paid',
        'amount' => 100000,
    ])->assertOk();

    $settled = $invoice->fresh();

    expect($settled->status)->toBe(Invoice::STATUS_PAID)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        // Aturan periode dan penguncian harga ikut berlaku, karena jalurnya
        // sama persis dengan pemeriksaan manual pemilik SaaS.
        ->and((float) $settled->subscription->price_locked)->toBe(100000.0)
        // Pelunasan tiruan TIDAK boleh bisa menyamar sebagai uang sungguhan.
        ->and($settled->settled_via)->toBe(InvoiceSettlement::SOURCE_GATEWAY_FAKE)
        ->and($settled->verified_by)->toBeNull()
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PAID);
});

test('the same notification twice settles once, and both are answered 200', function () {
    ['invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    $payload = ['external_id' => $attempt->external_id, 'status' => 'paid', 'amount' => 100000];

    sendCallback($payload)->assertOk();
    // 200, bukan galat: penyedia yang menerima galat akan mengulang selamanya
    // sesuatu yang tidak akan pernah berubah.
    sendCallback($payload)->assertOk();

    expect(PlatformAuditLog::where('action', 'payments.settled')->count())->toBe(1);
});

test('a paid callback for an unknown transaction is ignored, not an error', function () {
    sendCallback(['external_id' => 'FAKE-TIDAK-ADA', 'status' => 'paid', 'amount' => 100000])->assertOk();
});

// ── Webhook: yang menahan pelunasan ──────────────────────────────────────────

test('an amount that does not match refuses to settle', function () {
    ['tenant' => $tenant, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    sendCallback([
        'external_id' => $attempt->external_id,
        'status' => 'paid',
        'amount' => 90000,
    ])->assertOk();

    // Melunasi kekurangan bayar berarti menutup selisihnya dari uang sendiri
    // tanpa seorang pun memutuskannya.
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_GRACE)
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_MISMATCH)
        ->and(PlatformAuditLog::where('action', 'payments.mismatch')->exists())->toBeTrue();
});

test('an expired instruction cannot be settled, and leaves a trail', function () {
    ['invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->expired()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    sendCallback([
        'external_id' => $attempt->external_id,
        'status' => 'paid',
        'amount' => 100000,
    ])->assertOk();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID)
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_EXPIRED)
        // Uangnya mungkin benar-benar masuk. Yang memutuskan nasibnya adalah
        // orang, dan jejak inilah yang membuat keputusan itu mungkin.
        ->and(PlatformAuditLog::where('action', 'payments.late')->exists())->toBeTrue();
});

test('a failed callback records the failure and settles nothing', function () {
    ['invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    sendCallback([
        'external_id' => $attempt->external_id,
        'status' => 'failed',
        'amount' => 0,
    ])->assertOk();

    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_FAILED)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('an upgrade invoice is payable too, and only grants its seats', function () {
    ['invoice' => $invoice] = paymentContext();

    $invoice->update([
        'kind' => Invoice::KIND_UPGRADE,
        'grants_seats' => 5,
        'previous_seats' => 3,
    ]);

    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    $before = $invoice->subscription;

    sendCallback([
        'external_id' => $attempt->external_id,
        'status' => 'paid',
        'amount' => 100000,
    ])->assertOk();

    $subscription = $invoice->fresh()->subscription;

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($subscription->seats)->toBe(5)
        // Upgrade hanya menambah seat: ia TIDAK memperpanjang periode dan TIDAK
        // mengunci ulang tarif bulanan. Biaya sekali-bayar untuk kasir tambahan
        // bukan harga langganan, dan menukar keduanya membuat tagihan bulan
        // depan salah.
        ->and($subscription->price_locked)->toEqual($before->price_locked)
        ->and($subscription->current_period_end->toDateString())->toBe($before->current_period_end->toDateString());
});

// ── Panel peragaan ───────────────────────────────────────────────────────────

test('the demo panel settles only through the webhook path', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    actingAs($owner);
    post("/langganan/pembayaran/{$attempt->id}/peragakan", ['outcome' => 'paid'])
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        ->and($invoice->fresh()->settled_via)->toBe(InvoiceSettlement::SOURCE_GATEWAY_FAKE)
        // Buktinya bahwa ia benar-benar lewat webhook, bukan memanggil
        // `settle()` sendiri: jejak yang ditinggalkan pemroses webhook ada.
        ->and(PlatformAuditLog::where('action', 'payments.settled')->exists())->toBeTrue();
});

test('the demo panel can demonstrate an underpayment', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    actingAs($owner);
    post("/langganan/pembayaran/{$attempt->id}/peragakan", ['outcome' => 'underpaid']);

    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_MISMATCH)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('the demo panel refuses an outcome it does not know', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    actingAs($owner);
    post("/langganan/pembayaran/{$attempt->id}/peragakan", ['outcome' => 'lunas-saja'])
        ->assertSessionHasErrors('outcome');

    expect($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PENDING);
});

// ── Pelunasan otomatis (peragaan tanpa menekan tombol) ───────────────────────

test('the instruction page carries the auto-settle delay', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    config(['subscription.payment.fake.auto_settle_seconds' => 8]);

    actingAs($owner);
    get("/langganan/pembayaran/{$attempt->id}")
        ->assertInertia(fn (Assert $page) => $page->where('gateway.auto_settle_seconds', 8));
});

test('setting the delay to zero leaves the page waiting for a button', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    config(['subscription.payment.fake.auto_settle_seconds' => 0]);

    actingAs($owner);
    get("/langganan/pembayaran/{$attempt->id}")
        ->assertInertia(fn (Assert $page) => $page->where('gateway.auto_settle_seconds', 0));
});

test('a negative delay is read as disabled, not as an instant payment', function () {
    config(['subscription.payment.fake.auto_settle_seconds' => -5]);

    expect(app(FakeGateway::class)->autoSettleSeconds())->toBe(0);
});

test('the auto-settle callback is the same signed notification as the panel sends', function () {
    ['tenant' => $tenant, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'amount' => 100000,
    ]);

    // Yang dikirim halaman setelah tenggatnya lewat dibangun driver, bukan
    // controller — jadi inilah badan yang sama persis dengan yang akan dikirim
    // penyedia sungguhan.
    $callback = app(FakeGateway::class)->callbackRequest($attempt->fresh(), 'paid');

    app(App\Http\Controllers\Billing\PaymentWebhookController::class)->handle($callback, 'fake');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PAID);
});

test('a gateway that is not simulated never reports an auto-settle delay', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    // Tagihan yang dulu dibayar lewat penyedia lain tidak boleh menghidupkan
    // kembali pelunasan otomatis hanya karena drivernya kini tiruan.
    $attempt = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'gateway' => 'sumopod',
    ]);

    actingAs($owner);
    get("/langganan/pembayaran/{$attempt->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('gateway.is_simulated', false)
            ->where('gateway.auto_settle_seconds', 0));
});

// ── Gerbang produksi ─────────────────────────────────────────────────────────

test('the fake driver refuses to be resolved in production', function () {
    $manager = app(PaymentGatewayManager::class);

    expect($manager->default())->toBeInstanceOf(FakeGateway::class);

    app()->detectEnvironment(fn () => 'production');

    // Bukan "sebaiknya tidak dipakai": gagal keras, sebelum satu baris pun
    // jalan. Diam-diam jatuh ke jalur manual jauh lebih buruk — halamannya
    // tetap terbuka, tombolnya tetap ada, dan tidak ada yang tahu bahwa yang
    // barusan "lunas" tak pernah dibayar.
    expect(fn () => $manager->default())->toThrow(RuntimeException::class)
        ->and($manager->has('fake'))->toBeFalse()
        ->and(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});

test('the webhook of a simulated gateway does not exist in production', function () {
    ['invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->create(['invoice_id' => $invoice->id, 'tenant_id' => $invoice->tenant_id]);

    [$body, $headers] = signedCallback([
        'external_id' => $attempt->external_id,
        'status' => 'paid',
        'amount' => 100000,
    ]);

    app()->detectEnvironment(fn () => 'production');

    // 404, bukan 403: di produksi alamat ini sebaiknya tidak terlihat pernah
    // ada. Penjaga CSRF ikut hidup di lingkungan itu, jadi rutenya juga harus
    // benar-benar dikecualikan — yang diuji di sini keduanya sekaligus.
    postJson('/webhook/pembayaran/fake', json_decode($body, true), $headers)->assertNotFound();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

// ── Bayar sekali jalan dari modal ───────────────────────────────

test('an owner pays an invoice from the modal by confirming with their password', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'invoice' => $invoice] = paymentContext();

    actingAs($owner);

    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", [
        'channel' => 'qris',
        'password' => 'password',
    ])->assertRedirect();

    $attempt = PaymentAttempt::first();

    // Satu pintu, satu jalur: tagihannya lunas, percobaannya ikut tertandai,
    // dan jejaknya tertulis — ketiganya milik `PaymentWebhookController`, yang
    // berarti pelunasannya memang lewat sana dan bukan lewat jalan pintas.
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($attempt->fresh()->status)->toBe(PaymentAttempt::STATUS_PAID)
        ->and($attempt->fresh()->paid_at)->not->toBeNull()
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        ->and(PlatformAuditLog::where('action', 'payments.settled')->exists())->toBeTrue();
});

test('a wrong password pays nothing', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    actingAs($owner);

    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", [
        'channel' => 'qris',
        'password' => 'bukan-kata-sandinya',
    ])->assertSessionHasErrors('password');

    // Tidak ada instruksi yang terbit sama sekali: kata sandi diperiksa sebelum
    // apa pun dibuat, sehingga menebak-nebak tidak meninggalkan tumpukan nomor
    // transaksi yang menganggur.
    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID)
        ->and(PaymentAttempt::count())->toBe(0);
});

test('the modal reuses an open instruction instead of issuing a second one', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    $existing = PaymentAttempt::factory()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
        'channel' => 'va_bca',
        'amount' => $invoice->amount,
    ]);

    actingAs($owner);

    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", [
        'channel' => 'qris',
        'password' => 'password',
    ])->assertRedirect();

    expect(PaymentAttempt::count())->toBe(1)
        ->and($existing->fresh()->status)->toBe(PaymentAttempt::STATUS_PAID)
        ->and($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID);
});

test('an invoice that is already paid is not paid twice', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $invoice->update(['status' => Invoice::STATUS_PAID]);

    actingAs($owner);

    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", [
        'channel' => 'qris',
        'password' => 'password',
    ])->assertSessionHas('error');

    expect(PaymentAttempt::count())->toBe(0);
});

test('the modal refuses a channel the gateway does not offer', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    actingAs($owner);

    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", [
        'channel' => 'transfer-ke-rekening-saya',
        'password' => 'password',
    ])->assertSessionHasErrors('channel');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('a cashier cannot pay from the modal, and neither can another tenant', function () {
    ['cashier' => $cashier, 'invoice' => $invoice] = paymentContext();
    ['owner' => $stranger] = paymentContext();

    actingAs($cashier);
    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", ['channel' => 'qris', 'password' => 'password'])
        ->assertForbidden();

    actingAs($stranger);
    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", ['channel' => 'qris', 'password' => 'password'])
        ->assertForbidden();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('the modal has no address once a real provider is installed', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();

    // Penyedia sungguhan tidak punya "bayar dengan kata sandi". Alamatnya harus
    // ikut hilang, bukan sekadar tombolnya berhenti dirender — halaman boleh
    // berubah, rute yang bisa melunasi tagihan tanpa uang tidak boleh menunggu
    // di belakangnya.
    app()->bind(PaymentGateway::class, fn () => new class implements PaymentGateway
    {
        public function key(): string
        {
            return 'sumopod';
        }

        public function settlementSource(): string
        {
            return 'gateway_sumopod';
        }

        public function availableChannels(): array
        {
            return [['code' => 'qris', 'label' => 'QRIS', 'hint' => '']];
        }

        public function createCharge(Invoice $invoice, string $channel): PaymentAttempt
        {
            throw new RuntimeException('tidak dipakai dalam pengujian ini');
        }

        public function verifyCallback(Illuminate\Http\Request $request): App\Services\Billing\Gateways\CallbackResult
        {
            throw new RuntimeException('tidak dipakai dalam pengujian ini');
        }
    });

    actingAs($owner);

    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", ['channel' => 'qris', 'password' => 'password'])
        ->assertNotFound();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('a suspended tenant can pay from the modal too', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

    actingAs($owner);

    // Rutenya bernama `billing.*` justru supaya ia lolos EnsureSubscriptionActive:
    // tenant yang ditangguhkan adalah yang paling butuh pintu ini.
    post("/langganan/tagihan/{$invoice->id}/bayar-cepat", ['channel' => 'qris', 'password' => 'password'])
        ->assertRedirect();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE);
});

test('the billing page carries the channels the modal renders', function () {
    ['owner' => $owner] = paymentContext();

    actingAs($owner);

    // Tanpa ini modal harus mengunjungi halaman lain dulu untuk tahu kanal apa
    // saja yang ada — dan sekali ia berpindah halaman, ia bukan modal lagi.
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->has('payment.channels', 5)
        ->where('payment.channels.0.code', 'qris'));
});

// ── Permukaannya ─────────────────────────────────────────────────────────────

test('the billing page offers the payment path', function () {
    ['owner' => $owner] = paymentContext();

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('payment.enabled', true)
        ->where('payment.is_simulated', true));
});

test('the instruction page shows the state, and a paid one stops asking for money', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->paid()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
    ]);

    actingAs($owner);
    get("/langganan/pembayaran/{$attempt->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/PaymentInstruction')
            ->where('attempt.status', PaymentAttempt::STATUS_PAID));
});

test('an expired instruction reads as expired even before anyone tells us', function () {
    ['owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $attempt = PaymentAttempt::factory()->expired()->create([
        'invoice_id' => $invoice->id,
        'tenant_id' => $invoice->tenant_id,
    ]);

    actingAs($owner);

    // Basis datanya masih menulis `pending` — tidak ada penjadwal yang
    // mengubahnya. Menampilkan "menunggu pembayaran" untuk nomor VA yang sudah
    // mati adalah berbohong kepada orang yang sedang memegang ponselnya.
    expect($attempt->status)->toBe(PaymentAttempt::STATUS_PENDING);

    get("/langganan/pembayaran/{$attempt->id}")
        ->assertInertia(fn (Assert $page) => $page->where('attempt.status', PaymentAttempt::STATUS_EXPIRED));
});

// ── Tenant yang ditangguhkan tetap punya jalan keluar ────────────────────────

test('a suspended tenant can still reach the payment path', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'invoice' => $invoice] = paymentContext();
    $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

    actingAs($owner);

    // Menutup jalan keluar dari penangguhan berarti tenant tidak akan pernah
    // bisa keluar darinya, termasuk dengan membayar.
    get("/langganan/tagihan/{$invoice->id}/bayar")->assertOk();
    post("/langganan/tagihan/{$invoice->id}/bayar", ['channel' => 'qris'])->assertRedirect();

    expect(PaymentAttempt::count())->toBe(1);
});
