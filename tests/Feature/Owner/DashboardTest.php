<?php

use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->owner = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'owner',
    ]);
});

/**
 * Tagihan milik tenant pengujian, lengkap dengan langganannya.
 */
function dashboardInvoice(Tenant $tenant, array $attributes = []): Invoice
{
    $subscription = app(SubscriptionService::class)->ensureFor($tenant);

    return Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        ...$attributes,
    ]);
}

test('owner can view dashboard', function () {
    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertStatus(200);
});

test('cashier cannot access dashboard', function () {
    $cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);

    $this->actingAs($cashier)
        ->get('/owner/dashboard')
        ->assertStatus(403);
});

// --- Ringkasan langganan (BL-040) ---
// Halaman `/langganan` sebelumnya tidak punya pintu masuk sama sekali;
// ringkasan inilah jalannya, jadi bentuk propnya ikut diuji.

test('dashboard membawa ringkasan langganan tenant', function () {
    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.status', Tenant::STATUS_TRIAL)
            ->where('subscription.track', Subscription::TRACK_NORMAL)
            ->where('subscription.trial_ends_at', now()->addDays(SubscriptionService::trialDays())->toDateString())
            // Tenant baru belum ditagih apa pun. Null, bukan nominal nol —
            // yang ditampilkan berbeda: diam, bukan "Rp 0".
            ->where('subscription.outstanding', null)
        );
});

test('ringkasan menampilkan tagihan terbuka yang jatuh temponya paling dekat', function () {
    // Sengaja dibuat terbalik: yang terbit belakangan justru jatuh tempo lebih
    // dulu, seperti tagihan upgrade yang menyusul di tengah periode. Urutan id
    // akan memilih yang salah.
    dashboardInvoice($this->tenant, [
        'amount' => 90000,
        'due_date' => now()->addDays(20)->toDateString(),
    ]);
    dashboardInvoice($this->tenant, [
        'amount' => 25000,
        'kind' => Invoice::KIND_UPGRADE,
        'due_date' => now()->addDays(3)->toDateString(),
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            // Nominal bulat kehilangan pecahannya saat di-JSON-kan, jadi yang
            // sampai ke Vue memang bilangan bulat.
            ->where('subscription.outstanding.amount', 25000)
            ->where('subscription.outstanding.kind', Invoice::KIND_UPGRADE)
            ->where('subscription.outstanding.status', Invoice::STATUS_UNPAID)
            ->where('subscription.outstanding.due_date', now()->addDays(3)->toDateString())
        );
});

test('tagihan yang buktinya ditolak tetap dihitung terbuka', function () {
    dashboardInvoice($this->tenant, [
        'status' => Invoice::STATUS_REJECTED,
        'rejection_reason' => 'Nominal transfer kurang dari tagihan.',
    ]);

    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.outstanding.status', Invoice::STATUS_REJECTED)
        );
});

test('tagihan lunas tidak muncul sebagai tagihan terbuka', function () {
    dashboardInvoice($this->tenant, ['status' => Invoice::STATUS_PAID, 'paid_at' => now()]);

    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('subscription.outstanding', null));
});

test('tagihan tenant lain tidak bocor ke ringkasan', function () {
    $lain = Tenant::factory()->create();
    dashboardInvoice($lain, ['amount' => 500000]);

    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('subscription.outstanding', null));
});

test('tenant di masa tenggang mendapat tanggal penangguhannya', function () {
    $tenant = Tenant::factory()->readOnly()->create();
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $subscription = app(SubscriptionService::class)->ensureFor($tenant);

    $expected = $subscription->current_period_end
        ->copy()
        ->addDays(SubscriptionService::graceDays())
        ->toDateString();

    $this->actingAs($owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page
            ->where('subscription.status', Tenant::STATUS_GRACE)
            ->where('subscription.suspends_at', $expected)
        );
});

test('tanggal penangguhan kosong selama langganan belum lewat', function () {
    $this->actingAs($this->owner)
        ->get('/owner/dashboard')
        ->assertInertia(fn (Assert $page) => $page->where('subscription.suspends_at', null));
});
