<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\PlatformUser;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pemulihan kata sandi akun platform.
 *
 * Memakai broker `platform_users` dengan tabel token sendiri, jadi tak pernah
 * bersinggungan dengan pemulihan kata sandi tenant.
 */
class PasswordResetController extends Controller
{
    public function showForgot(): Response
    {
        return Inertia::render('Platform/ForgotPassword');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = $this->broker()->sendResetLink($validated);

        PlatformAuditLog::record('password_reset.requested', meta: [
            'email' => $validated['email'],
            // Statusnya dicatat walau tak ditampilkan: kalau ada yang menyisir
            // alamat, jejaknya harus bisa dilihat meski penyisirnya tidak
            // mendapat petunjuk apa pun dari layar.
            'status' => $status,
        ]);

        // Balasan SELALU sama, apa pun hasilnya. Membedakan "email tidak
        // terdaftar" dari "tautan terkirim" akan mengubah halaman ini jadi alat
        // pemeriksa keberadaan akun — pada panel yang memegang data seluruh
        // klien, itu petunjuk yang tak perlu diberikan.
        return back()->with('success', 'Jika email tersebut terdaftar, tautan pemulihan sudah kami kirimkan.');
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Platform/ResetPassword', [
            'token' => $token,
            'email' => $request->query('email'),
        ]);
    }

    public function reset(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = $this->broker()->reset(
            $validated,
            function (PlatformUser $platformUser, string $password) {
                $platformUser->forceFill([
                    'password' => $password, // cast 'hashed'
                    'remember_token' => Str::random(60),
                ])->save();

                PlatformAuditLog::record('password_reset.completed', $platformUser, [
                    'email' => $platformUser->email,
                ]);

                event(new PasswordReset($platformUser));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => 'Tautan pemulihan tidak berlaku atau sudah kedaluwarsa. Silakan minta tautan baru.',
            ]);
        }

        return redirect()
            ->route('platform.login')
            ->with('success', 'Kata sandi berhasil diubah. Silakan masuk.');
    }

    private function broker(): \Illuminate\Contracts\Auth\PasswordBroker
    {
        return Password::broker('platform_users');
    }
}
