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

/**
 * Rincian satu tenant — halaman bertab milik modul Daftar Tenant.
 *
 * Yang dijaga di sini bukan tampilannya, melainkan dua hal yang membuatnya
 * pindah dari modul langganan: siapa yang boleh membukanya, dan apa yang ikut
 * terkirim untuk masing-masing pembacanya.
 *
 * @return array{tenant: Tenant, platformUser: PlatformUser}
 */
function tenantDetailContext(): array
{
    $tenant = Tenant::factory()->active()->create([
        'name' => 'Kopi Story',
        'kitchen_queue_enabled' => true,
        'self_order_enabled' => false,
        'ai_enabled' => true,
    ]);

    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    Subscription::factory()->seats(3)->create(['tenant_id' => $tenant->id]);

    // Data operasional sengaja diisi: rincian tenant tidak boleh
    // membocorkannya lewat jalur mana pun.
    Product::factory()->create(['tenant_id' => $tenant->id, 'name' => 'ProdukRahasia']);
    Transaction::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $owner->id,
        'code' => 'TRXRAHASIA',
        'total_amount' => 987654,
    ]);

    return [
        'tenant' => $tenant,
        'platformUser' => PlatformUser::factory()->withAllModules()->create(),
    ];
}

// --- Jalan masuk ---

test('pemegang modul daftar tenant bisa membuka rinciannya, bukan menabrak 403', function () {
    ['tenant' => $tenant] = tenantDetailContext();

    // Persis keadaan yang dulu rusak: staf yang hanya diberi daftar tenant
    // menaut ke halaman yang digerbang modul lain.
    $hanyaTenant = PlatformUser::factory()->create();
    $hanyaTenant->modules()->create(['module' => 'tenants']);

    actingAs($hanyaTenant, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->component('Platform/Tenants/Show')
            ->where('tenant.name', 'Kopi Story')
            // Halamannya terbuka, isinya yang disaring: keterangan komersial
            // tidak ikut terkirim sama sekali — bukan terkirim lalu
            // disembunyikan di Vue.
            ->where('subscription', null)
            ->where('invoices', null)
            ->where('can.tenants', true)
            ->where('can.subscriptions', false)
            ->etc()
        );
});

test('pemegang modul tagihan saja tetap bisa membuka rincian yang sama', function () {
    ['tenant' => $tenant] = tenantDetailContext();

    Invoice::factory()->create(['tenant_id' => $tenant->id, 'period' => '2026-07']);

    // Ia sampai ke sini dari daftar langganan. Menyempitkan gerbangnya ke modul
    // `tenants` hanya akan memindahkan 403 yang sama ke pintu yang lain.
    $hanyaTagihan = PlatformUser::factory()->create();
    $hanyaTagihan->modules()->create(['module' => 'payments']);

    actingAs($hanyaTagihan, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->has('invoices.data', 1)
            ->where('can.tenants', false)
            ->etc()
        );
});

test('yang tidak memegang satu pun modul terkait tetap ditolak', function () {
    ['tenant' => $tenant] = tenantDetailContext();

    actingAs(PlatformUser::factory()->create(), 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertForbidden();
});

test('alamat lama rincian akun mengalihkan, bukan mati', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = tenantDetailContext();

    actingAs($platformUser, 'platform');

    get("/platform/subscriptions/{$tenant->id}")
        ->assertRedirect("/platform/tenants/{$tenant->id}");

    get("/platform/subscriptions/{$tenant->id}/revenue")
        ->assertRedirect("/platform/tenants/{$tenant->id}/revenue");
});

// --- Isi ---

test('kapabilitas kasir ikut terkirim sebagai bacaan, lengkap dengan yang mati', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = tenantDetailContext();

    actingAs($platformUser, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenant.capabilities', 3)
            ->where('tenant.capabilities.0.key', 'kitchen_queue')
            ->where('tenant.capabilities.0.enabled', true)
            // Yang mati pun ikut: "tidak aktif" adalah jawaban, sedangkan baris
            // yang hilang hanya menyisakan pertanyaan.
            ->where('tenant.capabilities.1.key', 'self_order')
            ->where('tenant.capabilities.1.enabled', false)
            ->where('tenant.capabilities.2.enabled', true)
            ->etc()
        );
});

test('tidak ada jalan mengubah kapabilitas dari panel platform', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = tenantDetailContext();

    // Keputusan pemilik: panel ini boleh MENGETAHUI, bukan mengubah. Kalau
    // suatu saat rutenya ditambahkan tanpa keputusan baru, test ini merah.
    actingAs($platformUser, 'platform')
        ->put("/platform/tenants/{$tenant->id}/capabilities", ['self_order' => true])
        ->assertNotFound();

    expect($tenant->fresh()->self_order_enabled)->toBeFalse();
});

test('jumlah akun dan tanggal terdaftar pindah ke rincian, bukan hilang', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = tenantDetailContext();

    User::factory()->count(2)->create(['tenant_id' => $tenant->id]);

    actingAs($platformUser, 'platform')
        ->get("/platform/tenants/{$tenant->id}")
        ->assertInertia(fn (Assert $page) => $page
            ->where('tenant.user_count', 3)
            ->where('tenant.registered_at', $tenant->created_at->toDateString())
            ->has('tenant.business_type_label')
            ->etc()
        );
});

test('tidak ada data operasional yang bocor ke rincian tenant', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = tenantDetailContext();

    $body = actingAs($platformUser, 'platform')->get("/platform/tenants/{$tenant->id}")->getContent();

    expect($body)
        ->not->toContain('ProdukRahasia')
        ->not->toContain('TRXRAHASIA')
        ->not->toContain('987654');
});

// --- Jejak audit ---

test('membuka rincian tercatat sebagai kejadian rutin, bukan sensitif', function () {
    ['tenant' => $tenant, 'platformUser' => $platformUser] = tenantDetailContext();

    actingAs($platformUser, 'platform')->get("/platform/tenants/{$tenant->id}");

    $log = PlatformAuditLog::where('action', 'tenants.show')->first();

    // Menengok keterangan tenant bukan "membuka data bisnis klien" — yang
    // sensitif adalah rute omzetnya, dan itu punya barisnya sendiri.
    expect($log)->not->toBeNull()
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_ROUTINE)
        ->and($log->subject_id)->toBe($tenant->id);
});
