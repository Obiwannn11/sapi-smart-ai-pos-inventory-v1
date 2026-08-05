<?php

use App\Models\Invoice;
use App\Models\PlatformAuditLog;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\InvoiceSettlement;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Pelunasan peragaan — `[BL-045]` butir (2).
 *
 * Yang paling penting diuji di sini bukan bahwa tombolnya bekerja, melainkan
 * bahwa ia TIDAK ADA di luar dua gerbangnya. Jalur yang melunasi tagihan tanpa
 * bukti adalah persis lubang yang `provisional_blocked` dibangun untuk menutup.
 */

/**
 * @return array{tenant: Tenant, owner: User, cashier: User, invoice: Invoice}
 */
function simulationContext(bool $isDemo = true): array
{
    $tenant = Tenant::factory()->create([
        'status' => Tenant::STATUS_GRACE,
        'is_demo' => $isDemo,
    ]);

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

// ── Gerbangnya ───────────────────────────────────────────────────────────────

test('a non-demo tenant gets a 404, not a 403', function () {
    ['owner' => $owner, 'invoice' => $invoice] = simulationContext(isDemo: false);

    actingAs($owner);

    // 404 disengaja: menjawab "terlarang" memberi tahu penanyanya bahwa ada
    // sesuatu di sini yang bisa dibuka dalam keadaan lain.
    post("/langganan/tagihan/{$invoice->id}/simulasi-bayar")->assertNotFound();

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

test('production closes the door even for a demo tenant', function () {
    ['tenant' => $tenant] = simulationContext();
    $settlement = app(InvoiceSettlement::class);

    // Penanda `is_demo` ikut terbawa bila basis data peragaan pernah disalin ke
    // produksi. Syarat lingkunganlah yang membuat jalur ini tidak pernah ADA
    // di sana, bukan sekadar sulit dicapai.
    expect($settlement->canSimulate($tenant))->toBeTrue();

    app()->detectEnvironment(fn () => 'production');

    expect($settlement->canSimulate($tenant))->toBeFalse();

    // Diuji di tingkat gerbangnya, bukan lewat request: berpindah ke
    // lingkungan `production` sekalian menyalakan penjaga CSRF, sehingga
    // request akan tertolak 419 sebelum gerbang ini sempat dinilai — lulus
    // karena alasan yang sama sekali berbeda dari yang sedang diuji.
});

test('a cashier cannot settle anything, demo or not', function () {
    ['cashier' => $cashier, 'invoice' => $invoice] = simulationContext();

    actingAs($cashier);

    // Digerbang `role:owner` di rutenya, sama seperti unggah bukti bayar —
    // staf tidak menyelesaikan kewajiban komersial usaha tempatnya bekerja.
    post("/langganan/tagihan/{$invoice->id}/simulasi-bayar")->assertForbidden();
});

test('a demo tenant cannot settle another tenant invoice', function () {
    ['owner' => $owner] = simulationContext();
    ['invoice' => $foreignInvoice] = simulationContext();

    actingAs($owner);

    post("/langganan/tagihan/{$foreignInvoice->id}/simulasi-bayar")->assertForbidden();

    expect($foreignInvoice->fresh()->status)->toBe(Invoice::STATUS_UNPAID);
});

// ── Apa yang terjadi ketika ia memang boleh ──────────────────────────────────

test('simulating a payment restores access through the one settlement door', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'invoice' => $invoice] = simulationContext();

    actingAs($owner);
    post("/langganan/tagihan/{$invoice->id}/simulasi-bayar")->assertSessionHas('success');

    $settled = $invoice->fresh();

    expect($settled->status)->toBe(Invoice::STATUS_PAID)
        // Inilah yang diminta catatan: refresh, lalu normal kembali.
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        // Aturan periode & penguncian harga ikut berlaku, karena jalurnya sama
        // persis dengan pemeriksaan manual pemilik SaaS.
        ->and((float) $settled->subscription->price_locked)->toBe(100000.0)
        ->and($settled->settled_via)->toBe(InvoiceSettlement::SOURCE_SIMULATION)
        // `verified_by` sengaja null: kolomnya menjawab "siapa yang memeriksa",
        // dan tidak ada yang memeriksa apa pun di sini.
        ->and($settled->verified_by)->toBeNull();
});

test('the shortcut always leaves a sensitive trail', function () {
    ['owner' => $owner, 'invoice' => $invoice] = simulationContext();

    actingAs($owner);
    post("/langganan/tagihan/{$invoice->id}/simulasi-bayar");

    $logged = PlatformAuditLog::where('action', 'invoices.simulate')->first();

    // Sebuah tagihan berpindah ke lunas tanpa seorang pun memeriksa bukti —
    // justru itu yang paling perlu meninggalkan jejak.
    expect($logged)->not->toBeNull()
        ->and($logged->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($logged->meta['by_user_id'])->toBe($owner->id);
});

test('an already paid invoice is refused, not settled twice', function () {
    ['owner' => $owner, 'invoice' => $invoice] = simulationContext();
    $invoice->update(['status' => Invoice::STATUS_PAID]);

    actingAs($owner);
    post("/langganan/tagihan/{$invoice->id}/simulasi-bayar")->assertSessionHas('error');
});

// ── Permukaannya ─────────────────────────────────────────────────────────────

test('the billing page only offers the button to a demo tenant', function () {
    ['owner' => $owner] = simulationContext();

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page->where('simulation.enabled', true));
});

test('an ordinary tenant is never told the button exists', function () {
    ['owner' => $owner] = simulationContext(isDemo: false);

    actingAs($owner);
    get('/langganan')->assertInertia(fn (Assert $page) => $page->where('simulation.enabled', false));
});

// ── Jalur pemilik SaaS tetap utuh setelah dipindah ke service ────────────────

test('the platform verify path still settles through the same door', function () {
    ['tenant' => $tenant, 'invoice' => $invoice] = simulationContext();
    $platformUser = App\Models\PlatformUser::factory()->withAllModules()->create();

    actingAs($platformUser, 'platform');
    post("/platform/invoices/{$invoice->id}/verify")->assertSessionHas('success');

    $settled = $invoice->fresh();

    expect($settled->status)->toBe(Invoice::STATUS_PAID)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        ->and($settled->settled_via)->toBe(InvoiceSettlement::SOURCE_PLATFORM_VERIFY)
        // Di sini justru sebaliknya: ada orang yang memeriksa, dan namanya
        // tercatat.
        ->and($settled->verified_by)->toBe($platformUser->id);
});
