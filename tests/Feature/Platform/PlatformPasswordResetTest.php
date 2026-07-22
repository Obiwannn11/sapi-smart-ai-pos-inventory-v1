<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\PlatformResetPassword;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;

use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    RateLimiter::clear('platform-password-reset');
    Notification::fake();
});

// ── Permintaan tautan ────────────────────────────────────────────────────────
test('a reset link is sent to a known platform account', function () {
    $account = PlatformUser::factory()->create(['email' => 'pemilik@sapi.test']);

    post('/platform/forgot-password', ['email' => 'pemilik@sapi.test'])
        ->assertSessionHas('success');

    Notification::assertSentTo($account, PlatformResetPassword::class);
});

test('the response never reveals whether an email exists', function () {
    PlatformUser::factory()->create(['email' => 'pemilik@sapi.test']);

    $known = post('/platform/forgot-password', ['email' => 'pemilik@sapi.test']);
    RateLimiter::clear('platform-password-reset');
    $unknown = post('/platform/forgot-password', ['email' => 'entah@sapi.test']);

    // Balasan identik: halaman ini tidak boleh jadi alat memeriksa keberadaan akun.
    expect(session()->get('success'))->not->toBeNull();
    $known->assertSessionHas('success', fn ($message) => $message === session('success'));
    $unknown->assertSessionHas('success');
    Notification::assertNothingSentTo(PlatformUser::factory()->make(['email' => 'entah@sapi.test']));
});

test('a tenant account never receives a platform reset link', function () {
    $tenant = Tenant::factory()->create();
    $tenantUser = User::factory()->create(['tenant_id' => $tenant->id, 'email' => 'owner@usaha.test']);

    post('/platform/forgot-password', ['email' => 'owner@usaha.test'])
        ->assertSessionHas('success');

    // Dua dunia terpisah: broker platform hanya melihat tabel platform_users.
    Notification::assertNothingSentTo($tenantUser);
});

// ── Mengatur ulang ───────────────────────────────────────────────────────────
test('a valid token changes the password', function () {
    $account = PlatformUser::factory()->create([
        'email' => 'pemilik@sapi.test',
        'password' => 'lamaSekali123',
    ]);

    $token = Password::broker('platform_users')->createToken($account);

    post('/platform/reset-password', [
        'token' => $token,
        'email' => 'pemilik@sapi.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ])->assertRedirect('/platform/login');

    expect(Hash::check('baruBanget123', $account->fresh()->password))->toBeTrue();
});

test('an invalid token is rejected', function () {
    PlatformUser::factory()->create(['email' => 'pemilik@sapi.test']);

    post('/platform/reset-password', [
        'token' => 'token-palsu',
        'email' => 'pemilik@sapi.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ])->assertSessionHasErrors('email');
});

test('a token cannot be reused', function () {
    $account = PlatformUser::factory()->create(['email' => 'pemilik@sapi.test']);
    $token = Password::broker('platform_users')->createToken($account);

    $payload = [
        'token' => $token,
        'email' => 'pemilik@sapi.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ];

    post('/platform/reset-password', $payload)->assertRedirect('/platform/login');
    RateLimiter::clear('platform-password-reset');
    post('/platform/reset-password', $payload)->assertSessionHasErrors('email');
});

test('the new password must be confirmed', function () {
    $account = PlatformUser::factory()->create(['email' => 'pemilik@sapi.test']);
    $token = Password::broker('platform_users')->createToken($account);

    post('/platform/reset-password', [
        'token' => $token,
        'email' => 'pemilik@sapi.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'beda123456',
    ])->assertSessionHasErrors('password');
});

// ── Halaman ──────────────────────────────────────────────────────────────────
test('the reset pages are reachable by guests', function () {
    get('/platform/forgot-password')->assertStatus(200);
    get('/platform/reset-password/token-apa-saja')->assertStatus(200);
});

// ── Throttle ─────────────────────────────────────────────────────────────────
test('reset link requests are rate limited', function () {
    PlatformUser::factory()->create(['email' => 'pemilik@sapi.test']);

    foreach (range(1, 3) as $ignored) {
        post('/platform/forgot-password', ['email' => 'pemilik@sapi.test']);
    }

    post('/platform/forgot-password', ['email' => 'pemilik@sapi.test'])
        ->assertSessionHasErrors('email');
});

// ── Audit ────────────────────────────────────────────────────────────────────
test('reset requests and completions are audited as sensitive', function () {
    $account = PlatformUser::factory()->create(['email' => 'pemilik@sapi.test']);

    post('/platform/forgot-password', ['email' => 'pemilik@sapi.test']);

    $token = Password::broker('platform_users')->createToken($account);
    post('/platform/reset-password', [
        'token' => $token,
        'email' => 'pemilik@sapi.test',
        'password' => 'baruBanget123',
        'password_confirmation' => 'baruBanget123',
    ]);

    expect(PlatformAuditLog::where('action', 'password_reset.requested')->first()->severity)
        ->toBe(PlatformAuditLog::SEVERITY_SENSITIVE);
    expect(PlatformAuditLog::where('action', 'password_reset.completed')->exists())->toBeTrue();
});
