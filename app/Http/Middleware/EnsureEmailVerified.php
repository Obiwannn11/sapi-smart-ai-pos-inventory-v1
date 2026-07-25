<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menahan tenant yang alamat surelnya belum diverifikasi.
 *
 * Inilah yang membuat pendaftaran berulang demi trial gratis jadi mahal: tanpa
 * alamat yang benar-benar bisa dibuka, akun barunya tidak bisa dipakai untuk
 * apa pun. Sekadar menandai tanpa menahan tidak akan mengubah perhitungan
 * siapa pun.
 *
 * Staf yang dibuat owner TIDAK terkena — mereka sudah ditandai terverifikasi
 * saat dibuat, karena owner-lah yang menjamin mereka. Kalau tidak, kasir yang
 * tidak punya alamat surel sendiri tak akan pernah bisa masuk.
 */
class EnsureEmailVerified
{
    /**
     * Rute yang tetap terbuka bagi yang belum terverifikasi.
     *
     * @var list<string>
     */
    private const ALWAYS_ALLOWED = [
        'logout',
        'verification.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $user->hasVerifiedEmail() || $this->isAlwaysAllowed($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Alamat email Anda belum diverifikasi. Buka tautan yang kami kirim ke email Anda.',
            ], 403);
        }

        return redirect()->route('verification.notice');
    }

    private function isAlwaysAllowed(Request $request): bool
    {
        $route = $request->route();

        return $route !== null && $route->named(...self::ALWAYS_ALLOWED);
    }
}
