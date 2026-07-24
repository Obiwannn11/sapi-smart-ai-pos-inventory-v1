<?php

use App\Models\Invoice;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Product;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{platformUser: PlatformUser, tenant: Tenant, subscription: Subscription}
 */
function platformBillingContext(): array
{
    $tenant = Tenant::factory()->create(['name' => 'Kopi Story', 'status' => Tenant::STATUS_GRACE]);
    $subscription = Subscription::factory()->seats(3)->create(['tenant_id' => $tenant->id]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    // Data operasional sengaja diisi: halaman langganan & pembayaran tidak
    // boleh membocorkannya lewat jalur mana pun.
    Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ProdukRahasia']);
    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'code' => 'TRXRAHASIA',
        'total_amount' => 987654,
    ]);

    return [
        'platformUser' => PlatformUser::factory()->withAllModules()->create(),
        'tenant' => $tenant,
        'subscription' => $subscription,
    ];
}

// --- Halaman langganan ---

test('halaman langganan menampilkan keterangan komersial saja', function () {
    ['platformUser' => $platformUser] = platformBillingContext();

    actingAs($platformUser, 'platform')
        ->get('/platform/subscriptions')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Subscriptions/Index')
            ->has('subscriptions.data', 1)
            ->has('subscriptions.data.0', fn (Assert $row) => $row
                ->hasAll([
                    'id', 'tenant', 'plan_name', 'pricing_track', 'seats',
                    'seat_high_water', 'price_locked', 'trial_ends_at', 'current_period_end',
                ])
                ->etc()
            )
        );
});

test('tidak ada data operasional yang bocor ke halaman langganan maupun pembayaran', function () {
    ['platformUser' => $platformUser] = platformBillingContext();

    actingAs($platformUser, 'platform');

    foreach (['/platform/subscriptions', '/platform/invoices'] as $url) {
        expect(get($url)->getContent())
            ->not->toContain('ProdukRahasia')
            ->not->toContain('TRXRAHASIA')
            ->not->toContain('987654');
    }
});

test('modul langganan dan pembayaran digerbang izinnya masing-masing', function () {
    ['platformUser' => $platformUser] = platformBillingContext();

    $tanpaModul = PlatformUser::factory()->create();

    actingAs($tanpaModul, 'platform');
    get('/platform/subscriptions')->assertForbidden();
    get('/platform/invoices')->assertForbidden();

    actingAs($platformUser, 'platform');
    get('/platform/subscriptions')->assertStatus(200);
});

// --- Menerbitkan tagihan ---

test('tagihan bisa diterbitkan dan tercatat di jejak audit', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 50000,
        'due_date' => '2026-08-10',
    ])->assertSessionHas('success');

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    expect($invoice->period)->toBe('2026-08')
        ->and($invoice->status)->toBe(Invoice::STATUS_UNPAID)
        ->and(PlatformAuditLog::where('action', 'invoices.create')
            ->where('severity', PlatformAuditLog::SEVERITY_SENSITIVE)
            ->exists())->toBeTrue();
});

test('tagihan ganda untuk periode yang sama ditolak', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();

    actingAs($platformUser, 'platform');

    $payload = [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 50000,
        'due_date' => '2026-08-10',
    ];

    post('/platform/invoices', $payload);
    post('/platform/invoices', $payload)->assertSessionHas('error');

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

// --- Verifikasi ---

test('menerima pembayaran mengaktifkan tenant dan mengunci harganya', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant, 'subscription' => $subscription] = platformBillingContext();

    $invoice = Invoice::factory()->awaitingVerification()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'amount' => 75000,
    ]);

    actingAs($platformUser, 'platform');
    post("/platform/invoices/{$invoice->id}/verify")->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_PAID)
        ->and($invoice->fresh()->verified_by)->toBe($platformUser->id)
        ->and($tenant->fresh()->status)->toBe(Tenant::STATUS_ACTIVE)
        // Harga dikunci dari nominal yang dibayar, bukan dibaca ulang dari
        // tabel tarif — mengubah tarif besok tidak menyentuh kesepakatan ini.
        ->and((float) $subscription->fresh()->price_locked)->toBe(75000.0)
        ->and($subscription->fresh()->current_period_end->toDateString())
        ->toBe(now()->addMonth()->toDateString());
});

test('tagihan yang sudah lunas tidak bisa diverifikasi dua kali', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant, 'subscription' => $subscription] = platformBillingContext();

    $invoice = Invoice::factory()->paid()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
    ]);

    actingAs($platformUser, 'platform');
    post("/platform/invoices/{$invoice->id}/verify")->assertSessionHas('error');
});

// --- Penolakan ---

test('menolak bukti bayar mencatat alasannya tanpa menyentuh akun staf', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant, 'subscription' => $subscription] = platformBillingContext();

    $invoice = Invoice::factory()->awaitingVerification()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
    ]);

    $jumlahAktifSebelum = $tenant->users()->where('is_active', true)->count();

    actingAs($platformUser, 'platform');
    post("/platform/invoices/{$invoice->id}/reject", ['reason' => 'Nominal kurang Rp 15.000.'])
        ->assertSessionHas('success');

    expect($invoice->fresh()->status)->toBe(Invoice::STATUS_REJECTED)
        ->and($invoice->fresh()->rejection_reason)->toContain('15.000')
        // Menonaktifkan akun yang sedang dipakai bekerja jauh lebih merusak
        // kepercayaan daripada menahan penambahan berikutnya.
        ->and($tenant->users()->where('is_active', true)->count())->toBe($jumlahAktifSebelum)
        ->and(PlatformAuditLog::where('action', 'invoices.reject')->exists())->toBeTrue();
});

test('penolakan wajib menyertakan alasan', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant, 'subscription' => $subscription] = platformBillingContext();

    $invoice = Invoice::factory()->awaitingVerification()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
    ]);

    actingAs($platformUser, 'platform');
    post("/platform/invoices/{$invoice->id}/reject", ['reason' => ''])
        ->assertSessionHasErrors('reason');
});
