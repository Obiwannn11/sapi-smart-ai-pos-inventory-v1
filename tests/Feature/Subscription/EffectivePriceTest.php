<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PricingRule;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Services\Billing\InvoiceSettlement;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Tarif nol yang tersimpan di `price_locked` — keputusan 2026-08-10.
 *
 * `price_locked` bertipe desimal, jadi `0.00` bukan `null` dan
 * `price_locked ?? base_price` tidak pernah jatuh ke tarif paket. Tenant
 * `paid-1` yang kolomnya berisi nol karena itu membaca "Rp 0/bulan" di halaman
 * langganannya sementara penerbit menyiapkan tagihan Rp 100.000 — layar yang
 * membantah tagihan, pada satu-satunya halaman yang dibuka tenant untuk
 * mengetahui berapa yang harus ia bayar.
 *
 * Yang diuji di sini dua arah sekaligus, dan keduanya wajib: nol dibaca sebagai
 * kosong, dan harga terkunci yang SUNGGUHAN tetap dihormati. Perbaikan yang
 * hanya menegakkan yang pertama akan diam-diam mencabut grandfathering setiap
 * tenant yang pernah menyepakati tarif di bawah tarif paketnya.
 */

/**
 * Tenant berbayar dengan owner-nya, di paket yang tarifnya bukan nol.
 *
 * Paketnya dibuat factory, bukan diambil dari slug `paid-1`: keempat paket
 * berbayar diisi pemilik SaaS lewat panel, bukan lewat migrasi, jadi basis data
 * test hanya memiliki paket `free`.
 *
 * @return array{tenant: Tenant, owner: User, subscription: Subscription}
 */
function makePricedTenant(float|int|null $priceLocked, float $basePrice = 100_000): array
{
    $tenant = Tenant::factory()->create(['status' => Tenant::STATUS_ACTIVE]);

    $plan = Plan::factory()->create([
        'name' => 'Premium 1',
        'base_price' => $basePrice,
        'included_seats' => 3,
    ]);

    $subscription = Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'plan_id' => $plan->id,
        'price_locked' => $priceLocked,
        'trial_ends_at' => null,
        'current_period_end' => now()->addDays(14)->toDateString(),
    ]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'subscription' => $subscription,
    ];
}

// ── Yang dibaca tenant di layar ──────────────────────────────────────────────

test('tarif terkunci nol tidak dipajang sebagai Rp 0, melainkan tarif paketnya', function () {
    ['owner' => $owner] = makePricedTenant(priceLocked: 0);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.plan_name', 'Premium 1')
        ->where('subscription.effective_price', 100_000)
    );
});

test('tarif terkunci yang sungguhan tetap dihormati, meski di bawah tarif paket', function () {
    // Inilah grandfathering yang benar-benar ada: tenant menyepakati Rp 50.000
    // dan tarif paket naik ke Rp 100.000 sesudahnya. Perbaikan nol tidak boleh
    // ikut menyapu angka ini.
    ['owner' => $owner] = makePricedTenant(priceLocked: 50_000);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.effective_price', 50_000)
    );
});

test('tenant yang memang belum pernah ditagih membaca tarif paketnya', function () {
    ['owner' => $owner] = makePricedTenant(priceLocked: null);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.effective_price', 100_000)
    );
});

test('tenant paket gratis tetap terbaca Rp 0', function () {
    // Nol yang dianggap kosong tidak boleh membesarkan tarif siapa pun: yang
    // memang tidak membayar apa-apa tinggal di paket yang `base_price`-nya juga
    // nol, jadi kedua pembacaannya bertemu di angka yang sama.
    ['owner' => $owner] = makePricedTenant(priceLocked: 0, basePrice: 0);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subscription.effective_price', 0)
    );
});

test('halaman Harga Adaptif menyebut tarif yang sama dengan halaman langganan', function () {
    // Kedua halaman menjawab pertanyaan yang sama, dan sampai hari ini keduanya
    // menghitungnya sendiri-sendiri dengan ungkapan yang disalin. Yang diuji di
    // sini bukan angkanya saja melainkan bahwa keduanya tidak bisa lagi
    // berselisih.
    ['owner' => $owner] = makePricedTenant(priceLocked: 0);

    actingAs($owner);
    get('/langganan/harga-adaptif')->assertInertia(fn (Assert $page) => $page
        ->where('current.price', 100_000)
    );
});

