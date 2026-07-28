<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\TenantResource;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar tenant untuk pemilik SaaS.
 *
 * Controller ini sengaja hanya menyentuh Tenant & relasi akunnya. Model
 * operasional (Transaction, Product, StockMovement, AiAnalysis, Category)
 * TIDAK boleh diimpor di sini — ditegakkan oleh PlatformArchTest.
 *
 * Read-only sejak Tahap A, dengan SATU pengecualian sejak `[BL-015]`: tipe
 * usaha bisa dikoreksi dari sini. Alasannya kebutuhan, bukan kemudahan —
 * seluruh tenant yang mendaftar sebelum pertanyaannya ada bernilai kosong, dan
 * tanpa jalan mengisinya dimensi harga itu tidak berguna bagi mereka selamanya.
 */
class TenantController extends Controller
{
    public function index(Request $request): Response
    {
        $tenants = Tenant::query()
            ->withCount('users')
            ->with('owners:id,tenant_id,name,email,email_verified_at')
            // Yang ditandai naik ke atas: itulah satu-satunya baris di halaman
            // ini yang menunggu penilaian seseorang.
            ->orderByRaw('CASE WHEN flagged_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        // Rutin: membuka daftar itu wajar berulang, jadi dideduplikasi per
        // jendela waktu. Tanpa itu satu sesi menengok menghasilkan puluhan
        // baris identik yang menenggelamkan kejadian penting.
        PlatformAuditLog::recordRoutine('tenants.index');

        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => TenantResource::collection($tenants),
            // Pilihan tipe usaha datang dari katalog dimensi harga, bukan dari
            // daftar terpisah. Dua daftar yang harus dijaga sinkron pasti
            // bercabang, dan cabangnya baru terlihat saat sebuah aturan harga
            // diam-diam berhenti cocok.
            'business_types' => config('pricing-dimensions.business_type.options', []),
        ]);
    }

    /**
     * Koreksi tipe usaha satu tenant.
     *
     * Dicatat `sensitive` meski nilainya bukan data bisnis: ia DASAR PENETAPAN
     * HARGA, dan mengubahnya bisa memindahkan tenant ke tarif yang berbeda.
     * Perubahan yang bisa menggeser tagihan orang harus punya jejak yang
     * menyebut nilai lama dan barunya sekaligus.
     */
    public function updateBusinessType(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'business_type' => [
                'nullable',
                Rule::in(array_keys(config('pricing-dimensions.business_type.options', []))),
            ],
        ]);

        $sebelum = $tenant->business_type;

        $tenant->update(['business_type' => $validated['business_type'] ?? null]);

        PlatformAuditLog::record('tenants.business-type.update', $tenant, [
            'before' => $sebelum,
            'after' => $tenant->business_type,
        ]);

        return back()->with('success', 'Tipe usaha diperbarui. Tarif yang sedang berjalan tidak berubah.');
    }
}
