<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang untuk hal yang hanya boleh disentuh pemilik SaaS (alias `platform.owner`).
 *
 * Dipakai pada manajemen akun platform. Sengaja bukan modul yang bisa dicentang:
 * kalau ia grantable, staf platform yang memegangnya dapat mencentangkan modul
 * sensitif seperti `revenue_data` untuk dirinya sendiri. Mengikuti pola sisi
 * tenant, di mana manajemen staf/role dijaga `role:owner`.
 */
class EnsurePlatformOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! auth('platform')->user()?->isOwner()) {
            abort(403, 'Hanya pemilik platform yang dapat mengelola akun.');
        }

        return $next($request);
    }
}