test('perkiraan Harga Adaptif dibandingkan dengan tarif sungguhan, bukan dengan nol', function () {
    // Akibat kedua dari nol yang lolos, dan yang paling merugikan: perkiraan
    // membandingkan tarif bracket dengan tarif berjalan. Terhadap Rp 0 palsu,
    // bracket Rp 25.000 terbaca LEBIH MAHAL — halamannya lalu memberi tahu
    // tenant yang paling butuh keringanan bahwa mengajukannya tidak
    // menguntungkan.
    PricingRule::query()->delete();
    PricingRule::factory()->revenueBetween(0, 5_000_000)->create(['label' => 'KECIL', 'price' => 25_000]);

    ['tenant' => $tenant, 'owner' => $owner] = makePricedTenant(priceLocked: 0);

    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'status' => Transaction::STATUS_COMPLETED,
        'total_amount' => 3_000_000,
        'occurred_at' => now()->startOfMonth()->subMonth()->addDays(5),
    ]);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('subsidy.estimate.current_price', 100_000)
        ->where('subsidy.estimate.price', 25_000)
        ->where('subsidy.estimate.is_cheaper', true)
    );
});

// ── Dari mana nol itu datang ─────────────────────────────────────────────────

test('melunasi tagihan langganan Rp 0 tidak mengunci tarif nol', function () {
    ['subscription' => $subscription] = makePricedTenant(priceLocked: 50_000);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $subscription->tenant_id,
        'subscription_id' => $subscription->id,
        'kind' => Invoice::KIND_SUBSCRIPTION,
        'amount' => 0,
    ]);

    app(InvoiceSettlement::class)->settle($invoice, InvoiceSettlement::SOURCE_PLATFORM_VERIFY);

    // Tarif yang sudah disepakati tidak tersentuh. Yang diputuskan pemilik SaaS
    // saat mengetik tagihan Rp 0 adalah "bulan ini gratis", bukan "tarifnya nol
    // mulai sekarang".
    expect((float) $subscription->fresh()->price_locked)->toBe(50_000.0);
});

test('melunasi tagihan langganan Rp 0 tetap memperpanjang periodenya', function () {
    ['subscription' => $subscription] = makePricedTenant(priceLocked: 50_000);
    $sebelum = $subscription->current_period_end->toDateString();

    $invoice = Invoice::factory()->create([
        'tenant_id' => $subscription->tenant_id,
        'subscription_id' => $subscription->id,
        'kind' => Invoice::KIND_SUBSCRIPTION,
        'amount' => 0,
    ]);

    app(InvoiceSettlement::class)->settle($invoice, InvoiceSettlement::SOURCE_PLATFORM_VERIFY);

    // Penjaganya hanya mencabut penguncian harganya. Perpanjangan periode
    // memang yang diputuskan orang yang menerbitkan tagihan itu, dan mencabut
    // keduanya sekaligus akan meninggalkan tenant di masa tenggang atas tagihan
    // yang sudah lunas.
    expect($subscription->fresh()->current_period_end->toDateString())
        ->not->toBe($sebelum)
        ->and($subscription->fresh()->tenant->status)->toBe(Tenant::STATUS_ACTIVE);
});

test('tagihan bernominal tetap mengunci tarifnya seperti sebelumnya', function () {
    ['subscription' => $subscription] = makePricedTenant(priceLocked: 50_000);

    $invoice = Invoice::factory()->create([
        'tenant_id' => $subscription->tenant_id,
        'subscription_id' => $subscription->id,
        'kind' => Invoice::KIND_SUBSCRIPTION,
        'amount' => 100_000,
    ]);

    app(InvoiceSettlement::class)->settle($invoice, InvoiceSettlement::SOURCE_PLATFORM_VERIFY);

    expect((float) $subscription->fresh()->price_locked)->toBe(100_000.0);
});
