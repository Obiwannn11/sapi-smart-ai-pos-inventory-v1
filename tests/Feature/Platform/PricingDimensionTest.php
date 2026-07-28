<?php

use App\Models\Invoice;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\PricingRule;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantConsent;
use App\Models\TenantMonthlyMetric;
use App\Models\User;
use App\Services\Pricing\DimensionRegistry;
use App\Services\PricingService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\put;

/**
 * `[BL-015]` — dimensi penetapan harga tidak lagi terbatas omzet & seat.
 *
 * Yang dijaga di sini bukan sekadar "aturan majemuk bisa dibuat", melainkan
 * hal-hal yang paling mudah rusak begitu aturan jadi lebih rumit: penjaga
 * privasi, perilaku gagal-menutup, dan grandfathering.
 */
function platformOwner(): PlatformUser
{
    $user = PlatformUser::factory()->withAllModules()->create();
    actingAs($user, 'platform');

    return $user;
}

/**
 * Tenant jalur subsidi yang omzetnya sudah terhitung dan consent-nya aktif.
 */
function subsidizedTenant(float $revenue = 3_000_000, int $transactions = 100, ?string $businessType = null): Tenant
{
    $tenant = Tenant::factory()->active()->create([
        'pricing_track' => Subscription::TRACK_SUBSIDIZED,
        'business_type' => $businessType,
    ]);

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'pricing_track' => Subscription::TRACK_SUBSIDIZED,
    ]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    TenantConsent::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'type' => TenantConsent::TYPE_SUBSIDIZED,
    ]);

    TenantMonthlyMetric::factory()->create([
        'tenant_id' => $tenant->id,
        'period' => now()->format('Y-m'),
        'revenue' => $revenue,
        'transaction_count' => $transactions,
    ]);

    return $tenant;
}

// --- Pencocokan multi-dimensi ---

test('aturan majemuk hanya cocok bila seluruh syaratnya terpenuhi', function () {
    PricingRule::query()->delete();

    PricingRule::factory()
        ->revenueBetween(0, 5_000_000)
        ->withCondition('business_type', 'eq', 'kuliner')
        ->create(['label' => 'KULINER-KECIL', 'priority' => 10, 'price' => 15_000]);

    $cocok = subsidizedTenant(revenue: 3_000_000, businessType: 'kuliner');
    $salahTipe = subsidizedTenant(revenue: 3_000_000, businessType: 'retail');
    $salahOmzet = subsidizedTenant(revenue: 9_000_000, businessType: 'kuliner');

    $pricing = app(PricingService::class);

    expect($pricing->resolveFor($cocok)['label'])->toBe('KULINER-KECIL')
        ->and($pricing->resolveFor($salahTipe)['label'])->toBeNull()
        ->and($pricing->resolveFor($salahOmzet)['label'])->toBeNull();
});

test('aturan berprioritas tertinggi mendahului aturan yang lebih umum', function () {
    PricingRule::query()->delete();

    // Keduanya cocok untuk tenant di bawah. Yang membedakan hanya prioritas —
    // dan tanpa prioritas, aturan umum harus menuliskan syarat penyangkal untuk
    // tiap pengecualian yang kelak ditambahkan.
    PricingRule::factory()
        ->revenueBetween(0, 5_000_000)
        ->create(['label' => 'UMUM', 'priority' => 0, 'price' => 25_000]);

    PricingRule::factory()
        ->revenueBetween(0, 5_000_000)
        ->withCondition('business_type', 'eq', 'jasa')
        ->create(['label' => 'JASA', 'priority' => 50, 'price' => 12_000]);

    $tenant = subsidizedTenant(revenue: 1_000_000, businessType: 'jasa');

    $resolved = app(PricingService::class)->resolveFor($tenant);

    expect($resolved['label'])->toBe('JASA')
        ->and($resolved['price'])->toBe(12_000.0);
});

