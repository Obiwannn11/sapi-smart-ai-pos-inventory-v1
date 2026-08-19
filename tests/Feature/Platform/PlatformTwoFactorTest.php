<?php

use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Services\Platform\TotpService;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\delete;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

/**
 * Faktor kedua akun platform (`[BL-013]`).
 *
 * Yang dijaga di sini bukan "kodenya cocok" — itu aritmetika. Yang dijaga
 * adalah hal-hal yang membuat sebuah 2FA jadi teater: sesi yang terlanjur
 * masuk sambil "diminta" kode, kode pemulihan yang bisa dipakai berulang,
 * rahasia yang lahir lalu mengunci akun sebelum sempat dibuktikan, dan
 * pencabutan yang bisa dilakukan sesi yang tertinggal terbuka.
 */

/** Kode yang sah untuk rahasia tersebut, dihitung dengan algoritma yang sama. */
function totpCodeFor(string $secret): string
{
    // Diambil lewat verify() yang sama tidak mungkin — ia hanya menjawab
    // benar/salah. Jadi kode dihitung ulang di sini dengan menembus visibility,
    // supaya test menguji jalur verifikasinya, bukan menyalin implementasinya.
    $service = app(TotpService::class);

    $method = new ReflectionMethod($service, 'codeAt');
    $method->setAccessible(true);

    return $method->invoke($service, $secret, (int) floor(time() / 30));
}

/**
 * @return array{user: PlatformUser, secret: string}
 */
function enrolledPlatformUser(): array
{
    $secret = app(TotpService::class)->generateSecret();

    $user = PlatformUser::factory()->create(['password' => 'rahasia-panjang']);

    $user->forceFill([
        'two_factor_secret' => $secret,
        'two_factor_recovery_codes' => ['AAAAA-BBBBB', 'CCCCC-DDDDD'],
        'two_factor_confirmed_at' => now(),
    ])->save();

    return ['user' => $user, 'secret' => $secret];
}

// --- Algoritma ---

test('kode yang dibangkitkan diterima, kode lain ditolak', function () {
    $service = app(TotpService::class);
    $secret = $service->generateSecret();

    expect($service->verify($secret, totpCodeFor($secret)))->toBeTrue()
        ->and($service->verify($secret, '000000'))->toBeFalse()
        ->and($service->verify($secret, 'bukan-angka'))->toBeFalse()
        // Panjang yang salah tidak boleh diperlakukan sebagai kode pendek yang
        // kebetulan cocok.
        ->and($service->verify($secret, '12345'))->toBeFalse();
});

test('rahasia yang berbeda tidak menerima kode satu sama lain', function () {
    $service = app(TotpService::class);

    $a = $service->generateSecret();
    $b = $service->generateSecret();

    expect($service->verify($b, totpCodeFor($a)))->toBeFalse();
});

test('URI provisioning memuat rahasia dan penerbitnya', function () {
    $service = app(TotpService::class);
    $secret = $service->generateSecret();

    $uri = $service->provisioningUri($secret, 'admin@example.test', 'SAPI');

    expect($uri)->toStartWith('otpauth://totp/')
        ->and($uri)->toContain('secret='.$secret)
        ->and($uri)->toContain('issuer=SAPI');
});

// --- Login ---

test('akun tanpa faktor kedua masuk seperti biasa', function () {
    $user = PlatformUser::factory()->create(['password' => 'rahasia-panjang']);

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
        ->assertRedirect(route('platform.dashboard'));

    expect(auth('platform')->check())->toBeTrue();
});

test('kata sandi benar BELUM memasukkan akun yang berfaktor kedua', function () {
    ['user' => $user] = enrolledPlatformUser();

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang'])
        ->assertRedirect(route('platform.two-factor.challenge'));

    // Inti seluruh entri ini. Sesi yang terlanjur terautentikasi sambil
    // "diminta" kode adalah gerbang yang bisa dilewati dengan menutup
    // halamannya.
    expect(auth('platform')->check())->toBeFalse();
});

