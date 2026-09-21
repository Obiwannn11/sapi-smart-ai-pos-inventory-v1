<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;

/**
 * Isi dan gerbang link data untuk AI — `[BL-102]` tahap 2.
 *
 * Yang membuka link adalah pengambil halaman AI, jadi dua hal dijaga di sini:
 * isinya teks yang memuat nama usaha dan produk (tes baca di prompt bawaan
 * bergantung padanya), dan setiap penolakan juga teks yang mengatakan dirinya
 * penolakan, bukan pengalihan ke halaman lain yang akan dibacakan AI seolah
 * data toko.
 */

/**
 * @param  array<string, mixed>  $tenantAttributes
 * @return array{tenant: Tenant, owner: User}
 */
function connectorSummaryContext(array $tenantAttributes = []): array
{
    $tenant = Tenant::factory()->active()->create([
        'name' => 'Kopi Senja',
        'ai_enabled' => true,
        ...$tenantAttributes,
    ]);
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
    ];
}

/**
 * URL persis seperti yang disalin owner: `|` pada token di-encode.
 */
function connectorSummaryUrl(string $plainTextToken): string
{
    return '/api/v1/connector/summary?token='.rawurlencode($plainTextToken);
}

// ── Isi ────────────────────────────────────────────────────────────────────

test('link membuka data toko sebagai teks yang memuat nama usaha dan produk', function () {
    ['tenant' => $tenant, 'owner' => $owner] = connectorSummaryContext();

    $category = Category::factory()->create(['tenant_id' => $tenant->id, 'name' => 'Minuman']);
    $product = Product::factory()->create([
        'tenant_id' => $tenant->id,
        'category_id' => $category->id,
        'name' => 'Es Kopi Susu',
    ]);
    ProductVariant::factory()->create(['product_id' => $product->id, 'name' => 'Reguler', 'price' => 18000, 'stock' => 12]);
    Transaction::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id, 'total_amount' => 54000]);

    $token = $owner->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->plainTextToken;

    $response = $this->get(connectorSummaryUrl($token))->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('text/plain')
        ->and($response->headers->get('Cache-Control'))->toContain('no-store')
        ->and($response->headers->get('X-Robots-Tag'))->toContain('noindex');

    $response->assertSee('# Data toko: Kopi Senja', false)
        ->assertSee('### Minuman', false)
        ->assertSee('Es Kopi Susu: Reguler Rp 18.000 (stok 12)', false)
        ->assertSee('Rp 54.000', false);
});

test('membuka link mencatat waktu terakhir dipakai', function () {
    ['owner' => $owner] = connectorSummaryContext();
    $newToken = $owner->createToken('ChatGPT', ['connector:read'], now()->addDays(30));

    $this->get(connectorSummaryUrl($newToken->plainTextToken))->assertOk();

    expect($newToken->accessToken->fresh()->last_used_at)->not->toBeNull();
});

test('link hanya memuat data tenant pemiliknya', function () {
    ['owner' => $owner] = connectorSummaryContext();

    $otherTenant = Tenant::factory()->active()->create(['name' => 'Warung Tetangga']);
    Product::factory()->create(['tenant_id' => $otherTenant->id, 'name' => 'Nasi Goreng Rahasia']);
    Transaction::factory()->create([
        'tenant_id' => $otherTenant->id,
        'user_id' => User::factory()->create(['tenant_id' => $otherTenant->id])->id,
        'total_amount' => 987000,
    ]);

    $token = $owner->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->plainTextToken;

    // TenantScope hanya menyaring saat auth()->check(); tanpa itu query ini
    // mengembalikan data seluruh tenant.
    $this->get(connectorSummaryUrl($token))
        ->assertOk()
        ->assertDontSee('Warung Tetangga')
        ->assertDontSee('Nasi Goreng Rahasia')
        ->assertDontSee('Rp 987.000', false);
});

// ── Penolakan terbaca sebagai penolakan ────────────────────────────────────

test('link yang tidak berlaku dijawab teks 401, bukan pengalihan ke halaman masuk', function (Closure $url) {
    ['owner' => $owner] = connectorSummaryContext();

    $response = $this->get($url($owner))
        ->assertUnauthorized()
        ->assertSee('Link ini tidak berlaku', false);

    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
})->with([
    'kedaluwarsa' => [fn (User $owner) => connectorSummaryUrl(
        $owner->createToken('Lama', ['connector:read'], now()->subMinute())->plainTextToken
    )],
    'sudah dicabut' => [fn (User $owner) => connectorSummaryUrl('999|tokenyangtidakpernahada')],
    'tanpa token' => [fn (User $owner) => '/api/v1/connector/summary'],
]);

test('token di header tidak dibaca di rute konektor', function () {
    ['owner' => $owner] = connectorSummaryContext();
    $token = $owner->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->plainTextToken;

    $this->withToken($token)
        ->get('/api/v1/connector/summary')
        ->assertUnauthorized();
});

test('link ditolak dengan teks saat gerbang lain menutupnya', function (Closure $setup, string $expectedText) {
    ['tenant' => $tenant, 'owner' => $owner] = connectorSummaryContext();
    $token = $setup($tenant, $owner);

    $response = $this->get(connectorSummaryUrl($token))
        ->assertForbidden()
        ->assertSee('Data toko tidak bisa dibuka lewat link ini.', false)
        ->assertSee($expectedText, false)
        ->assertDontSee('Kopi Senja');

    expect($response->headers->get('Content-Type'))->toStartWith('text/plain');
})->with([
    'token mcp, bukan link' => [
        fn (Tenant $tenant, User $owner) => $owner->createToken('mcp-client', ['mcp:use'])->plainTextToken,
        'Token ini tidak berlaku untuk endpoint ini.',
    ],
    'modul ai dimatikan' => [
        function (Tenant $tenant, User $owner) {
            $tenant->update(['ai_enabled' => false]);

            return $owner->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->plainTextToken;
        },
        'sedang tidak aktif',
    ],
    'langganan ditangguhkan' => [
        function (Tenant $tenant, User $owner) {
            $tenant->update(['status' => Tenant::STATUS_SUSPENDED]);

            return $owner->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->plainTextToken;
        },
        'ditangguhkan',
    ],
    'link milik kasir' => [
        function (Tenant $tenant, User $owner) {
            $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

            return $cashier->createToken('ChatGPT', ['connector:read'], now()->addDays(30))->plainTextToken;
        },
        'tidak memiliki akses',
    ],
]);
