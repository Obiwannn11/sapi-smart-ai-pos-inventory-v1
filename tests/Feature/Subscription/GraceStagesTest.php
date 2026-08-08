<?php

use App\Models\CashDrawer;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Models\PaymentMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Tangga masa tenggang — `[BL-054]`.
 *
 * Sampai 2026-08-07 masa tenggang punya satu bentuk saja: seluruh permintaan
 * non-GET ditolak sejak hari pertama, artinya kasir mati dan toko tidak bisa
 * berjualan. Keputusan pemilik menggantinya dengan tiga tahap — halus 1–14,
 * mengganggu 15–19, menulis dicabut 20–30 — dan berkas ini yang menjaganya.
 *
 * Angkanya TIDAK ditulis ulang sebagai literal di sini. Semuanya dibaca dari
 * `SubscriptionService`, yang membacanya dari config: test yang mengunci angka
 * 20 akan berubah jadi merah pada hari kebijakan komersialnya diubah, padahal
 * yang seharusnya dijaga adalah bentuk tangganya, bukan tinggi anak tangganya.
 */

/**
 * Tenant di masa tenggang, pada hari tenggat ke-$graceDay.
 *
 * @return array{tenant: Tenant, owner: User, cashier: User}
 */
function graceContext(int $graceDay): array
{
    $tenant = Tenant::factory()->inGrace()->create();

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->subDays($graceDay)->toDateString(),
    ]);

    return [
        'tenant' => $tenant->fresh(),
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'cashier' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']),
    ];
}

/**
 * Kelengkapan minimal supaya POS bisa dibuka dan sebuah checkout bisa lolos.
 *
 * @return array<string, mixed>
 */
function graceCheckoutPayload(Tenant $tenant, User $cashier): array
{
    $product = Product::factory()->create(['tenant_id' => $tenant->id]);
    $variant = ProductVariant::factory()->create([
        'product_id' => $product->id,
        'price' => 20000,
        'stock' => 50,
    ]);
    $cash = PaymentMethod::factory()->create(['tenant_id' => $tenant->id, 'type' => 'cash']);

    CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $cashier->id,
        'closed_at' => null,
    ]);

    return [
        'items' => [[
            'variant_id' => $variant->id,
            'variant_name' => 'Kopi Susu - Reguler',
            'qty' => 1,
            'unit_price' => 20000,
            'modifiers' => [],
            'notes' => null,
        ]],
        'payments' => [[
            'payment_method_id' => $cash->id,
            'amount' => 20000,
        ]],
        'client_uuid' => (string) Str::uuid(),
    ];
}

// ── Umur tenggat dan tahapnya ──────────────────────────────────────────────

test('hari pertama tenggat adalah hari SETELAH periode berakhir', function () {
    // Hari periodenya berakhir masih hari terakhir yang dibayar. Menghitungnya
    // sebagai hari tenggat pertama akan menggeser seluruh tangga sehari lebih
    // awal, termasuk tanggal penangguhannya.
    ['tenant' => $tenant] = graceContext(graceDay: 1);

    expect($tenant->graceDay())->toBe(1)
        ->and($tenant->graceStage())->toBe(Tenant::GRACE_STAGE_SOFT);
});

test('tahapnya berpindah tepat di ambang yang dipasang config', function () {
    $intensive = SubscriptionService::graceIntensiveFromDay();
    $lock = SubscriptionService::graceLockFromDay();

    expect(graceContext($intensive - 1)['tenant']->graceStage())->toBe(Tenant::GRACE_STAGE_SOFT)
        ->and(graceContext($intensive)['tenant']->graceStage())->toBe(Tenant::GRACE_STAGE_INTENSIVE)
        ->and(graceContext($lock - 1)['tenant']->graceStage())->toBe(Tenant::GRACE_STAGE_INTENSIVE)
        ->and(graceContext($lock)['tenant']->graceStage())->toBe(Tenant::GRACE_STAGE_LOCKED);
});

test('menulis baru dicabut di tahap terkunci, bukan sebelumnya', function () {
    expect(graceContext(1)['tenant']->canWrite())->toBeTrue()
        ->and(graceContext(SubscriptionService::graceIntensiveFromDay())['tenant']->canWrite())->toBeTrue()
        ->and(graceContext(SubscriptionService::graceLockFromDay())['tenant']->canWrite())->toBeFalse();
});