test('operator in mencocokkan salah satu dari daftar', function () {
    PricingRule::query()->delete();

    PricingRule::factory()
        ->withCondition('business_type', 'in', 'kuliner, retail')
        ->create(['label' => 'DAGANG', 'priority' => 5, 'price' => 30_000]);

    $pricing = app(PricingService::class);

    expect($pricing->resolveFor(subsidizedTenant(businessType: 'retail'))['label'])->toBe('DAGANG')
        ->and($pricing->resolveFor(subsidizedTenant(businessType: 'jasa'))['label'])->toBeNull();
});

test('aturan tanpa syarat cocok untuk siapa pun', function () {
    PricingRule::query()->delete();

    PricingRule::factory()->create(['label' => 'BAWAAN', 'priority' => 0, 'price' => 50_000]);

    $tenant = Tenant::factory()->active()->create();

    expect(app(PricingService::class)->resolveFor($tenant)['label'])->toBe('BAWAAN');
});

// --- Gagal menutup ---

test('dimensi yang tak bisa dihitung menggugurkan aturan, bukan meloloskannya', function () {
    PricingRule::query()->delete();

    PricingRule::factory()
        ->withCondition('business_type', 'eq', 'kuliner')
        ->create(['label' => 'KULINER', 'priority' => 10, 'price' => 9_000]);

    // Tenant lama: mendaftar sebelum pertanyaan tipe usaha ada.
    $tenant = subsidizedTenant(businessType: null);

    expect(app(PricingService::class)->resolveFor($tenant)['label'])->toBeNull();
});

test('syarat berdimensi tak dikenal tidak pernah cocok', function () {
    PricingRule::query()->delete();

    // Bisa terjadi bila sebuah dimensi dicabut dari katalog setelah aturannya
    // terlanjur dibuat. Aturannya harus mati, bukan berubah jadi cocok untuk
    // semua orang.
    PricingRule::factory()
        ->withCondition('jumlah_outlet', 'gte', '3')
        ->create(['label' => 'MULTI-OUTLET', 'priority' => 99, 'price' => 200_000]);

    expect(app(PricingService::class)->resolveFor(subsidizedTenant())['label'])->toBeNull();
});

// --- Penjaga privasi ---

test('dimensi ber-consent tidak punya nilai untuk tenant jalur normal', function () {
    $tenant = Tenant::factory()->active()->create(['pricing_track' => Subscription::TRACK_NORMAL]);
    Subscription::factory()->create(['tenant_id' => $tenant->id]);

    // Sengaja dibuatkan barisnya untuk membuktikan bahwa yang menahan adalah
    // persetujuannya, bukan sekadar ketiadaan data.
    TenantMonthlyMetric::factory()->create([
        'tenant_id' => $tenant->id,
        'period' => now()->format('Y-m'),
        'revenue' => 4_000_000,
    ]);

    $registry = app(DimensionRegistry::class);

    expect($registry->valueFor($tenant, 'monthly_revenue'))->toBeNull()
        ->and($registry->valueFor($tenant, 'transaction_count'))->toBeNull()
        // Seat dan tipe usaha tidak membuka data bisnis apa pun, jadi keduanya
        // tetap terbaca di jalur normal.
        ->and($registry->valueFor($tenant, 'active_seats'))->not->toBeNull();
});

test('mencabut persetujuan memadamkan dimensi omzet seketika', function () {
    $tenant = subsidizedTenant(revenue: 4_000_000);
    $registry = app(DimensionRegistry::class);

    expect($registry->valueFor($tenant, 'monthly_revenue'))->toBe(4_000_000.0);

    app(\App\Services\ConsentService::class)->revoke($tenant, TenantConsent::TYPE_SUBSIDIZED);

    expect($registry->valueFor($tenant->fresh(), 'monthly_revenue'))->toBeNull();
});

test('konteks harga tidak ikut bocor ke payload platform', function () {
    $tenant = subsidizedTenant(revenue: 7_654_321);
    $subscription = $tenant->subscription;

    Invoice::factory()->create([
        'tenant_id' => $tenant->id,
        'subscription_id' => $subscription->id,
        'pricing_context' => ['monthly_revenue' => 7_654_321.0],
    ]);

    platformOwner();

    $body = $this->get('/platform/invoices')->getContent();

    // Angka omzet hanya boleh terbuka di halaman omzet yang tiap kunjungannya
    // tercatat — bukan menumpang di daftar tagihan.
    expect($body)->not->toContain('7654321');
});

