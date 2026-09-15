<?php

use App\Models\PlatformUser;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\User;
use App\Notifications\PlatformAlert;
use App\Notifications\TenantVerifyEmail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\artisan;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

function signUp(string $email = 'budi@example.com', string $business = 'Warung Sapi'): Illuminate\Testing\TestResponse
{
    return post('/register', [
        'business_name' => $business,
        'name' => 'Budi',
        'email' => $email,
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);
}

beforeEach(fn () => Notification::fake());

// --- Verifikasi ---

test('pendaftaran mengirim tautan verifikasi dan mendarat di halamannya', function () {
    signUp()->assertRedirect('/verifikasi-email');

    $user = User::where('email', 'budi@example.com')->firstOrFail();

    expect($user->hasVerifiedEmail())->toBeFalse();
    Notification::assertSentTo($user, TenantVerifyEmail::class);
});

test('owner yang belum terverifikasi tidak bisa memakai aplikasi', function () {
    signUp();
    $user = User::where('email', 'budi@example.com')->firstOrFail();

    actingAs($user);

    // Inilah yang membuat pendaftaran berulang jadi mahal: akun tanpa alamat
    // yang benar-benar bisa dibuka tidak berguna untuk apa pun.
    get('/owner/dashboard')->assertRedirect('/verifikasi-email');
    post('/owner/categories', ['name' => 'Kopi'])->assertRedirect('/verifikasi-email');
});

test('tautan verifikasi membuka akses', function () {
    signUp();
    $user = User::where('email', 'budi@example.com')->firstOrFail();

    $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
        'id' => $user->id,
        'hash' => sha1($user->email),
    ]);

    actingAs($user)->get($url)->assertRedirect('/owner/dashboard');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();

    actingAs($user->fresh())->get('/owner/dashboard')->assertStatus(200);
});

