<?php

use App\Models\Invoice;
use App\Models\Plan;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\PricingRule;
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
        // Tenant ini tidak cocok dengan satu aturan harga pun, jadi nominalnya
        // menyimpang menurut definisi dan alasannya wajib (`[BL-057]`(a)).
        'amount_reason' => 'Belum ada bracket untuk tenant ini.',
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
        'amount_reason' => 'Belum ada bracket untuk tenant ini.',
    ];

    post('/platform/invoices', $payload);
    post('/platform/invoices', $payload)->assertSessionHas('error');

    expect(Invoice::where('tenant_id', $tenant->id)->count())->toBe(1);
});

// --- Alasan nominal khusus (`[BL-057]`(a)) ---

/**
 * Satu aturan tanpa syarat: ia cocok untuk tenant mana pun, sehingga test di
 * bawah bisa menyatakan "mengikuti aturan" dan "menyimpang" hanya lewat nominal
 * yang dikirim.
 */
function ruleMatchingEveryone(float $price = 10000): PricingRule
{
    return PricingRule::factory()->create(['price' => $price]);
}

test('nominal yang menyimpang dari aturan ditolak bila alasannya kosong', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();
    ruleMatchingEveryone(10000);

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 7500,
        'due_date' => '2026-08-10',
    ])->assertSessionHasErrors('amount_reason');

    expect(Invoice::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

test('spasi saja bukan alasan', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();
    ruleMatchingEveryone(10000);

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 7500,
        'due_date' => '2026-08-10',
        'amount_reason' => '   ',
    ])->assertSessionHasErrors('amount_reason');

    expect(Invoice::where('tenant_id', $tenant->id)->exists())->toBeFalse();
});

test('nominal yang menyimpang diterima bersama alasannya, dan alasannya ikut ke jejak audit', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();
    ruleMatchingEveryone(10000);

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 7500,
        'due_date' => '2026-08-10',
        'amount_reason' => 'Potongan 25% tiga bulan pertama, kesepakatan 5 Agustus.',
    ])->assertSessionHas('success');

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();
    $log = PlatformAuditLog::where('action', 'invoices.create')->firstOrFail();

    expect($invoice->amount_reason)->toBe('Potongan 25% tiga bulan pertama, kesepakatan 5 Agustus.')
        // Aturannya TIDAK ditautkan: harganya tidak keluar dari sana.
        ->and($invoice->pricing_rule_id)->toBeNull()
        ->and($log->meta['follows_rule'])->toBeFalse()
        ->and($log->meta['amount_reason'])->toBe('Potongan 25% tiga bulan pertama, kesepakatan 5 Agustus.');
});

test('nominal yang persis mengikuti aturan tidak dimintai alasan', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();
    $rule = ruleMatchingEveryone(10000);

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 10000,
        'due_date' => '2026-08-10',
    ])->assertSessionHas('success');

    expect(Invoice::where('tenant_id', $tenant->id)->firstOrFail()->pricing_rule_id)->toBe($rule->id);
});

test('alasan yang terlanjur diketik untuk nominal sesuai aturan tidak disimpan', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();
    ruleMatchingEveryone(10000);

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 10000,
        'due_date' => '2026-08-10',
        'amount_reason' => 'Terlanjur diketik lalu nominalnya dikembalikan.',
    ])->assertSessionHas('success');

    // Kalimat yang menjelaskan penyimpangan yang tidak terjadi hanya
    // membingungkan tenant yang membacanya.
    expect(Invoice::where('tenant_id', $tenant->id)->firstOrFail()->amount_reason)->toBeNull();
});

test('alasannya terlihat pemilik SaaS di rincian tenant', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();
    ruleMatchingEveryone(10000);

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 7500,
        'due_date' => '2026-08-10',
        'amount_reason' => 'Potongan pemulihan pascabanjir.',
    ]);

    get("/platform/tenants/{$tenant->id}")->assertInertia(fn (Assert $page) => $page
        ->where('invoices.data.0.amount_reason', 'Potongan pemulihan pascabanjir.'));
});

