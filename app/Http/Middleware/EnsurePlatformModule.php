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
 *
 * Beberapa modul boleh disebut sekaligus (`platform.can:subscriptions,payments`)
 * dan artinya SALAH SATU cukup. Dipakai halaman yang menyatukan dua urusan —
 * langganan dan tagihannya — di mana pemegang satu modul tetap harus bisa
 * masuk. Yang ditentukan gerbang ini adalah siapa boleh membuka halamannya;
 * bagian mana yang ia lihat di dalam ditentukan controller-nya, per modul.
 */
class EnsurePlatformModule
{
    public function handle(Request $request, Closure $next, string ...$modules): Response
    {
        $platformUser = auth('platform')->user();

        $diizinkan = $platformUser !== null
            && collect($modules)->contains(fn (string $module) => $platformUser->hasModule($module));

        if (! $diizinkan) {
            abort(403, 'Anda tidak memiliki akses ke modul ini.');
        }

        return $next($request);
    }
}