test('tautan tanpa tanda tangan yang sah ditolak', function () {
    signUp();
    $user = User::where('email', 'budi@example.com')->firstOrFail();

    actingAs($user)
        ->get("/verifikasi-email/{$user->id}/".sha1($user->email))
        ->assertForbidden();

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('tautan bisa dikirim ulang', function () {
    signUp();
    $user = User::where('email', 'budi@example.com')->firstOrFail();

    actingAs($user)->post('/verifikasi-email/kirim-ulang')->assertSessionHas('success');

    Notification::assertSentToTimes($user, TenantVerifyEmail::class, 2);
});

test('halaman verifikasi dan logout tetap terbuka bagi yang belum terverifikasi', function () {
    signUp();
    $user = User::where('email', 'budi@example.com')->firstOrFail();

    actingAs($user);
    get('/verifikasi-email')
        ->assertStatus(200)
        ->assertInertia(fn (Inertia\Testing\AssertableInertia $page) => $page
            ->component('Auth/VerifyEmail')
            ->where('email', 'budi@example.com'));
    post('/logout')->assertRedirect('/login');
});

test('staf buatan owner langsung terverifikasi', function () {
    $tenant = Tenant::factory()->active()->create();
    Subscription::factory()->seats(5)->create(['tenant_id' => $tenant->id]);
    $owner = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    actingAs($owner)->post('/owner/staff', [
        'name' => 'Kasir',
        'email' => 'kasir@usaha.test',
        'password' => 'password123',
    ])->assertSessionHas('success');

    // Kasir warung kecil kerap tak punya alamat surel sendiri; owner-lah yang
    // menjaminnya.
    $staf = User::where('email', 'kasir@usaha.test')->firstOrFail();
    expect($staf->hasVerifiedEmail())->toBeTrue();
    Notification::assertNotSentTo($staf, TenantVerifyEmail::class);
});

test('api menolak dengan json, bukan pengalihan', function () {
    signUp();
    $user = User::where('email', 'budi@example.com')->firstOrFail();

    Laravel\Sanctum\Sanctum::actingAs($user, ['mobile:use']);

    $this->getJson('/api/v1/mobile/products')
        ->assertStatus(403)
        ->assertJsonStructure(['message']);
});

// --- Deteksi pendaftaran berulang ---

test('pendaftaran dari satu ip melewati ambang ditandai untuk ditinjau', function () {
    PlatformUser::factory()->owner()->create();
    $ambang = (int) config('platform-alerts.signup.max_per_ip');

    for ($i = 0; $i <= $ambang; $i++) {
        signUp("orang{$i}@example.com", "Warung {$i}");
        post('/logout');
    }

    $terakhir = Tenant::where('name', 'Warung '.$ambang)->firstOrFail();

    expect($terakhir->flagged_at)->not->toBeNull()
        ->and($terakhir->flag_reason)->toContain('pendaftaran dari IP');

    Notification::assertSentTimes(PlatformAlert::class, 1);
});

test('pendaftaran di bawah ambang tidak ditandai', function () {
    PlatformUser::factory()->owner()->create();

    signUp('satu@example.com', 'Warung Satu');
    post('/logout');
    signUp('dua@example.com', 'Warung Dua');

    expect(Tenant::whereNotNull('flagged_at')->count())->toBe(0);
    Notification::assertNotSentTo(PlatformUser::first(), PlatformAlert::class);
});

test('ip pendaftaran dicatat', function () {
    signUp();

    expect(Tenant::where('name', 'Warung Sapi')->first()->signup_ip)->not->toBeNull();
});

test('penandaan tidak memblokir — tenantnya tetap terbuat dan bisa diverifikasi', function () {
    PlatformUser::factory()->owner()->create();
    $ambang = (int) config('platform-alerts.signup.max_per_ip');

    for ($i = 0; $i <= $ambang; $i++) {
        signUp("orang{$i}@example.com", "Warung {$i}");
        post('/logout');
    }

    // Satu IP publik bisa dipakai bersama beberapa usaha yang sah; yang
    // ditandai perlu ditinjau orang, bukan dijegal mesin.
    expect(Tenant::count())->toBe($ambang + 1)
        ->and(User::count())->toBe($ambang + 1);
});

// --- Pemangkasan tenant terbengkalai ---

test('tenant yang tak pernah diverifikasi dan tak pernah dipakai bisa dipangkas', function () {
    $terbengkalai = Tenant::factory()->create();
    User::factory()->unverified()->create(['tenant_id' => $terbengkalai->id, 'role' => 'owner']);
    $terbengkalai->forceFill(['created_at' => now()->subDays(60)])->save();

    artisan('platform:prune-abandoned-tenants')->assertSuccessful();

    expect(Tenant::find($terbengkalai->id))->toBeNull();
});

test('tenant yang pernah bertransaksi tidak pernah dipangkas', function () {
    $tenant = Tenant::factory()->create();
    $owner = User::factory()->unverified()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $tenant->forceFill(['created_at' => now()->subDays(60)])->save();

    Transaction::factory()->create(['tenant_id' => $tenant->id, 'user_id' => $owner->id]);

    artisan('platform:prune-abandoned-tenants');

    // Ada penjualan sungguhan di dalamnya, berapa pun umurnya.
    expect(Tenant::find($tenant->id))->not->toBeNull();
});

test('tenant terverifikasi tidak dipangkas walau tanpa transaksi', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $tenant->forceFill(['created_at' => now()->subDays(60)])->save();

    artisan('platform:prune-abandoned-tenants');

    expect(Tenant::find($tenant->id))->not->toBeNull();
});

test('tenant yang masih muda tidak dipangkas', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->unverified()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);

    artisan('platform:prune-abandoned-tenants');

    expect(Tenant::find($tenant->id))->not->toBeNull();
});

test('dry-run tidak menghapus apa pun', function () {
    $tenant = Tenant::factory()->create();
    User::factory()->unverified()->create(['tenant_id' => $tenant->id, 'role' => 'owner']);
    $tenant->forceFill(['created_at' => now()->subDays(60)])->save();

    artisan('platform:prune-abandoned-tenants', ['--dry-run' => true])->assertSuccessful();

    expect(Tenant::find($tenant->id))->not->toBeNull();
});
