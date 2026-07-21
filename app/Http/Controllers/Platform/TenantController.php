<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\PlatformTenantResource;
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

        // Dicatat sejak Tahap A: kebiasaan mencatat akses harus terbentuk saat
        // datanya masih administratif, bukan baru dipasang nanti ketika data
        // omset sudah ikut terlihat.
        PlatformAuditLog::record('tenants.index', meta: ['page' => $tenants->currentPage()]);

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => PlatformTenantResource::collection($tenants),
        ]);
    }
}
