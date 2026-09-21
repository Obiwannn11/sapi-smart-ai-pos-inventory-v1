<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Notifications\PlatformAlert;
use Illuminate\Support\Facades\Notification;

use function Pest\Laravel\artisan;

function recordFailedLogin(string $email, string $ip = '203.0.113.10', ?Carbon\Carbon $at = null): PlatformAuditLog
{
    $log = PlatformAuditLog::create([
        'action' => 'login.failed',
        'severity' => PlatformAuditLog::SEVERITY_SENSITIVE,
        'meta' => ['email' => $email],
        'ip' => $ip,
    ]);

    if ($at !== null) {
        $log->forceFill(['created_at' => $at])->save();
    }

    return $log;
}

beforeEach(function () {
    Notification::fake();
    PlatformUser::factory()->owner()->create();
});

// --- Pola 1: penebakan kata sandi ---

test('kegagalan menumpuk pada satu alamat memicu peringatan', function () {
    $ambang = config('platform-alerts.failed_login.per_email');

    for ($i = 0; $i < $ambang; $i++) {
        recordFailedLogin('owner@sapi.test');
    }

    artisan('platform:alert-failed-logins')->assertSuccessful();

    Notification::assertSentTimes(PlatformAlert::class, 1);
});

test('kegagalan di bawah ambang tidak memicu apa pun', function () {
    $ambang = config('platform-alerts.failed_login.per_email');

    for ($i = 0; $i < $ambang - 1; $i++) {
        recordFailedLogin('owner@sapi.test');
    }

    artisan('platform:alert-failed-logins');

    Notification::assertNothingSent();
});

test('kegagalan di luar jendela waktu tidak ikut dihitung', function () {
    $ambang = config('platform-alerts.failed_login.per_email');
    $lampau = now()->subMinutes((int) config('platform-alerts.failed_login.window_minutes') + 60);

    for ($i = 0; $i < $ambang + 5; $i++) {
        recordFailedLogin('owner@sapi.test', at: $lampau);
    }

    artisan('platform:alert-failed-logins');

    Notification::assertNothingSent();
});

test('kapitalisasi alamat tidak memberi jatah baru', function () {
    $ambang = config('platform-alerts.failed_login.per_email');

    for ($i = 0; $i < $ambang; $i++) {
        recordFailedLogin($i % 2 === 0 ? 'Owner@Sapi.test' : 'owner@sapi.test');
    }

    artisan('platform:alert-failed-logins');

    // Digabung jadi satu alamat, bukan dua yang masing-masing di bawah ambang.
    Notification::assertSentTimes(PlatformAlert::class, 1);
});

// --- Pola 2: penebakan akun ---

test('banyak alamat berbeda dari satu ip memicu peringatan tersendiri', function () {
    $ambang = config('platform-alerts.failed_login.per_ip_distinct_emails');

    // Tiap alamat hanya dicoba sekali — jauh di bawah ambang per-email, jadi
    // pola ini akan lolos sepenuhnya kalau tidak punya ambangnya sendiri.
    for ($i = 0; $i < $ambang; $i++) {
        recordFailedLogin("korban{$i}@usaha.test", '198.51.100.7');
    }

    artisan('platform:alert-failed-logins');

    Notification::assertSentTimes(PlatformAlert::class, 1);
});

test('satu alamat dicoba dari banyak ip tidak dihitung sebagai penebakan akun', function () {
    $ambang = config('platform-alerts.failed_login.per_ip_distinct_emails');

    for ($i = 0; $i < $ambang + 3; $i++) {
        recordFailedLogin('owner@sapi.test', "198.51.100.{$i}");
    }

    artisan('platform:alert-failed-logins');

    // Belum menyentuh ambang per-email, dan tiap IP hanya punya satu alamat.
    Notification::assertNothingSent();
});

// --- Jeda pengiriman ---

test('peringatan sejenis tidak dikirim ulang selama masa jeda', function () {
    $ambang = config('platform-alerts.failed_login.per_email');

    for ($i = 0; $i < $ambang; $i++) {
        recordFailedLogin('owner@sapi.test');
    }

    artisan('platform:alert-failed-logins');
    artisan('platform:alert-failed-logins');

    // Serangan yang berlangsung semalaman jika tidak akan menghasilkan satu
    // surel tiap jam, dan kotak masuk yang penuh peringatan identik dibaca
    // persis sama seperti kotak masuk tanpa peringatan.
    Notification::assertSentTimes(PlatformAlert::class, 1);
});

test('peringatan untuk alamat berbeda tidak saling menghalangi', function () {
    $ambang = config('platform-alerts.failed_login.per_email');

    foreach (['satu@sapi.test', 'dua@sapi.test'] as $email) {
        for ($i = 0; $i < $ambang; $i++) {
            recordFailedLogin($email);
        }
    }

    artisan('platform:alert-failed-logins');

    Notification::assertSentTimes(PlatformAlert::class, 2);
});

// --- Jejak & mode kering ---

test('pengiriman peringatan tercatat di jejak audit sebagai sensitif', function () {
    $ambang = config('platform-alerts.failed_login.per_email');

    for ($i = 0; $i < $ambang; $i++) {
        recordFailedLogin('owner@sapi.test');
    }

    artisan('platform:alert-failed-logins');

    $log = PlatformAuditLog::where('action', 'alert.sent')->first();

    expect($log)->not->toBeNull()
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE)
        ->and($log->meta['email'])->toBe('owner@sapi.test');
});

test('dry-run tidak mengirim apa pun', function () {
    $ambang = config('platform-alerts.failed_login.per_email');

    for ($i = 0; $i < $ambang; $i++) {
        recordFailedLogin('owner@sapi.test');
    }

    artisan('platform:alert-failed-logins', ['--dry-run' => true])->assertSuccessful();

    Notification::assertNothingSent();
    expect(PlatformAuditLog::where('action', 'alert.sent')->exists())->toBeFalse();
});

test('staf platform tidak ikut menerima peringatan keamanan', function () {
    PlatformUser::factory()->withModules(['tenants'])->create();
    $ambang = config('platform-alerts.failed_login.per_email');

    for ($i = 0; $i < $ambang; $i++) {
        recordFailedLogin('owner@sapi.test');
    }

    artisan('platform:alert-failed-logins');

    // Staf bisa saja hanya dipercaya satu modul; peringatan keamanan memuat
    // hal yang belum tentu boleh mereka lihat.
    Notification::assertSentTimes(PlatformAlert::class, 1);
    Notification::assertSentTo(PlatformUser::where('is_owner', true)->get(), PlatformAlert::class);
});
