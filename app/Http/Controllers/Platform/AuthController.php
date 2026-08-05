<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class AuthController extends Controller
{
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

        $request->session()->regenerate();

        // Rutin: masuk berkali-kali dalam sehari itu wajar. Yang perlu menonjol
        // adalah kegagalannya, bukan keberhasilannya.
        PlatformAuditLog::recordRoutine('login.success');

        return redirect()->to($this->intendedPlatformUrl($request) ?? route('platform.dashboard'));
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
