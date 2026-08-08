<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use App\Services\SubscriptionService;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * @return array{tenant: Tenant, owner: User}
 */
function makeBillingContext(string $status = Tenant::STATUS_ACTIVE, int $periodEndOffsetDays = 30): array
{
    $tenant = Tenant::factory()->create(['status' => $status]);

    Subscription::factory()->seats(5)->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->addDays($periodEndOffsetDays)->toDateString(),
        'trial_ends_at' => now()->addDays($periodEndOffsetDays),
    ]);

    $owner = User::factory()->create([
        'tenant_id' => $tenant->id,
        'role' => 'owner',
    ]);

    return ['tenant' => $tenant, 'owner' => $owner];
}

// --- Gerbang middleware ---

test('tenant aktif tetap bisa menulis', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_ACTIVE);

    actingAs($owner);
    post('/owner/categories', ['name' => 'Kopi'])->assertSessionHasNoErrors();

    expect(App\Models\Category::where('name', 'Kopi')->exists())->toBeTrue();
});

test('tenggat yang sudah mengunci menolak tulis tapi membiarkan baca', function () {
    ['owner' => $owner] = makeBillingContext(
        Tenant::STATUS_GRACE,
        -SubscriptionService::graceLockFromDay(),
    );

    actingAs($owner);

    get('/owner/dashboard')->assertStatus(200);
    post('/owner/categories', ['name' => 'Kopi'])->assertSessionHas('error');

    expect(App\Models\Category::where('name', 'Kopi')->exists())->toBeFalse();
});

test('hari-hari awal tenggat tidak menolak apa pun', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_GRACE, -1);

    actingAs($owner);

    // `[BL-054]`: sampai `grace_lock_from_day`, masa tenggang murni berupa
    // notifikasi. Tidak ada satu pun pintu yang tertutup di sini.
    post('/owner/categories', ['name' => 'Kopi'])->assertSessionHasNoErrors();

    expect(App\Models\Category::where('name', 'Kopi')->exists())->toBeTrue();
});

test('penangguhan mengarahkan seluruh halaman ke halaman langganan', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_SUSPENDED, -90);

    actingAs($owner);

    get('/owner/dashboard')->assertRedirect('/langganan');
    post('/owner/categories', ['name' => 'Kopi'])->assertRedirect('/langganan');
});

test('halaman langganan tetap terbuka justru saat ditangguhkan', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_SUSPENDED, -90);

    actingAs($owner);
    get('/langganan')->assertStatus(200);
});

test('penangguhan ikut dibagikan ke sidebar agar navigasinya bisa dimatikan', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_SUSPENDED, -90);

    actingAs($owner);
    get('/langganan')
        ->assertInertia(fn (Assert $page) => $page->where('auth.tenant.is_suspended', true));
});

test('tenant aktif tidak menandai dirinya ditangguhkan', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_ACTIVE);

    actingAs($owner);
    get('/langganan')
        ->assertInertia(fn (Assert $page) => $page->where('auth.tenant.is_suspended', false));
});

test('logout tetap bisa saat ditangguhkan', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_SUSPENDED, -90);

    actingAs($owner);
    post('/logout')->assertRedirect('/login');
});

test('kasir juga boleh membuka halaman langganan, bukan hanya owner', function () {
    ['tenant' => $tenant] = makeBillingContext(Tenant::STATUS_SUSPENDED, -90);

    $cashier = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']);

    actingAs($cashier);
    get('/langganan')->assertStatus(200);
});

test('api mengembalikan 403 json saat tenggat mengunci, bukan pengalihan', function () {
    ['owner' => $owner] = makeBillingContext(
        Tenant::STATUS_GRACE,
        -SubscriptionService::graceLockFromDay(),
    );

    Sanctum::actingAs($owner);

    // Konsumennya aplikasi, jadi jawabannya harus JSON — pengalihan ke halaman
    // langganan tidak berarti apa pun di sana.
    $this->postJson('/api/v1/mobile/transactions', [])
        ->assertStatus(403)
        ->assertJsonStructure(['message']);
});

test('api tetap boleh membaca saat masa tenggang', function () {
    ['owner' => $owner] = makeBillingContext(Tenant::STATUS_GRACE, -1);

    Sanctum::actingAs($owner);

    $this->getJson('/api/v1/mobile/products')->assertStatus(200);
});

// --- Perpindahan keadaan ---

test('periode yang lewat memindahkan tenant ke masa tenggang', function () {
    ['tenant' => $tenant] = makeBillingContext(Tenant::STATUS_TRIAL, -1);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_GRACE);
});

test('periode yang belum lewat tidak dipindahkan', function () {
    ['tenant' => $tenant] = makeBillingContext(Tenant::STATUS_TRIAL, 5);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_TRIAL);
});

test('masa tenggang yang habis berujung penangguhan', function () {
    $offset = -(SubscriptionService::graceDays() + 1);
    ['tenant' => $tenant] = makeBillingContext(Tenant::STATUS_GRACE, $offset);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_SUSPENDED);
});

test('masa tenggang yang belum habis belum ditangguhkan', function () {
    ['tenant' => $tenant] = makeBillingContext(Tenant::STATUS_GRACE, -1);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_GRACE);
});

test('trial terbengkalai berbulan-bulan tetap melewati masa tenggang dulu', function () {
    ['tenant' => $tenant] = makeBillingContext(Tenant::STATUS_TRIAL, -180);

    artisan('subscriptions:advance-lifecycle')->assertSuccessful();

    // Bukan langsung `suspended`: masa tenggang yang dijanjikan tidak boleh
    // hilang hanya karena tenant lama tidak tersentuh perintah ini.
    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_GRACE);
});

test('dry-run tidak mengubah keadaan apa pun', function () {
    ['tenant' => $tenant] = makeBillingContext(Tenant::STATUS_TRIAL, -1);

    artisan('subscriptions:advance-lifecycle', ['--dry-run' => true])->assertSuccessful();

    expect($tenant->fresh()->status)->toBe(Tenant::STATUS_TRIAL);
});
