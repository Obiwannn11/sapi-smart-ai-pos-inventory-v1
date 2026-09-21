<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use App\Services\Platform\TotpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
    /**
     * Kunci sesi tempat id akun menunggu antara kata sandi benar dan kode
     * kedua benar ([BL-013]).
     *
     * Yang disimpan hanya ID — bukan objek pengguna, dan sama sekali bukan
     * kredensialnya. Sesi ini belum terautentikasi: `Auth::guard('platform')`
     * baru dipanggil setelah faktor kedua terbukti.
     */
    private const PENDING_KEY = 'platform.two_factor.pending_id';

    public function __construct(
        private readonly TotpService $totp,
    ) {}

    public function showLogin(): Response
    {
        return Inertia::render('Platform/Login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::guard('platform')->attempt($credentials, $request->boolean('remember'))) {
            // Percobaan gagal ikut dicatat: pola percobaan masuk ke panel yang
            // memegang data seluruh klien adalah hal yang perlu terlihat.
            // Sensitif: percobaan masuk yang gagal ke panel pemegang data
            // seluruh klien adalah sinyal keamanan, bukan kebisingan.
            PlatformAuditLog::create([
                'action' => 'login.failed',
                'severity' => PlatformAuditLog::SEVERITY_SENSITIVE,
                'meta' => ['email' => $credentials['email']],
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['email' => __('auth.failed')]);
        }

        $user = Auth::guard('platform')->user();

        // Faktor kedua ([BL-013]). Kata sandi yang benar BELUM berarti masuk:
        // sesinya dilepas kembali dan yang tersisa hanya id yang menunggu di
        // sesi. Membiarkan sesi tetap terautentikasi sambil "meminta" kode
        // adalah gerbang yang bisa dilewati dengan menutup modalnya.
        if ($user->hasTwoFactorEnabled()) {
            Auth::guard('platform')->logout();

            $request->session()->put(self::PENDING_KEY, $user->id);
            $request->session()->put('platform.two_factor.remember', $request->boolean('remember'));

            return redirect()->route('platform.two-factor.challenge');
        }

        $request->session()->regenerate();

        // Rutin: masuk berkali-kali dalam sehari itu wajar. Yang perlu menonjol
        // adalah kegagalannya, bukan keberhasilannya.
        PlatformAuditLog::recordRoutine('login.success');

        return redirect()->to($this->intendedPlatformUrl($request) ?? route('platform.dashboard'));
    }

    /**
     * Layar kode kedua. Hanya bisa dibuka oleh sesi yang baru saja melewati
     * kata sandi.
     */
    public function showChallenge(Request $request): Response|RedirectResponse
    {
        if (! $this->pendingUser($request)) {
            return redirect()->route('platform.login');
        }

        return Inertia::render('Platform/TwoFactorChallenge');
    }

    /**
     * Verifikasi kode TOTP atau kode pemulihan.
     */
    public function challenge(Request $request): RedirectResponse
    {
        $user = $this->pendingUser($request);

        if (! $user) {
            return redirect()->route('platform.login');
        }

        $request->validate([
            'code' => ['required', 'string', 'max:64'],
        ]);

        $code = (string) $request->input('code');

        $viaTotp = $this->totp->verify($user->two_factor_secret, $code);

        // Kode pemulihan hanya dicoba SETELAH TOTP gagal. Urutan sebaliknya
        // akan membakar satu kode pemulihan tiap kali seseorang salah ketik
        // digit terakhir.
        $viaRecovery = ! $viaTotp && $user->consumeRecoveryCode($code);

        if (! $viaTotp && ! $viaRecovery) {
            // Sensitif, dengan alasan yang sama seperti login.failed: kata
            // sandi yang benar diikuti kode kedua yang salah berkali-kali
            // adalah bentuk yang tidak dihasilkan pemilik akun yang sah.
            PlatformAuditLog::create([
                'action' => 'two-factor.failed',
                'severity' => PlatformAuditLog::SEVERITY_SENSITIVE,
                'meta' => ['email' => $user->email],
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['code' => 'Kode tidak cocok. Coba lagi, atau pakai kode pemulihan.']);
        }

        $remember = (bool) $request->session()->pull('platform.two_factor.remember', false);
        $request->session()->forget(self::PENDING_KEY);

        Auth::guard('platform')->login($user, $remember);
        $request->session()->regenerate();

        PlatformAuditLog::recordRoutine('login.success');

        if ($viaRecovery) {
            // Sensitif: kode pemulihan dipakai berarti authenticator-nya hilang
            // ATAU seseorang lain yang memegangnya. Keduanya perlu terlihat.
            PlatformAuditLog::record('two-factor.recovery-used', $user, [
                'remaining_codes' => count($user->fresh()->two_factor_recovery_codes ?? []),
            ]);
        }

        return redirect()->to($this->intendedPlatformUrl($request) ?? route('platform.dashboard'));
    }

    /**
     * Akun yang sedang menunggu faktor kedua, atau null.
     */
    private function pendingUser(Request $request): ?PlatformUser
    {
        $id = $request->session()->get(self::PENDING_KEY);

        if (! $id) {
            return null;
        }

        $user = PlatformUser::find($id);

        // Faktor kedua yang dimatikan sementara sesi menunggu membuat id ini
        // tidak lagi berarti apa-apa.
        return $user?->hasTwoFactorEnabled() ? $user : null;
    }

    /**
     * Tujuan tersimpan dari `url.intended`, tapi HANYA bila ia benar-benar
     * berada di area platform.
     *
     * Kuncinya satu untuk kedua guard karena sesinya satu, jadi peramban yang
     * pernah menyentuh area tenant dalam keadaan keluar meninggalkan tujuan
     * tenant di sana. `intended()` polos memenangkan nilai itu atas argumen
     * bawaannya: login platform berhasil, audit log mencatat `login.success`,
     * lalu EnsureTenant menolak dan layar yang muncul adalah halaman masuk
     * pemilik usaha. Gejalanya bersyarat — sesi peramban yang bersih tidak
     * pernah mengalaminya, dan itu sebabnya ia bisa lolos sekian lama.
     *
     * Disaring, bukan dihapus: akun platform yang mengklik tautan langsung ke
     * /platform/tenants/7 lalu diminta masuk tetap layak dikembalikan ke sana.
     */
    private function intendedPlatformUrl(Request $request): ?string
    {
        // `pull` dan bukan `get`: nilai yang ditolak harus ikut hangus, kalau
        // tidak ia akan menunggu di sesi dan menyerang perpindahan halaman
        // berikutnya yang kebetulan memakai intended().
        $intended = $request->session()->pull('url.intended');

        if (! is_string($intended) || $intended === '') {
            return null;
        }

        // Host ikut diperiksa. Hari ini kunci itu hanya ditulis middleware kita
        // sendiri, tapi memeriksa path saja akan meloloskan URL absolut ke host
        // lain yang kebetulan memuat prefiks yang sama.
        $host = parse_url($intended, PHP_URL_HOST);

        if ($host !== null && $host !== $request->getHost()) {
            return null;
        }

        $path = trim((string) parse_url($intended, PHP_URL_PATH), '/');

        // Dicocokkan sebagai segmen utuh, bukan awalan string: '/platformx'
        // bukan area platform.
        return ($path === 'platform' || str_starts_with($path, 'platform/'))
            ? $intended
            : null;
    }

    public function logout(Request $request): RedirectResponse
    {
        PlatformAuditLog::recordRoutine('logout');

        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
