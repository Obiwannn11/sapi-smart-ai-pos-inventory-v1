<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gerbang kapabilitas untuk permukaan web.
 *
 * Menjawab pertanyaan "outlet ini punya fiturnya?" — berbeda dari `permission`
 * (spatie) yang menjawab "pengguna ini boleh?". Keduanya ortogonal dan sering
 * dipasang berdampingan; yang satu tidak menggantikan yang lain.
 *
 * Kembarannya untuk API/MCP/mobile ada di {@see EnsureTenantFeatureApi}, yang
 * membalas JSON alih-alih abort — konsumennya mesin, bukan peramban.
 */
class EnsureTenantFeature
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $request->user()?->tenant?->hasFeature($feature)) {
            abort(403, 'Fitur ini tidak aktif untuk outlet Anda.');
        }

        return $next($request);
    }
}