test('layar kode kedua tidak bisa dibuka tanpa melewati kata sandi', function () {
    get('/platform/two-factor')->assertRedirect(route('platform.login'));
});

test('kode authenticator yang benar menyelesaikan login', function () {
    ['user' => $user, 'secret' => $secret] = enrolledPlatformUser();

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang']);

    post('/platform/two-factor', ['code' => totpCodeFor($secret)])
        ->assertRedirect(route('platform.dashboard'));

    expect(auth('platform')->id())->toBe($user->id);
});

test('kode yang salah tidak memasukkan siapa pun dan tercatat sebagai sensitif', function () {
    ['user' => $user] = enrolledPlatformUser();

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang']);

    post('/platform/two-factor', ['code' => '000000'])->assertSessionHasErrors('code');

    expect(auth('platform')->check())->toBeFalse();

    $log = PlatformAuditLog::where('action', 'two-factor.failed')->first();

    expect($log)->not->toBeNull()
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE);
});

// --- Kode pemulihan ---

test('kode pemulihan menyelesaikan login dan hangus setelah dipakai', function () {
    ['user' => $user] = enrolledPlatformUser();

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang']);
    post('/platform/two-factor', ['code' => 'AAAAA-BBBBB'])->assertRedirect(route('platform.dashboard'));

    expect(auth('platform')->id())->toBe($user->id)
        // Sekali pakai. Kode pemulihan yang bisa dipakai berulang adalah kata
        // sandi kedua yang tercetak di kertas.
        ->and($user->fresh()->two_factor_recovery_codes)->toBe(['CCCCC-DDDDD']);

    expect(PlatformAuditLog::where('action', 'two-factor.recovery-used')->exists())->toBeTrue();
});

test('kode pemulihan yang sudah hangus tidak bisa dipakai lagi', function () {
    ['user' => $user] = enrolledPlatformUser();

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang']);
    post('/platform/two-factor', ['code' => 'AAAAA-BBBBB']);

    post('/platform/logout');

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang']);
    post('/platform/two-factor', ['code' => 'AAAAA-BBBBB'])->assertSessionHasErrors('code');

    expect(auth('platform')->check())->toBeFalse();
});

test('kode TOTP yang salah ketik tidak membakar kode pemulihan', function () {
    ['user' => $user] = enrolledPlatformUser();

    post('/platform/login', ['email' => $user->email, 'password' => 'rahasia-panjang']);
    post('/platform/two-factor', ['code' => '111111']);

    // TOTP dicoba lebih dulu, kode pemulihan hanya SETELAH ia gagal — dan
    // sebuah salah ketik enam angka tidak menyerupai kode pemulihan mana pun,
    // jadi daftarnya harus utuh.
    expect($user->fresh()->two_factor_recovery_codes)->toHaveCount(2);
});

// --- Pendaftaran ---

test('pendaftaran belum berlaku sampai satu kode dibuktikan', function () {
    $user = PlatformUser::factory()->create(['password' => 'rahasia-panjang']);

    actingAs($user, 'platform');

    post('/platform/keamanan/two-factor')->assertSessionHas('twoFactorSetup');

    $user->refresh();

    // Rahasianya sudah lahir, TAPI akunnya belum terkunci. Membuka layar lalu
    // menutup tab tidak boleh mengunci akun dengan rahasia yang tidak pernah
    // masuk ke ponsel mana pun.
    expect($user->two_factor_secret)->not->toBeNull()
        ->and($user->hasTwoFactorEnabled())->toBeFalse();
});

test('kode yang benar menyelesaikan pendaftaran dan menerbitkan kode pemulihan', function () {
    $user = PlatformUser::factory()->create(['password' => 'rahasia-panjang']);

    actingAs($user, 'platform');
    post('/platform/keamanan/two-factor');

    $secret = $user->fresh()->two_factor_secret;

    post('/platform/keamanan/two-factor/konfirmasi', ['code' => totpCodeFor($secret)])
        ->assertSessionHas('recoveryCodes');

    $user->refresh();

    expect($user->hasTwoFactorEnabled())->toBeTrue()
        ->and($user->two_factor_recovery_codes)->toHaveCount(8);

    $log = PlatformAuditLog::where('action', 'two-factor.enabled')->first();

    expect($log)->not->toBeNull()
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE);
});

