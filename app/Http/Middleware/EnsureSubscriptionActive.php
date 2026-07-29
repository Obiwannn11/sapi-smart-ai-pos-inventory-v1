<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan siklus hidup langganan di setiap request bertenant.
 *
 * Dua keadaan yang membatasi:
 *
 * - `grace` — HANYA-BACA. Halaman tetap terbuka, ekspor tetap jalan, tapi
 *   permintaan yang mengubah data ditolak. Menyandera data pelanggan bukan
 *   alat penagihan yang sah; menahan layanan baru adalah.
 * - `suspended` — akses ditutup, semua diarahkan ke halaman langganan.
 *
 * Rute langganan dan logout SELALU terbuka. Menutup jalan keluar dari keadaan
 * terbatas berarti tenant tidak akan pernah bisa keluar darinya — termasuk
 * dengan membayar.
 */
class EnsureSubscriptionActive
{
    /**
     * Rute yang tetap terbuka di keadaan apa pun.
     *
     * @var list<string>
     */
    private const ALWAYS_ALLOWED = [
        'logout',
        'billing.*',
        // Menyelesaikan pesanan yang sudah diterima bukan "layanan baru".
        // Seluruh aksi papan adalah POST, jadi tanpa pengecualian ini masa
        // tenggang akan membekukan dapur di tengah antrean — menyandera
        // pelanggan yang sudah membayar, persis yang ditolak docblock kelas
        // ini. Tak satu pun rute di bawah pola ini menciptakan penjualan baru.
        //
        // Halaman papannya sendiri bernama `cashier.queue` tanpa akhiran,
        // sehingga TIDAK tercakup pola ini — dan itu tidak masalah karena GET
        // sudah lolos sebagai method aman.
        'cashier.queue.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $tenant = $request->user()?->tenant;

        if (! $tenant instanceof Tenant || $this->isAlwaysAllowed($request)) {
            return $next($request);
        }

        if ($tenant->isSuspended()) {
            return $this->deny(
                $request,
                'Langganan Anda ditangguhkan. Selesaikan pembayaran untuk mengaktifkan kembali.',
                redirectToBilling: true,
            );
        }

        if ($tenant->isReadOnly() && ! $request->isMethodSafe()) {
            return $this->deny(
                $request,
                'Masa langganan Anda sudah berakhir, jadi data baru tidak bisa disimpan. Data lama tetap bisa dibuka dan diunduh.',
                redirectToBilling: false,
            );
        }

        return $next($request);
    }

    private function isAlwaysAllowed(Request $request): bool
    {
        $route = $request->route();

        return $route !== null && $route->named(...self::ALWAYS_ALLOWED);
    }

    private function deny(Request $request, string $message, bool $redirectToBilling): Response
    {
        if ($request->expectsJson()) {
            return response()->json(['message' => $message], 403);
        }

        // Penolakan tulis dikembalikan lewat `back()` agar pesannya muncul di
        // halaman tempat tombolnya ditekan. Penangguhan mengarahkan ke halaman
        // langganan karena di sanalah satu-satunya tindakan yang masih berguna.
        return $redirectToBilling
            ? redirect()->route('billing.show')->with('error', $message)
            : back()->with('error', $message);
    }
}
