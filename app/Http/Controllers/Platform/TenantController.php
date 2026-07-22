<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\TenantResource;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar tenant untuk pemilik SaaS — read-only di Tahap A.
 *
 * Controller ini sengaja hanya menyentuh Tenant & relasi akunnya. Model
 * operasional (Transaction, Product, StockMovement, AiAnalysis, Category)
 * TIDAK boleh diimpor di sini — ditegakkan oleh PlatformArchTest.
 */
class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->with('owners:id,tenant_id,name,email')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Rutin: membuka daftar itu wajar berulang, jadi dideduplikasi per
        // jendela waktu. Tanpa itu satu sesi menengok menghasilkan puluhan
        // baris identik yang menenggelamkan kejadian penting.
        PlatformAuditLog::recordRoutine('tenants.index');

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => TenantResource::collection($tenants),
        ]);
    }
}
