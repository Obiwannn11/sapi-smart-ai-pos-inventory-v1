<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang modul RBAC untuk API (alias `permission.api`).
 *
 * Kembaran JSON dari middleware `permission:` milik spatie yang dipakai sisi
 * web. Bedanya hanya di jawabannya: spatie melempar 403 berbentuk halaman HTML,
 * dan aplikasi kasir yang menerimanya akan menampilkan halaman error mentah
 * alih-alih pesan yang bisa dibaca.
 *
 * Katalog modulnya SAMA — `config/rbac.php` — dan pemeriksaannya lewat Gate
 * yang sama, jadi izin yang berlaku di web pasti berlaku sama di mobile.
 * Owner otomatis lolos lewat Gate::before, seperti di web.
 *
 * Wajib dipasang SETELAH `tenant.api`: middleware itulah yang menetapkan
 * team-id spatie ke tenant yang sedang masuk. Tanpa urutan itu, pemeriksaannya
 * berjalan tanpa konteks tenant dan jawabannya tidak bisa dipercaya.
 */
class EnsureModuleApi
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->can($module)) {
            $label = config("rbac.modules.{$module}.label", $module);

            return response()->json([
                'message' => "Anda tidak memiliki akses ke modul {$label}.",
            ], 403);
        }

        return $next($request);
    }
}
