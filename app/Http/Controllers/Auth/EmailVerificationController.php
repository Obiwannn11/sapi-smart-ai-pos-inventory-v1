<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Verifikasi alamat surel pemilik usaha yang mendaftar sendiri.
 */
class EmailVerificationController extends Controller
{
    public function notice(Request $request): RedirectResponse|Response
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/owner/dashboard');
        }

        return inertia('Auth/VerifyEmail', [
            'email' => $request->user()->email,
        ]);
    }

    /**
     * `EmailVerificationRequest` bawaan Laravel yang memvalidasi tanda tangan
     * dan mencocokkan hash alamatnya — bagian yang tidak boleh ditulis ulang
     * sendiri.
     */
    public function verify(EmailVerificationRequest $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/owner/dashboard');
        }

        $request->fulfill();

        return redirect()->intended('/owner/dashboard')
            ->with('success', 'Email Anda terverifikasi. Selamat datang di SAPI POS.');
    }

    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->intended('/owner/dashboard');
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Tautan verifikasi baru sudah dikirim ke email Anda.');
    }
}
