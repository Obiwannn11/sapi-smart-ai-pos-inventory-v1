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
            PlatformAuditLog::create([
                'action' => 'login.failed',
                'meta' => ['email' => $credentials['email']],
                'ip' => $request->ip(),
            ]);

            return back()->withErrors(['email' => 'Email atau password salah.']);
        }

        $request->session()->regenerate();

        PlatformAuditLog::record('login.success');

        return redirect()->intended(route('platform.dashboard'));
    }

    public function logout(Request $request): RedirectResponse
    {
        PlatformAuditLog::record('logout');

        Auth::guard('platform')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('platform.login');
    }
}
