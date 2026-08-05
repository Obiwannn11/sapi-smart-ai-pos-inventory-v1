<?php

use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;

/**
 * Pita keadaan langganan di setiap layar — `[BL-045]` butir (1).
 *
 * Penegakannya sudah lama benar; yang diuji di sini adalah bahwa keadaannya
 * ikut DIBAGIKAN, supaya kasir dan owner tahu sebelum menekan tombol, bukan
 * sesudah ditolak.
 */

/**
 * @return array{tenant: Tenant, owner: User, cashier: User}
 */
function bannerContext(string $status, int $periodEndOffsetDays = -1): array
{
    $tenant = Tenant::factory()->create(['status' => $status]);

    Subscription::factory()->create([
        'tenant_id' => $tenant->id,
        'current_period_end' => now()->addDays($periodEndOffsetDays)->toDateString(),
    ]);

    return [
        'tenant' => $tenant,
        'owner' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']),
        'cashier' => User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'cashier']),
    ];
}

test('a tenant in grace shares its restriction with every screen', function () {
    ['owner' => $owner] = bannerContext(Tenant::STATUS_GRACE);

    actingAs($owner);

    // Halaman yang sama sekali tidak berhubungan dengan langganan — justru itu
    // intinya: dulu keadaan ini hanya ada di props Dashboard.
    get('/owner/products')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription.status', Tenant::STATUS_GRACE)
        // Tanggal penangguhan ikut, supaya pita bisa menyebut tenggatnya.
        ->where('auth.tenant.subscription.suspends_at', now()->subDay()->addDays(30)->toDateString())
    );
});

test('the cashier shell sees it too, not just the owner shell', function () {
    ['cashier' => $cashier] = bannerContext(Tenant::STATUS_GRACE);

    actingAs($cashier);

    // Kasir yang membuka layar kasir langsung adalah orang yang paling
    // dirugikan oleh penolakan tanpa peringatan — pembeli sedang menunggu di
    // depan meja. Dipakai halaman kas, bukan POS: tanpa laci terbuka POS
    // memantul ke sini lebih dulu, dan keduanya sama-sama memakai
    // `CashierTopbar` yang memuat pitanya.
    get('/cashier/cash-drawer')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription.status', Tenant::STATUS_GRACE)
    );
});

test('a suspended tenant shares it on the one page still open to it', function () {
    ['owner' => $owner] = bannerContext(Tenant::STATUS_SUSPENDED);

    actingAs($owner);

    get('/langganan')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription.status', Tenant::STATUS_SUSPENDED)
        // `suspensionDateFor()` sengaja null di luar masa tenggang — di
        // penangguhan tanggal itu tidak punya arti apa pun lagi.
        ->where('auth.tenant.subscription.suspends_at', null)
    );
});

test('an unrestricted tenant shares nothing, and pays no query for it', function () {
    ['owner' => $owner] = bannerContext(Tenant::STATUS_ACTIVE, periodEndOffsetDays: 20);

    actingAs($owner);

    // `null` bukan sekadar kerapian: inilah yang membuat pita ini gratis di
    // jalur panas. Mayoritas request tidak pernah menyentuh query tambahan.
    get('/owner/products')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription', null)
    );
});

test('a trial tenant is not warned — nothing has narrowed yet', function () {
    ['owner' => $owner] = bannerContext(Tenant::STATUS_TRIAL, periodEndOffsetDays: 3);

    actingAs($owner);

    get('/owner/products')->assertInertia(fn (Assert $page) => $page
        ->where('auth.tenant.subscription', null)
    );
});

test('a platform account is never handed a tenant restriction', function () {
    $platformUser = App\Models\PlatformUser::factory()->withAllModules()->create();

    actingAs($platformUser, 'platform');

    // `auth.tenant` seluruhnya null di konteks platform — akun platform tidak
    // punya tenant, dan pita ini tidak boleh muncul di panel pemilik SaaS.
    get('/platform')->assertInertia(fn (Assert $page) => $page->where('auth.tenant', null));
});