test('alasannya ikut terbaca tenant di halaman langganannya sendiri', function () {
    ['platformUser' => $platformUser, 'tenant' => $tenant] = platformBillingContext();
    ruleMatchingEveryone(10000);

    actingAs($platformUser, 'platform');
    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => '2026-08',
        'amount' => 7500,
        'due_date' => '2026-08-10',
        'amount_reason' => 'Potongan pemulihan pascabanjir.',
    ]);

    // Inilah butir (b): tagihan yang nominalnya berbeda dari daftar harga tanpa
    // penjelasan adalah pertanyaan yang pasti datang. Yang ditulis pemilik SaaS
    // di formulir tadi dibaca orang yang ditagih.
    actingAs(User::where('tenant_id', $tenant->id)->where('role', 'owner')->firstOrFail());

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('invoices.0.amount_reason', 'Potongan pemulihan pascabanjir.'));
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
        // Periode MENYAMBUNG dari akhir periode sebelumnya, bukan mulai dari
        // hari verifikasi ([BL-030]). Langganan factory berakhir sebulan dari
        // sekarang, jadi periode barunya berakhir dua bulan dari sekarang.
        ->and($subscription->fresh()->current_period_start->toDateString())
        ->toBe(now()->addMonthNoOverflow()->toDateString())
        ->and($subscription->fresh()->current_period_end->toDateString())
        ->toBe(now()->addMonthsNoOverflow(2)->toDateString());
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

// --- Perpindahan paket ---
//
// Sampai `[BL-046]`(2), `plan_id` ditulis sekali seumur hidup langganan dan tak
// pernah berubah lagi — paket kedua bisa dibuat tapi tidak bisa dihuni, dan
// keputusan "tenant beromset tinggi hanya bisa Premium" tidak punya cara
// ditegakkan sama sekali.

