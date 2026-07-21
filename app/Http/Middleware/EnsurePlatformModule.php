<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang modul untuk panel platform (alias `platform.can`).
 *
 * Sengaja terpisah dari alias `permission` milik tenant: alias itu memakai
 * spatie yang bergantung pada team-id per tenant, sedangkan akun platform tidak
 * punya tenant sama sekali. Menyatukannya hanya akan menyeret konteks team ke
 * tempat yang tidak punya team.
 */
class EnsurePlatformModule
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        $platformUser = auth('platform')->user();

        if (! $platformUser || ! $platformUser->hasModule($module)) {
            abort(403, 'Anda tidak memiliki akses ke modul ini.');
        }

        return $next($request);
    }
}
