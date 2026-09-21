<?php

use App\Models\CashDrawer;
use App\Models\Tenant;
use App\Models\User;
use App\Services\CashDrawerExpiryService;
use Inertia\Testing\AssertableInertia as Assert;

/**
 * Umur sesi kas dan penutupan paksanya (`[BL-088]`).
 */
beforeEach(function () {
    $this->tenant = Tenant::factory()->create();
    $this->cashier = User::factory()->create([
        'tenant_id' => $this->tenant->id,
        'role' => 'cashier',
    ]);
});

function drawerOpenedHoursAgo(Tenant $tenant, User $user, int $hours): CashDrawer
{
    return CashDrawer::factory()->create([
        'tenant_id' => $tenant->id,
        'user_id' => $user->id,
        'opening_amount' => 500_000,
        'opened_at' => now()->subHours($hours),
        'closed_at' => null,
    ]);
}

test('sesi yang lewat batas ditutup, yang belum tidak disentuh', function () {
    $stale = drawerOpenedHoursAgo($this->tenant, $this->cashier, CashDrawer::MAX_SESSION_HOURS + 1);

    $freshCashier = User::factory()->create(['tenant_id' => $this->tenant->id, 'role' => 'cashier']);
    $fresh = drawerOpenedHoursAgo($this->tenant, $freshCashier, CashDrawer::MAX_SESSION_HOURS - 1);

    $closed = app(CashDrawerExpiryService::class)->expire();

    expect($closed)->toBe(1)
        ->and($stale->fresh()->closed_at)->not->toBeNull()
        ->and($fresh->fresh()->closed_at)->toBeNull();
});

test('sesi yang ditutup sistem tidak mengaku sudah dihitung', function () {
    // Inti butir 2: `closing_amount` yang diisi `expected_amount` menghasilkan
    // selisih nol yang dikarang — kebohongan yang sama dengan yang dilarang
    // `[BL-086]`, hanya berpindah dari layar ke basis data.
    $drawer = drawerOpenedHoursAgo($this->tenant, $this->cashier, CashDrawer::MAX_SESSION_HOURS + 2);

    app(CashDrawerExpiryService::class)->expire();

    $drawer = $drawer->fresh();

    expect($drawer->closed_by_system)->toBeTrue()
        ->and($drawer->closing_amount)->toBeNull()
        ->and($drawer->difference)->toBeNull()
        // `expected_amount` sebaliknya DIISI: ia angka milik sistem sendiri,
        // bukan pernyataan tentang uang fisik.
        ->and($drawer->expected_amount)->not->toBeNull()
        ->and($drawer->notes)->toContain('Ditutup otomatis oleh sistem');
});

test('catatan kasir yang sudah ada tidak ditimpa', function () {
    $drawer = drawerOpenedHoursAgo($this->tenant, $this->cashier, CashDrawer::MAX_SESSION_HOURS + 1);
    $drawer->update(['notes' => 'Uang kecil menipis sejak sore']);

    app(CashDrawerExpiryService::class)->expire();

    expect($drawer->fresh()->notes)
        ->toContain('Uang kecil menipis sejak sore')
        ->toContain('Ditutup otomatis oleh sistem');
});

test('sesi yang sudah ditutup kasir tidak ikut tersapu', function () {
    $drawer = CashDrawer::factory()->closed()->create([
        'tenant_id' => $this->tenant->id,
        'user_id' => $this->cashier->id,
        'opened_at' => now()->subDays(3),
    ]);

    $closingAmount = $drawer->closing_amount;

    app(CashDrawerExpiryService::class)->expire();

    expect($drawer->fresh()->closed_by_system)->toBeFalse()
        ->and($drawer->fresh()->closing_amount)->toEqual($closingAmount);
});

test('sapuan berjalan lintas tenant tanpa user yang login', function () {
    // Perintah terjadwal tidak punya sesi, jadi TenantScope tidak punya tenant
    // untuk disandarkan. Tanpa `withoutGlobalScopes()` sapuan ini akan diam.
    $otherTenant = Tenant::factory()->create();
    $otherCashier = User::factory()->create(['tenant_id' => $otherTenant->id, 'role' => 'cashier']);

    drawerOpenedHoursAgo($this->tenant, $this->cashier, CashDrawer::MAX_SESSION_HOURS + 1);
    drawerOpenedHoursAgo($otherTenant, $otherCashier, CashDrawer::MAX_SESSION_HOURS + 1);

    expect(app(CashDrawerExpiryService::class)->expire())->toBe(2);
});

test('dry-run menghitung tanpa menutup', function () {
    $drawer = drawerOpenedHoursAgo($this->tenant, $this->cashier, CashDrawer::MAX_SESSION_HOURS + 1);

    expect(app(CashDrawerExpiryService::class)->expire(dryRun: true))->toBe(1)
        ->and($drawer->fresh()->closed_at)->toBeNull();
});

test('perintahnya terdaftar dan jalan', function () {
    drawerOpenedHoursAgo($this->tenant, $this->cashier, CashDrawer::MAX_SESSION_HOURS + 1);

    $this->artisan('cash-drawers:expire')
        ->assertSuccessful();

    expect(CashDrawer::withoutGlobalScopes()->where('closed_by_system', true)->count())->toBe(1);
});

test('kasir diperingatkan sebelum batasnya lewat, bukan sesudah', function () {
    // Batas peringatan, bukan batas umur: peringatan yang muncul sesudah sesi
    // tertutup tidak berguna — uang fisiknya sudah tidak bisa dihitung.
    $hoursIn = CashDrawer::MAX_SESSION_HOURS - CashDrawer::STALE_WARNING_HOURS + 1;
    drawerOpenedHoursAgo($this->tenant, $this->cashier, $hoursIn);

    $this->actingAs($this->cashier)
        ->get('/cashier/cash-drawer')
        ->assertStatus(200)
        ->assertInertia(fn (Assert $page) => $page
            ->where('sessionLimit.hours', CashDrawer::MAX_SESSION_HOURS)
            ->has('sessionLimit.expires_at')
            ->has('sessionLimit.warn_from')
        );
});
