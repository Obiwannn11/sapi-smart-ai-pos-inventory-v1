<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Kembaran JSON dari {@see EnsureTenantFeature} untuk API, MCP, dan mobile.
 *
 * Dipisah karena bentuk jawabannya yang berbeda, bukan logikanya: peramban
 * butuh abort/redirect, sementara n8n dan aplikasi kasir butuh sesuatu yang
 * bisa dibaca mesin.
 */
class EnsureTenantFeatureApi
{
    /**
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! $request->user()?->tenant?->hasFeature($feature)) {
            // `code` yang stabil lebih penting daripada `message`: n8n
            // mencocokkan kode, bukan kalimat, sehingga teksnya bisa diperbaiki
            // tanpa merusak workflow yang sudah berjalan.
            return response()->json([
                'success' => false,
                'code' => 'feature_disabled',
                'message' => "Fitur '{$feature}' sedang tidak aktif untuk outlet ini.",
            ], 403);
        }

        return $next($request);
    }
}
