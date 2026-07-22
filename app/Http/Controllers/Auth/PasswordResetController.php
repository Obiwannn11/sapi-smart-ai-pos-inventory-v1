<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pemulihan kata sandi pengguna tenant (owner & staf).
 *
 * Memakai broker `users` bawaan. Owner tenant sebelumnya tak punya jalan
 * keluar sama sekali bila lupa kata sandi — staf masih bisa ditolong owner
 * lewat manajemen staf, tapi owner tidak bisa ditolong siapa pun.
 *
 * Pola mengikuti Platform\PasswordResetController; yang membedakan hanya broker
 * dan rutenya.
 */
class PasswordResetController extends Controller
{
    public function showForgot(): Response
    {
        return Inertia::render('Auth/ForgotPassword');
    }

    public function sendLink(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        // Tenant tidak punya tabel jejak audit sendiri, jadi cukup log aplikasi
        // — bukan untuk ditampilkan ke siapa pun, tapi supaya pola penyisiran
        // alamat masih bisa ditelusuri saat dibutuhkan.
        Log::info('Permintaan pemulihan kata sandi tenant', [
            'email' => $validated['email'],
            'status' => $status,
            'ip' => $request->ip(),
        ]);

        // Balasan SELALU sama, terdaftar atau tidak: kalau dibedakan, halaman
        // ini jadi alat memeriksa apakah sebuah email punya akun di sini.
        return back()->with('success', 'Jika email tersebut terdaftar, tautan pemulihan sudah kami kirimkan.');
    }

    public function showReset(Request $request, string $token): Response
    {
        return Inertia::render('Auth/ResetPassword', [
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

        $status = Password::reset(
            $validated,
            function (User $user, string $password) {
                $user->forceFill([
                    'password' => $password, // cast 'hashed'
                    'remember_token' => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            }
        );

        if ($status !== Password::PASSWORD_RESET) {
            return back()->withErrors([
                'email' => 'Tautan pemulihan tidak berlaku atau sudah kedaluwarsa. Silakan minta tautan baru.',
            ]);
        }

        return redirect()
            ->route('login')
            ->with('success', 'Kata sandi berhasil diubah. Silakan masuk.');
    }
}
