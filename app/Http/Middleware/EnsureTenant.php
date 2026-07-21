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

        // Scope spatie roles/permissions to the current tenant (teams feature
        // maps team_id -> tenant_id). Must run after auth + tenant checks.
        app(PermissionRegistrar::class)->setPermissionsTeamId(auth()->user()->tenant_id);

        return $next($request);
    }
}
