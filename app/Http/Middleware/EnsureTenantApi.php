<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantApi
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!auth()->check()) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        if (!auth()->user()->tenant_id) {
            return response()->json(['message' => 'User tidak terhubung ke tenant manapun.'], 403);
        }

        return $next($request);
    }
}
