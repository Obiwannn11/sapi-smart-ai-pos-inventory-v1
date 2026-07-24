<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth()->check()) {
            return redirect()->route('login');
        }

        if (! auth()->user()->tenant_id) {
            abort(403, 'User tidak terhubung ke tenant manapun.');
        }

        // Penonaktifan harus menutup sesi yang SEDANG berjalan, bukan hanya
        // menolak login berikutnya. Kalau tidak, staf yang baru dinonaktifkan
        // tetap bisa bekerja sampai ia kebetulan keluar sendiri.
        if (! auth()->user()->is_active) {
            auth()->logout();
            request()->session()->invalidate();
            request()->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Akun ini dinonaktifkan. Hubungi pemilik usaha Anda.',
            ]);
        }

        // Scope spatie roles/permissions to the current tenant (teams feature
        // maps team_id -> tenant_id). Must run after auth + tenant checks.
        app(PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->tenant_id);

        return $next($request);
    }
}