test('tenant bisa dipindahkan ke paket lain, dan perpindahannya berjejak', function () {
    ['platformUser' => $platformUser, 'subscription' => $subscription] = platformBillingContext();

    $premium = Plan::factory()->create([
        'name' => 'Premium 1',
        'base_price' => 100000,
        'included_seats' => 3,
        'limits' => [Plan::LIMIT_AI_DAILY => 10],
    ]);

    actingAs($platformUser, 'platform');

    // Paket menentukan tarif, jatah seat, dan kuota AI sekaligus. Perpindahan
    // tanpa alasan tertulis adalah tiga perubahan yang tak satu pun bisa
    // dijelaskan saat tenant menanyakannya.
    put("/platform/subscriptions/{$subscription->id}/plan", ['plan_id' => $premium->id])
        ->assertSessionHasErrors('reason');

    expect($subscription->fresh()->plan_id)->not->toBe($premium->id);

    put("/platform/subscriptions/{$subscription->id}/plan", [
        'plan_id' => $premium->id,
        'reason' => 'Omzetnya melewati batas jalur Harga Adaptif.',
    ])->assertSessionHas('success');

    expect($subscription->fresh()->plan_id)->toBe($premium->id);

    $log = PlatformAuditLog::where('action', 'subscriptions.plan.update')->firstOrFail();

    expect($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($log->meta['before']['plan'])->toBe('Free')
        ->and($log->meta['after']['plan'])->toBe('Premium 1')
        // Batas AI ikut dicatat: "dipindah ke Premium" tidak menjawab kenapa
        // analisis AI tenant tiba-tiba ditolak atau tiba-tiba diperbolehkan.
        ->and($log->meta['before']['ai_daily_limit'])->toBeNull()
        ->and($log->meta['after']['ai_daily_limit'])->toBe(10)
        ->and($log->meta['reason'])->toBe('Omzetnya melewati batas jalur Harga Adaptif.');
});

test('seat tambahan yang sudah dibayar ikut pindah bersama paketnya', function () {
    ['platformUser' => $platformUser, 'subscription' => $subscription] = platformBillingContext();

    // Paket Free menjatah 2; batas 3 berarti 1 seat sudah dibeli. Sejak
    // `[BL-053]` jumlah yang dibeli punya kolomnya sendiri dan tidak lagi
    // disimpulkan dari selisih `seats − included_seats` — angka yang jadi dasar
    // uang tidak boleh bergantung pada pengurangan yang bisa meleset.
    $subscription->update(['purchased_extra_seats' => 1]);

    $premium = Plan::factory()->create(['name' => 'Premium', 'included_seats' => 5]);

    actingAs($platformUser, 'platform')
        ->put("/platform/subscriptions/{$subscription->id}/plan", [
            'plan_id' => $premium->id,
            'reason' => 'Naik paket.',
        ])->assertSessionHas('success');

    // Bukan 5 (jatah paket baru saja — mencabut seat yang sudah dibayar) dan
    // bukan 3 (batas lama disalin apa adanya — menelan jatah paket barunya).
    expect($subscription->fresh()->seats)->toBe(6);
});

test('memindahkan ke paket yang sedang dihuni ditolak tanpa menulis jejak', function () {
    ['platformUser' => $platformUser, 'subscription' => $subscription] = platformBillingContext();

    actingAs($platformUser, 'platform')
        ->put("/platform/subscriptions/{$subscription->id}/plan", [
            'plan_id' => $subscription->plan_id,
            'reason' => 'Salah klik.',
        ])->assertSessionHas('error');

    expect(PlatformAuditLog::where('action', 'subscriptions.plan.update')->count())->toBe(0);
});

test('paket yang sudah dihentikan bukan tujuan perpindahan yang sah', function () {
    ['platformUser' => $platformUser, 'subscription' => $subscription] = platformBillingContext();

    $lama = Plan::factory()->inactive()->create(['name' => 'Promo Lawas']);
    $planSemula = $subscription->plan_id;

    actingAs($platformUser, 'platform')
        ->put("/platform/subscriptions/{$subscription->id}/plan", [
            'plan_id' => $lama->id,
            'reason' => 'Coba paket lama.',
        ])->assertSessionHasErrors('plan_id');

    expect($subscription->fresh()->plan_id)->toBe($planSemula);
});

test('perpindahan paket tertutup bagi pemegang modul pembayaran saja', function () {
    ['subscription' => $subscription] = platformBillingContext();

    $premium = Plan::factory()->create();
    $planSemula = $subscription->plan_id;

    $hanyaTagihan = PlatformUser::factory()->create();
    $hanyaTagihan->modules()->create(['module' => 'payments']);

    actingAs($hanyaTagihan, 'platform')
        ->put("/platform/subscriptions/{$subscription->id}/plan", [
            'plan_id' => $premium->id,
            'reason' => 'coba-coba',
        ])
        ->assertForbidden();

    expect($subscription->fresh()->plan_id)->toBe($planSemula);
});

test('katalog paket hanya ikut bagi pemegang modul langganan', function () {
    ['tenant' => $tenant] = platformBillingContext();

    Plan::factory()->create(['name' => 'Premium']);

    $hanyaTagihan = PlatformUser::factory()->create();
    $hanyaTagihan->modules()->create(['module' => 'payments']);

    // Yang tidak boleh memindahkan tenant tidak perlu menerima daftar tujuannya
    // — tidak terkirim sama sekali, bukan terkirim lalu disembunyikan di Vue.
    actingAs($hanyaTagihan, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertInertia(fn (Assert $page) => $page->where('plans', null)->etc());

    ['platformUser' => $platformUser] = platformBillingContext();

    actingAs($platformUser, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertInertia(fn (Assert $page) => $page->has('plans', 2)->etc());
});