test('tenant tenggang tanpa langganan tetap boleh berjualan', function () {
    // Data langganan yang bolong adalah masalah kami. Menutup kasir orang
    // karena masalah kami adalah cara terburuk menemukannya.
    $tenant = Tenant::factory()->inGrace()->create();

    expect($tenant->graceDay())->toBeNull()
        ->and($tenant->graceStage())->toBe(Tenant::GRACE_STAGE_SOFT)
        ->and($tenant->canWrite())->toBeTrue();
});

// ── Penegakannya di middleware ─────────────────────────────────────────────

test('kasir tetap bisa menyimpan transaksi di hari-hari awal tenggat', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = graceContext(graceDay: 1);
    $payload = graceCheckoutPayload($tenant, $cashier);

    actingAs($cashier);

    post('/cashier/transactions', $payload)->assertSessionHasNoErrors();
});

test('kasir berhenti menyimpan begitu tenggat mengunci', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = graceContext(
        graceDay: SubscriptionService::graceLockFromDay(),
    );
    $payload = graceCheckoutPayload($tenant, $cashier);

    actingAs($cashier);

    post('/cashier/transactions', $payload)->assertSessionHas('error');
});

test('layar kasir diganti halaman selesaikan tagihan, bukan dialihkan diam-diam', function () {
    ['tenant' => $tenant, 'cashier' => $cashier] = graceContext(
        graceDay: SubscriptionService::graceLockFromDay(),
    );
    graceCheckoutPayload($tenant, $cashier);

    actingAs($cashier);

    // URL-nya tetap /cashier/pos — pengalihan diam-diam ke halaman langganan
    // membuat pengguna mengira aplikasinya rusak.
    get('/cashier/pos')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Billing/Locked')
            ->where('graceDay', SubscriptionService::graceLockFromDay())
        );
});

test('membaca data lama tidak pernah ikut terkunci', function () {
    ['owner' => $owner] = graceContext(graceDay: SubscriptionService::graceLockFromDay());

    actingAs($owner);

    // Prinsip yang tidak dicabut di tahap mana pun. Riwayat kasir dipilih
    // sengaja: ia satu grup rute dengan layar POS yang barusan dikunci.
    get('/cashier/transactions')->assertOk();
    get('/owner/products')->assertOk();
});

// ── Apa yang dilihat layar ─────────────────────────────────────────────────

test('tahap tenggat ikut dibagikan ke setiap layar', function () {
    ['owner' => $owner] = graceContext(graceDay: 1);

    actingAs($owner);

    // Dulu tenant tenggang hari pertama dan hari kedua puluh mengirim payload
    // yang sama persis, sehingga pita peringatan tidak punya bahan untuk
    // membedakan "belum ada yang dicabut" dari "kasir sudah mati".
    get('/owner/products')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription.stage', Tenant::GRACE_STAGE_SOFT)
        ->where('auth.tenant.subscription.grace_day', 1)
        ->where('auth.tenant.subscription.can_write', true)
        ->where('auth.tenant.subscription.lock_from_day', SubscriptionService::graceLockFromDay())
    );
});

test('instruksi bayar yang masih berlaku mematikan notifikasi, bukan jam tenggatnya', function () {
    ['tenant' => $tenant, 'owner' => $owner] = graceContext(
        graceDay: SubscriptionService::graceLockFromDay(),
    );

    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
    PaymentAttempt::factory()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
    ]);

    actingAs($owner);

    get('/owner/products')->assertInertia(fn (Assert $page) => $page
        // Modalnya padam — menagih orang yang sudah membayar adalah cara
        // tercepat kehilangan mereka.
        ->where('auth.tenant.subscription.payment_pending', true)
        // Tapi tahapnya tidak mundur. Kalau menerbitkan instruksi bayar bisa
        // menunda hari ke-20, menerbitkannya lalu mendiamkannya jadi cara
        // membeli waktu tanpa membayar, berulang kali.
        ->where('auth.tenant.subscription.stage', Tenant::GRACE_STAGE_LOCKED)
        ->where('auth.tenant.subscription.can_write', false)
    );
});

test('instruksi bayar yang sudah kedaluwarsa tidak lagi menyenyapkan apa pun', function () {
    ['tenant' => $tenant, 'owner' => $owner] = graceContext(
        graceDay: SubscriptionService::graceIntensiveFromDay(),
    );

    $invoice = Invoice::factory()->create(['tenant_id' => $tenant->id]);
    PaymentAttempt::factory()->expired()->create([
        'tenant_id' => $tenant->id,
        'invoice_id' => $invoice->id,
    ]);

    actingAs($owner);

    get('/owner/products')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription.payment_pending', false)
    );
});
