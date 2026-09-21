<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * Ability token Sanctum ditegakkan per permukaan API — `[BL-112]`.
 *
 * Sebelum berkas ini ada, ability hanya DITULIS ke token dan tidak pernah
 * diperiksa, sehingga token MCP yang dianggap hanya-baca diterima di rute
 * mobile yang menulis, termasuk void transaksi. Test di sini sengaja memakai
 * token sungguhan (`createToken` + header Bearer), bukan `Sanctum::actingAs`:
 * yang diuji justru jalur token yang dipakai klien nyata.
 *
 * Penolakan diperiksa lewat `code`, bukan status saja. Rute-rute ini punya
 * gerbang lain yang juga menjawab 403 (langganan, fitur, peran), jadi 403 tanpa
 * kode tidak membuktikan gerbang mana yang menolak.
 */

/**
 * @return array{tenant: Tenant, owner: User}
 */
function tokenAbilityContext(): array
{
    // Semua gerbang lain dibuka, supaya satu-satunya yang bisa menolak adalah
    // ability token.
    $tenant = Tenant::factory()->active()->create([
        'ai_enabled' => true,
        'self_order_enabled' => true,
    ]);
    Subscription::factory()->seats(10)->create(['tenant_id' => $tenant->id]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
    ];
}

// ── Token MCP tidak boleh keluar dari MCP ──────────────────────────────────

test('token mcp ditolak di rute mobile dan self-order', function (string $method, string $uri) {
    ['owner' => $owner] = tokenAbilityContext();
    $token = $owner->createToken('mcp-client', ['mcp:use'])->plainTextToken;

    $this->withToken($token)
        ->json($method, $uri)
        ->assertForbidden()
        ->assertJsonPath('code', 'token_ability_missing');
})->with([
    'buat transaksi mobile' => ['POST', '/api/v1/mobile/transactions'],
    'buka laci kas' => ['POST', '/api/v1/mobile/cash-drawer/open'],
    'katalog mobile' => ['GET', '/api/v1/mobile/products'],
    'pesanan self-order' => ['POST', '/api/v1/orders'],
    'katalog self-order' => ['GET', '/api/v1/products'],
]);

test('token mcp tidak bisa membatalkan transaksi', function () {
    ['tenant' => $tenant, 'owner' => $owner] = tokenAbilityContext();
    $transaction = Transaction::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $token = $owner->createToken('mcp-client', ['mcp:use'])->plainTextToken;

    // Rute paling berbahaya dari celah ini, jadi dibuktikan terpisah dengan
    // transaksi sungguhan — id karangan akan menjawab 404 dari route binding.
    $this->withToken($token)
        ->postJson("/api/v1/mobile/transactions/{$transaction->id}/void")
        ->assertForbidden()
        ->assertJsonPath('code', 'token_ability_missing');

    expect($transaction->fresh()->status)->toBe($transaction->status);
});

test('token mcp tidak bisa memajukan pesanan self-order', function () {
    ['tenant' => $tenant, 'owner' => $owner] = tokenAbilityContext();
    $transaction = Transaction::factory()->selfOrder()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);
    $token = $owner->createToken('mcp-client', ['mcp:use'])->plainTextToken;

    $this->withToken($token)
        ->patchJson("/api/v1/orders/{$transaction->id}/fulfillment")
        ->assertForbidden()
        ->assertJsonPath('code', 'token_ability_missing');
});

test('token mcp tetap diterima di endpoint mcp', function () {
    ['owner' => $owner] = tokenAbilityContext();
    $token = $owner->createToken('mcp-client', ['mcp:use'])->plainTextToken;

    $this->withToken($token)
        ->postJson('/mcp/business', [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
        ])
        ->assertOk();
});

// ── Token yang sudah beredar tidak ikut mati ───────────────────────────────

test('token dari login mobile tetap diterima di rute mobile', function () {
    ['owner' => $owner] = tokenAbilityContext();

    // Token login mobile hari ini ber-ability `*`, dan aplikasi kasir memegangnya
    // berminggu-minggu. Penegakan ability tidak boleh mengeluarkannya.
    $token = $this->postJson('/api/v1/mobile/login', [
        'email' => $owner->email,
        'password' => 'password',
    ])->json('token');

    $this->withToken($token)->getJson('/api/v1/mobile/products')->assertOk();
});

// ── Ability tiap permukaan tidak saling membuka ────────────────────────────

test('ability satu permukaan tidak membuka permukaan lain', function (array $abilities, string $allowedUri, string $deniedUri) {
    ['owner' => $owner] = tokenAbilityContext();
    $token = $owner->createToken('uji-ability', $abilities)->plainTextToken;

    $this->withToken($token)->getJson($allowedUri)->assertOk();

    $this->withToken($token)
        ->getJson($deniedUri)
        ->assertForbidden()
        ->assertJsonPath('code', 'token_ability_missing');
})->with([
    'mobile' => [['mobile:use'], '/api/v1/mobile/products', '/api/v1/products'],
    'self-order' => [['self-order:use'], '/api/v1/products', '/api/v1/mobile/products'],
]);

// ── Penjaga: grup bertoken berikutnya tidak boleh lupa ─────────────────────

test('setiap rute auth:sanctum menyebut ability permukaannya', function () {
    // Celah [BL-112] lahir dari kelalaian yang tidak terlihat: rute tanpa
    // `ability:` tetap berjalan normal dan tidak ada test yang gagal. Penjaga
    // ini menamai rutenya begitu ada yang lupa lagi.
    $sanctumRoutes = collect(Route::getRoutes()->getRoutes())
        ->filter(fn (RoutingRoute $route) => in_array('auth:sanctum', $route->gatherMiddleware(), true));

    // Penjaga yang tidak menemukan satu rute pun akan hijau selamanya.
    expect($sanctumRoutes)->not->toBeEmpty();

    $routesWithoutAbility = $sanctumRoutes
        ->reject(fn (RoutingRoute $route) => collect($route->gatherMiddleware())
            ->contains(fn ($middleware) => is_string($middleware) && Str::startsWith($middleware, ['ability:', 'abilities:'])))
        ->map(fn (RoutingRoute $route) => implode('|', $route->methods()).' '.$route->uri())
        ->values()
        ->all();

    expect($routesWithoutAbility)->toBe([]);
});