// --- Validasi panel ---

test('operator yang tidak masuk akal untuk dimensi atribut ditolak', function () {
    platformOwner();

    post('/platform/pricing-rules', [
        'label' => 'SALAH',
        'priority' => 0,
        'price' => 10_000,
        'effective_from' => now()->addDay()->toDateString(),
        'conditions' => [
            ['dimension' => 'business_type', 'operator' => 'gte', 'value' => 'kuliner'],
        ],
    ])->assertSessionHasErrors('conditions.0.operator');
});

test('nilai di luar pilihan dimensi atribut ditolak', function () {
    platformOwner();

    post('/platform/pricing-rules', [
        'label' => 'SALAH',
        'priority' => 0,
        'price' => 10_000,
        'effective_from' => now()->addDay()->toDateString(),
        'conditions' => [
            ['dimension' => 'business_type', 'operator' => 'eq', 'value' => 'peternakan'],
        ],
    ])->assertSessionHasErrors('conditions.0.value');
});

test('satu nilai salah di tengah daftar in tetap tertangkap', function () {
    platformOwner();

    post('/platform/pricing-rules', [
        'label' => 'SALAH',
        'priority' => 0,
        'price' => 10_000,
        'effective_from' => now()->addDay()->toDateString(),
        'conditions' => [
            ['dimension' => 'business_type', 'operator' => 'in', 'value' => 'kuliner, peternakan'],
        ],
    ])->assertSessionHasErrors('conditions.0.value');
});

test('nilai bukan angka untuk dimensi metrik ditolak', function () {
    platformOwner();

    post('/platform/pricing-rules', [
        'label' => 'SALAH',
        'priority' => 0,
        'price' => 10_000,
        'effective_from' => now()->addDay()->toDateString(),
        'conditions' => [
            ['dimension' => 'monthly_revenue', 'operator' => 'gte', 'value' => 'banyak'],
        ],
    ])->assertSessionHasErrors('conditions.0.value');
});

test('dimensi di luar katalog ditolak', function () {
    platformOwner();

    post('/platform/pricing-rules', [
        'label' => 'SALAH',
        'priority' => 0,
        'price' => 10_000,
        'effective_from' => now()->addDay()->toDateString(),
        'conditions' => [
            ['dimension' => 'laba_bersih', 'operator' => 'gte', 'value' => '100'],
        ],
    ])->assertSessionHasErrors('conditions.0.dimension');
});

test('aturan berkelompok sama menggantikan pendahulunya, bukan menambah pesaing', function () {
    PricingRule::query()->delete();

    PricingRule::factory()
        ->revenueBetween(0)
        ->create(['label' => 'A', 'priority' => 0, 'price' => 10_000, 'effective_from' => now()->subYear()->toDateString()]);

    PricingRule::factory()
        ->revenueBetween(0)
        ->create(['label' => 'A', 'priority' => 0, 'price' => 20_000, 'effective_from' => now()->subDay()->toDateString()]);

    expect(app(PricingService::class)->bracketFor(1_000_000)['price'])->toBe(20_000.0);
});

// --- Penerbitan tagihan ---

test('usulan tarif dikembalikan untuk tenant dan periode', function () {
    PricingRule::query()->delete();
    PricingRule::factory()->revenueBetween(0, 5_000_000)->create(['label' => 'B', 'price' => 25_000]);

    $tenant = subsidizedTenant(revenue: 3_000_000);

    platformOwner();

    $this->getJson("/platform/invoices/suggestion?tenant_id={$tenant->id}&period=".now()->format('Y-m'))
        ->assertOk()
        ->assertJson(['amount' => 25_000, 'label' => 'B', 'matched' => true]);
});

