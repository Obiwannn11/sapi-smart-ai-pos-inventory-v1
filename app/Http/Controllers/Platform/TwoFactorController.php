<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Services\Platform\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pendaftaran dan pencabutan faktor kedua akun platform ([BL-013]).
 *
 * **Pendaftarannya dua langkah, dan itu bukan basa-basi.** Rahasianya
 * dibangkitkan saat layar dibuka, tapi `two_factor_confirmed_at` baru terisi
 * setelah pengguna mengetikkan kode dari aplikasinya. Tanpa pemisahan itu,
 * membuka layar lalu menutup tab akan mengunci akun dengan rahasia yang tidak
 * pernah masuk ke ponsel mana pun — pada panel yang memegang data
 * administratif seluruh klien, dan tanpa jalan keluar selain menyunting basis
 * data.
 *
 * **Mematikannya menuntut kata sandi.** Sesi yang tertinggal terbuka di
 * komputer bersama tidak boleh bisa mencabut faktor kedua dengan satu klik —
 * itu akan membuat seluruh lapisannya bisa dilepas oleh persis orang yang ia
 * ada untuk hadang.
 */
class TwoFactorController extends Controller
{
    public function __construct(
        private readonly TotpService $totp,
    ) {}

    public function show(Request $request): Response
    {
        $user = $request->user('platform');

        return Inertia::render('Platform/TwoFactorSetup', [
            'enabled' => $user->hasTwoFactorEnabled(),
            'recoveryCodesRemaining' => count($user->two_factor_recovery_codes ?? []),
            'confirmedAt' => $user->two_factor_confirmed_at?->toIso8601String(),
        ]);
    }

    /**
     * Langkah 1 — bangkitkan rahasia dan tampilkan untuk dimasukkan ke
     * aplikasi authenticator.
     */
    public function create(Request $request): RedirectResponse
    {
        $user = $request->user('platform');

        if ($user->hasTwoFactorEnabled()) {
            return back()->with('error', 'Faktor kedua sudah aktif. Matikan dulu bila ingin mendaftar ulang.');
        }

        $secret = $this->totp->generateSecret();

        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Rahasianya diberikan lewat flash sekali-tampil, bukan lewat prop
        // halaman yang tetap. Prop tetap berarti rahasia itu ikut di setiap
        // kunjungan berikutnya ke halaman ini, termasuk setelah pendaftarannya
        // selesai.
        return back()->with('twoFactorSetup', [
            'secret' => $this->totp->formatForDisplay($secret),
            'uri' => $this->totp->provisioningUri($secret, $user->email, config('app.name')),
        ]);
    }

    /**
     * Langkah 2 — buktikan aplikasinya membaca rahasia yang sama, lalu
     * terbitkan kode pemulihan.
     */
    public function confirm(Request $request): RedirectResponse
    {
        $user = $request->user('platform');

        $request->validate(['code' => ['required', 'string', 'max:16']]);

        if (! $user->two_factor_secret) {
            return back()->withErrors(['code' => 'Belum ada pendaftaran yang berjalan. Mulai dari awal.']);
        }

        if (! $this->totp->verify($user->two_factor_secret, (string) $request->input('code'))) {
            return back()->withErrors(['code' => 'Kode tidak cocok. Periksa jam perangkat, lalu coba kode terbaru.']);
        }

        $codes = $this->totp->generateRecoveryCodes();

        $user->forceFill([
            'two_factor_recovery_codes' => $codes,
            'two_factor_confirmed_at' => now(),
        ])->save();

        PlatformAuditLog::record('two-factor.enabled', $user);

        // Sekali tampil, dan halamannya mengatakan begitu. Kode pemulihan yang
        // bisa dilihat lagi kapan saja adalah kode yang tidak pernah dicatat
        // siapa pun.
        return back()->with('recoveryCodes', $codes);
    }

    /**
     * Terbitkan ulang kode pemulihan, tanpa mengganti rahasianya.
     */
    public function regenerateRecoveryCodes(Request $request): RedirectResponse
    {
        $user = $request->user('platform');

        if (! $user->hasTwoFactorEnabled()) {
            return back()->with('error', 'Faktor kedua belum aktif.');
        }

        $this->assertPassword($request);

        $codes = $this->totp->generateRecoveryCodes();

        $user->forceFill(['two_factor_recovery_codes' => $codes])->save();

        PlatformAuditLog::record('two-factor.recovery-codes-regenerated', $user);

        return back()->with('recoveryCodes', $codes);
    }

    public function destroy(Request $request): RedirectResponse
    {
        $user = $request->user('platform');

        $this->assertPassword($request);

        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ])->save();

        // Sensitif, dan justru pencabutannya yang paling perlu terlihat:
        // menyalakan lapisan keamanan adalah kabar baik, mencabutnya bisa jadi
        // langkah pertama seseorang yang baru saja menguasai akun ini.
        PlatformAuditLog::record('two-factor.disabled', $user);

        return back()->with('success', 'Faktor kedua dimatikan.');
    }

    /**
     * Kata sandi wajib untuk tindakan yang MELEMAHKAN akun.
     */
    private function assertPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check((string) $request->input('password'), $request->user('platform')->password)) {
            throw ValidationException::withMessages(['password' => 'Kata sandi salah.']);
        }
    }
}
