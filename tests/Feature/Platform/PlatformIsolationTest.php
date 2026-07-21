<?php

use App\Models\PlatformUser;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

/**
 * Pertahanan lapis kedua, melengkapi PlatformArchTest.
 *
 * Arch test memeriksa IMPOR, jadi ia tidak menangkap traversal relasi
 * ($tenant->transactions), query mentah (DB::table), atau eager load
 * (Tenant::with('transactions')). Test di sini memeriksa HASILNYA di tingkat
 * HTTP: apa pun jalurnya, data operasional tidak boleh sampai ke payload.
 *
 * @return array{platformUser: PlatformUser, tenantA: Tenant, tenantB: Tenant}
 */
function platformIsolationContext(): array
{
    $tenantA = Tenant::factory()->create(['name' => 'Kopi Story']);
    $tenantB = Tenant::factory()->create(['name' => 'Warung Bu Tini']);

    foreach ([$tenantA, $tenantB] as $tenant) {
        $owner = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => 'owner',
            'email' => "owner-{$tenant->id}@usaha.test",
        ]);
        User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

        Product::factory()->create([
            'tenant_id' => $tenant->id,
            'name' => "ProdukRahasia{$tenant->id}",
        ]);
        Transaction::factory()->create([
            'tenant_id' => $tenant->id,
            'user_id' => $owner->id,
            'code' => "TRXRAHASIA{$tenant->id}",
            'total_amount' => 987654,
        ]);
    }

    return [
        'platformUser' => PlatformUser::factory()->withAllModules()->create(),
        'tenantA' => $tenantA,
        'tenantB' => $tenantB,
    ];
}

test('tenant list exposes only whitelisted administrative fields', function () {
    ['platformUser' => $platformUser] = platformIsolationContext();

    actingAs($platformUser, 'platform')
        ->get('/platform/tenants')
        ->assertInertia(fn (Assert $page) => $page
            ->has('tenants.data', 2)
            ->has('tenants.data.0', fn (Assert $tenant) => $tenant
                // Daftar putih: persis field ini, tidak lebih. Kalau suatu saat
                // ada kolom baru yang bocor lewat resource, test ini merah.
                ->hasAll(['id', 'name', 'slug', 'registered_at', 'user_count', 'owner'])
                ->etc()
            )
        );
});

test('no operational data of any tenant leaks into the platform payload', function () {
    ['platformUser' => $platformUser] = platformIsolationContext();

    $body = actingAs($platformUser, 'platform')->get('/platform/tenants')->getContent();

    // Apa pun jalurnya — relasi, eager load, query mentah — jejak data
    // operasional tidak boleh muncul di respons.
    expect($body)
        ->not->toContain('ProdukRahasia')
        ->not->toContain('TRXRAHASIA')
        ->not->toContain('987654');
});

test('platform dashboard exposes only administrative counts', function () {
    ['platformUser' => $platformUser] = platformIsolationContext();

    actingAs($platformUser, 'platform')
        ->get('/platform')
        ->assertInertia(fn (Assert $page) => $page
            ->where('stats.tenant_count', 2)
            ->where('stats.user_count', 4)
            ->has('stats', fn (Assert $stats) => $stats->hasAll(['tenant_count', 'user_count']))
        );
});

test('the tenant global scope is not silently disabled for platform requests', function () {
    // Penjaga terhadap "perbaikan" yang keliru: kalau suatu saat seseorang
    // membuang TenantScope karena halaman platform terlihat kosong, query
    // operasional di konteks platform akan mulai mengembalikan data semua
    // tenant — dan test ini yang menangkapnya.
    ['platformUser' => $platformUser] = platformIsolationContext();

    actingAs($platformUser, 'platform');

    expect(Transaction::count())->toBe(0);
    expect(Product::count())->toBe(0);
});

test('audit log records that the tenant list was opened', function () {
    ['platformUser' => $platformUser] = platformIsolationContext();

    actingAs($platformUser, 'platform')->get('/platform/tenants');

    expect(\App\Models\PlatformAuditLog::where('action', 'tenants.index')->exists())->toBeTrue();
});
