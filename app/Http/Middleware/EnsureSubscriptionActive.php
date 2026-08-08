<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * Menegakkan siklus hidup langganan di setiap request bertenant.
 *
 * Masa tenggang BERTINGKAT (`[BL-054]`), dan tangganya ada di `Tenant`:
 *
 * - `grace` tahap `soft` & `intensive` — tidak ada yang ditutup di sini sama
 *   sekali. Tekanannya seluruhnya berupa notifikasi. Sampai 2026-08-07
 *   middleware ini memblokir seluruh tulis sejak hari pertama tenggat, artinya
 *   kasir mati dan toko tidak bisa berjualan — menagih dengan merusak sumber
 *   pembayarannya sendiri.
 * - `grace` tahap `locked` — HANYA-BACA. Halaman tetap terbuka, ekspor tetap
 *   jalan, tapi permintaan yang mengubah data ditolak. Menyandera data
 *   pelanggan bukan alat penagihan yang sah; menahan layanan baru adalah.
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

    /**
     * Rute yang, di tahap `locked`, diganti halaman "selesaikan tagihan".
     *
     * Sengaja pendek dan hanya berisi layar kasir: layar itu ada semata-mata
     * untuk berjualan, jadi membukanya di tahap yang melarang berjualan berarti
     * menyodorkan tombol Bayar yang pasti ditolak — persis kejutan yang dilarang
     * `[BL-045]`. Semua layar lain TIDAK ada di sini karena membaca data yang
     * sudah ada tidak pernah dicabut di tahap mana pun (keputusan pemilik
     * 2026-08-07; lihat prinsipnya di `config/subscription.php`). Riwayat
     * transaksi kasir, laporan, dan ekspor tetap terbuka.
     *
     * @var list<string>
     */
    private const LOCKED_STAGE_PAGES = [
        'cashier.pos',
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

        if (! $tenant->isReadOnly()) {
            return $next($request);
        }

        if ($request->isMethodSafe()) {
            return $this->isLockedStagePage($request)
                ? $this->lockedPage($request, $tenant)
                : $next($request);
        }

        return $this->deny(
            $request,
            'Tagihan Anda belum diselesaikan, jadi data baru tidak bisa disimpan. Data lama tetap bisa dibuka dan diunduh.',
            redirectToBilling: false,
        );
    }

    private function isAlwaysAllowed(Request $request): bool
    {
        $route = $request->route();

        return $route !== null && $route->named(...self::ALWAYS_ALLOWED);
    }

    private function isLockedStagePage(Request $request): bool
    {
        $route = $request->route();

        return $route !== null && $route->named(...self::LOCKED_STAGE_PAGES);
    }

    /**
     * Halaman "selesaikan tagihan" beserta tautan pembayarannya.
     *
     * Dirender di tempat, BUKAN diarahkan ke halaman langganan: pengalihan
     * diam-diam membuat pengguna mengira aplikasinya rusak, dan kasir yang
     * mendarat di halaman tagihan tanpa penjelasan tidak tahu apa yang baru saja
     * terjadi pada layar kerjanya (`[BL-054]`(b)). URL-nya sengaja tetap
     * `/cashier/pos` supaya menekan Muat Ulang mengembalikan kasir ke sana
     * begitu tagihannya lunas.
     */
    private function lockedPage(Request $request, Tenant $tenant): Response
    {
        $suspendsAt = app(SubscriptionService::class)->suspensionDateFor($tenant);

        return Inertia::render('Billing/Locked', [
            'graceDay' => $tenant->graceDay(),
            'graceDays' => SubscriptionService::graceDays(),
            'suspendsAt' => $suspendsAt?->toDateString(),
            // Kasir boleh membuka halaman langganan, tapi tiap tombol di sana
            // digerbang `role:owner` — mengirim staf ke sana hanya memindahkan
            // kebuntuan. Alasan yang sama seperti di SubscriptionBanner.
            'isOwner' => $request->user()?->role === 'owner',
        ])->toResponse($request);
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
