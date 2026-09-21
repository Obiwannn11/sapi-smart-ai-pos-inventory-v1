<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (! auth()->user()->tenant_id) {
            return response()->json(['message' => 'User tidak terhubung ke tenant manapun.'], 403);
        }

        // Penonaktifan harus mencabut token yang sudah beredar, bukan hanya
        // menolak login berikutnya — aplikasi kasir memegang tokennya
        // berminggu-minggu dan tidak pernah "keluar sendiri".
        if (! auth()->user()->is_active) {
            auth()->user()->currentAccessToken()?->delete();

            return response()->json(['message' => 'Akun ini dinonaktifkan. Hubungi pemilik usaha Anda.'], 403);
        }

        // Scope spatie roles/permissions to the current tenant (teams feature
        // maps team_id -> tenant_id). Must run after auth + tenant checks.
        app(PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->tenant_id);

        return $next($request);
    }
}