test('tagihan menyimpan aturan pemenang berikut konteksnya', function () {
    PricingRule::query()->delete();
    PricingRule::factory()->revenueBetween(0, 5_000_000)->create(['label' => 'B', 'price' => 25_000]);

    $tenant = subsidizedTenant(revenue: 3_000_000, transactions: 42);

    platformOwner();

    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => now()->format('Y-m'),
        'amount' => 25_000,
        'due_date' => now()->addWeek()->toDateString(),
    ])->assertSessionHas('success');

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    // toEqual, bukan toBe: konteksnya tersimpan sebagai JSON, dan 3000000.0
    // pulang sebagai integer setelah perjalanan itu.
    expect($invoice->pricing_rule_id)->not->toBeNull()
        ->and($invoice->pricing_context['monthly_revenue'])->toEqual(3_000_000)
        ->and($invoice->pricing_context['transaction_count'])->toEqual(42);
});

test('nominal yang ditetapkan sendiri tidak ditautkan ke aturan mana pun', function () {
    PricingRule::query()->delete();
    PricingRule::factory()->revenueBetween(0, 5_000_000)->create(['label' => 'B', 'price' => 25_000]);

    $tenant = subsidizedTenant(revenue: 3_000_000);

    platformOwner();

    post('/platform/invoices', [
        'tenant_id' => $tenant->id,
        'period' => now()->format('Y-m'),
        // Pemilik SaaS memberi potongan; aturannya tidak menghasilkan angka ini.
        'amount' => 15_000,
        'due_date' => now()->addWeek()->toDateString(),
    ])->assertSessionHas('success');

    $invoice = Invoice::where('tenant_id', $tenant->id)->firstOrFail();

    // Jejaknya tidak boleh berbohong bahwa harga ini keluar dari aturan.
    expect($invoice->pricing_rule_id)->toBeNull()
        // Tapi keadaan tenant saat itu tetap dibekukan — justru di sinilah
        // pertanyaan "kenapa dipotong" paling mungkin muncul.
        ->and($invoice->pricing_context['monthly_revenue'])->toEqual(3_000_000);

    $log = PlatformAuditLog::where('action', 'invoices.create')->firstOrFail();

    expect($log->meta['follows_rule'])->toBeFalse();
});

// --- Tipe usaha ---

test('tipe usaha tersimpan saat pendaftaran', function () {
    post('/register', [
        'business_name' => 'Kopi Story',
        'business_type' => 'kuliner',
        'name' => 'Pemilik',
        'email' => 'pemilik@kopistory.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    expect(Tenant::where('name', 'Kopi Story')->firstOrFail()->business_type)->toBe('kuliner');
});

test('tipe usaha di luar pilihan ditolak saat pendaftaran', function () {
    post('/register', [
        'business_name' => 'Ternak Maju',
        'business_type' => 'peternakan',
        'name' => 'Pemilik',
        'email' => 'pemilik@ternak.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ])->assertSessionHasErrors('business_type');
});

test('pendaftaran tetap jalan tanpa memilih tipe usaha', function () {
    post('/register', [
        'business_name' => 'Warung Tanpa Tipe',
        'name' => 'Pemilik',
        'email' => 'pemilik@warung.test',
        'password' => 'rahasia123',
        'password_confirmation' => 'rahasia123',
    ]);

    expect(Tenant::where('name', 'Warung Tanpa Tipe')->firstOrFail()->business_type)->toBeNull();
});

test('tipe usaha bisa dikoreksi dari panel dan tercatat nilai lama barunya', function () {
    $tenant = Tenant::factory()->active()->create(['business_type' => null]);

    platformOwner();

    put("/platform/tenants/{$tenant->id}/business-type", ['business_type' => 'retail'])
        ->assertSessionHas('success');

    expect($tenant->fresh()->business_type)->toBe('retail');

    $log = PlatformAuditLog::where('action', 'tenants.business-type.update')->firstOrFail();

    expect($log->meta['before'])->toBeNull()
        ->and($log->meta['after'])->toBe('retail')
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE);
});