test('kode yang salah tidak menyelesaikan pendaftaran', function () {
    $user = PlatformUser::factory()->create(['password' => 'rahasia-panjang']);

    actingAs($user, 'platform');
    post('/platform/keamanan/two-factor');

    post('/platform/keamanan/two-factor/konfirmasi', ['code' => '000000'])
        ->assertSessionHasErrors('code');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeFalse();
});

// --- Pencabutan ---

test('mematikan faktor kedua menuntut kata sandi', function () {
    ['user' => $user] = enrolledPlatformUser();

    actingAs($user, 'platform');

    // Sesi yang tertinggal terbuka di komputer bersama tidak boleh bisa
    // melepas lapisan ini dengan satu klik.
    delete('/platform/keamanan/two-factor', ['password' => 'salah'])
        ->assertSessionHasErrors('password');

    expect($user->fresh()->hasTwoFactorEnabled())->toBeTrue();
});

test('kata sandi yang benar mematikan faktor kedua dan tercatat sebagai sensitif', function () {
    ['user' => $user] = enrolledPlatformUser();

    actingAs($user, 'platform');

    delete('/platform/keamanan/two-factor', ['password' => 'rahasia-panjang'])
        ->assertSessionHas('success');

    $user->refresh();

    expect($user->hasTwoFactorEnabled())->toBeFalse()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->two_factor_recovery_codes)->toBeNull();

    $log = PlatformAuditLog::where('action', 'two-factor.disabled')->first();

    // Justru pencabutannya yang paling perlu terlihat: menyalakan lapisan
    // keamanan adalah kabar baik, mencabutnya bisa jadi langkah pertama
    // seseorang yang baru menguasai akun ini.
    expect($log)->not->toBeNull()
        ->and($log->severity)->toBe(PlatformAuditLog::SEVERITY_SENSITIVE);
});

test('menerbitkan ulang kode pemulihan menuntut kata sandi dan mengganti seluruh daftarnya', function () {
    ['user' => $user] = enrolledPlatformUser();

    actingAs($user, 'platform');

    post('/platform/keamanan/two-factor/kode-pemulihan', ['password' => 'salah'])
        ->assertSessionHasErrors('password');

    post('/platform/keamanan/two-factor/kode-pemulihan', ['password' => 'rahasia-panjang'])
        ->assertSessionHas('recoveryCodes');

    $codes = $user->fresh()->two_factor_recovery_codes;

    expect($codes)->toHaveCount(8)
        ->and($codes)->not->toContain('AAAAA-BBBBB');
});

// --- Kerahasiaan ---

test('rahasia dan kode pemulihan tidak pernah ikut serialisasi model', function () {
    ['user' => $user] = enrolledPlatformUser();

    $array = $user->toArray();

    expect($array)->not->toHaveKey('two_factor_secret')
        ->and($array)->not->toHaveKey('two_factor_recovery_codes');
});

test('rahasia tersimpan terenkripsi di basis data', function () {
    ['user' => $user, 'secret' => $secret] = enrolledPlatformUser();

    $raw = DB::table('platform_users')->where('id', $user->id)->value('two_factor_secret');

    // Dump basis data yang bocor tidak boleh langsung berarti kode sah
    // selamanya.
    expect($raw)->not->toBe($secret)
        ->and($user->fresh()->two_factor_secret)->toBe($secret);
});

test('halaman keamanan terbuka untuk staf platform tanpa modul apa pun', function () {
    $staff = PlatformUser::factory()->create(['is_owner' => false, 'password' => 'rahasia-panjang']);

    actingAs($staff, 'platform');

    // Keamanan akun sendiri bukan modul yang bisa dipegangkan atau ditahan.
    get('/platform/keamanan/two-factor')->assertOk();
});
