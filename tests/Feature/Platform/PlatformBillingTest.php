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
use function Pest\Laravel\put;

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
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();

    actingAs($platformUser, 'platform');

    // Langganan dan tagihan kini satu bagian: daftarnya, dan rincian tiap akun
    // yang memuat riwayat tagihannya.
    foreach (['/platform/subscriptions', "/platform/tenants/{$tenant->id}"] as $url) {
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

// --- Rincian akun: langganan & tagihan jadi satu ---

test('rincian akun menyatukan keadaan langganan dengan riwayat tagihannya', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant, 'subscription' => $subscription] = platformBillingContext();

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'period' => '2026-07',
    ]);

    actingAs($platformUser, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants/Show')
            ->where('tenant.name', 'Kopi Story')
            ->where('subscription.seats', 3)
            ->has('invoices.data', 1)
            ->where('invoices.data.0.period', '2026-07')
            // Angka omzetnya TIDAK ikut: membukanya adalah tindakan tersendiri
            // yang punya rute dan barisan auditnya sendiri.
            ->where('revenue', null)
            ->etc()
        );
});

test('rincian akun menyembunyikan tagihan dari pemegang modul langganan saja', function () {
    ['tenant' => $tenant] = platformBillingContext();

    $hanyaLangganan = PlatformUser::factory()->create();
    $hanyaLangganan->modules()->create(['module' => 'subscriptions']);

    actingAs($hanyaLangganan, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertStatus(200)
        // Bukan terkirim lalu disembunyikan di Vue — memang tidak ada isinya.
        ->assertInertia(fn (Assert $page) => $page
            ->where('invoices', null)
            ->where('can.payments', false)
            ->etc()
        );
});

// --- Batas pengguna ---

test('batas pengguna bisa diatur dari panel dan wajib beralasan', function () {
    ['platformUser' => $platformUser, 'subscription' => $subscription] = platformBillingContext();

    actingAs($platformUser, 'platform');

    // Angka baru tanpa alasan tertulis adalah persis hal yang tidak bisa
    // dijelaskan saat tenant menanyakannya.
    put("/platform/subscriptions/{$subscription->id}/seats", ['seats' => 5])
        ->assertSessionHasErrors('reason');

    expect($subscription->fresh()->seats)->toBe(3);

    put("/platform/subscriptions/{$subscription->id}/seats", [
        'seats' => 5,
        'reason' => 'Kesepakatan tambahan kasir di luar aplikasi.',
    ])->assertSessionHas('success');

    expect($subscription->fresh()->seats)->toBe(5);

    $log = PlatformAuditLog::where('action', 'subscriptions.seats.update')->firstOrFail();

    expect($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($log->meta['before'])->toBe(3)
        ->and($log->meta['after'])->toBe(5)
        ->and($log->meta['reason'])->toBe('Kesepakatan tambahan kasir di luar aplikasi.');
});

test('menurunkan batas di bawah pemakaian aktif diizinkan dan tidak mengusir siapa pun', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant, 'subscription' => $subscription] = platformBillingContext();

    User::factory()->count(2)->create(['tenant_id' => $tenant->id, 'is_active' => true]);

    actingAs($platformUser, 'platform');
    put("/platform/subscriptions/{$subscription->id}/seats", [
        'seats' => 1,
        'reason' => 'Koreksi setelah bukti bayar penambahan ditolak.',
    ])->assertSessionHas('success');

    // Yang tertutup adalah penambahan berikutnya, bukan pekerjaan orang yang
    // sedang berjalan — mengikuti alasan yang sama seperti penolakan bukti.
    expect($subscription->fresh()->seats)->toBe(1)
        ->and($tenant->users()->where('is_active', true)->count())->toBe(3)
        ->and($subscription->fresh()->hasSeatAvailable())->toBeFalse();
});

test('batas pengguna tertutup bagi pemegang modul pembayaran saja', function () {
    ['subscription' => $subscription] = platformBillingContext();

    $hanyaTagihan = PlatformUser::factory()->create();
    $hanyaTagihan->modules()->create(['module' => 'payments']);

    actingAs($hanyaTagihan, 'platform')
        ->put("/platform/subscriptions/{$subscription->id}/seats", ['seats' => 9, 'reason' => 'coba-coba'])
        ->assertForbidden();

    expect($subscription->fresh()->seats)->toBe(3);
});
