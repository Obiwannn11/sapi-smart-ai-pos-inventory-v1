<?php

use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\TenantResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    RateLimiter::clear('password-reset');
    Notification::fake();
});

function tenantUser(string $email = 'owner@usaha.test', string $role = 'owner'): User
{
    return User::factory()->create([
        'tenant_id' => Tenant::factory()->create()->id,
        'email' => $email,
        'role' => $role,
        'password' => 'lamaSekali123',
    ]);
}

// ── Permintaan tautan ────────────────────────────────────────────────────────
test('a reset link is sent to a known tenant account', function () {
    $user = tenantUser();

    post('/forgot-password', ['email' => 'owner@usaha.test'])->assertSessionHas('success');

    Notification::assertSentTo($user, TenantResetPassword::class);
});

test('staff accounts can recover too, not just owners', function () {
    $staff = tenantUser('kasir@usaha.test', 'cashier');

    post('/forgot-password', ['email' => 'kasir@usaha.test'])->assertSessionHas('success');

    Notification::assertSentTo($staff, TenantResetPassword::class);
});

test('the response never reveals whether an email exists', function () {
    tenantUser();

    post('/forgot-password', ['email' => 'owner@usaha.test'])->assertSessionHas('success');
    RateLimiter::clear('password-reset');
    post('/forgot-password', ['email' => 'entah@usaha.test'])->assertSessionHas('success');
});

test('a platform account never receives a tenant reset link', function () {
    $platformUser = PlatformUser::factory()->create(['email' => 'platform@sapi.test']);

    post('/forgot-password', ['email' => 'platform@sapi.test'])->assertSessionHas('success');

    // Dua dunia terpisah: broker tenant hanya melihat tabel users.
    Notification::assertNothingSentTo($platformUser);
});

// ── Mengatur ulang ───────────────────────────────────────────────────────────
test('a valid token changes the password', function () {
    $user = tenantUser();
    $token = Password::broker()->createToken($user);

    post('/reset-password', [
        'token' => $token,
        'email' => 'owner@usaha.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ])->assertRedirect('/login');

    expect(Hash::check('baruBanget123', $user->fresh()->password))->toBeTrue();
});

test('the new password actually works for logging in', function () {
    $user = tenantUser();
    $token = Password::broker()->createToken($user);

    post('/reset-password', [
        'token' => $token,
        'email' => 'owner@usaha.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ]);

    post('/login', ['email' => 'owner@usaha.test', 'password' => 'baruBanget123'])
        ->assertRedirect('/owner/dashboard');
});

test('an invalid token is rejected', function () {
    tenantUser();

    post('/reset-password', [
        'token' => 'token-palsu',
        'email' => 'owner@usaha.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ])->assertSessionHasErrors('email');
});

test('a token cannot be reused', function () {
    $user = tenantUser();
    $token = Password::broker()->createToken($user);

    $payload = [
        'token' => $token,
        'email' => 'owner@usaha.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ];

    post('/reset-password', $payload)->assertRedirect('/login');
    RateLimiter::clear('password-reset');
    post('/reset-password', $payload)->assertSessionHasErrors('email');
});

test('the new password must be confirmed', function () {
    $user = tenantUser();
    $token = Password::broker()->createToken($user);

    post('/reset-password', [
        'token' => $token,
        'email' => 'owner@usaha.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'beda123456',
    ])->assertSessionHasErrors('password');
});

// ── Halaman & throttle ───────────────────────────────────────────────────────
test('the reset pages are reachable by guests', function () {
    get('/forgot-password')->assertStatus(200);
    get('/reset-password/token-apa-saja')->assertStatus(200);
});

test('reset link requests are rate limited', function () {
    tenantUser();

    foreach (range(1, 3) as $ignored) {
        post('/forgot-password', ['email' => 'owner@usaha.test']);
    }

    post('/forgot-password', ['email' => 'owner@usaha.test'])->assertSessionHasErrors('email');
});

test('the tenant reset limit does not consume the platform one', function () {
    tenantUser();
    PlatformUser::factory()->create(['email' => 'platform@sapi.test']);

    foreach (range(1, 4) as $ignored) {
        post('/forgot-password', ['email' => 'owner@usaha.test']);
    }

    // Limiter terpisah: kasir yang kehabisan jatah tidak boleh ikut mengunci
    // jalur pemulihan pemilik SaaS.
    post('/platform/forgot-password', ['email' => 'platform@sapi.test'])
        ->assertSessionHasNoErrors();
});
