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

        return redirect()->intended(route('platform.dashboard'));
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
