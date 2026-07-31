<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Http\Resources\Platform\TenantResource;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use App\Services\Platform\AccountOverview;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Daftar tenant untuk pemilik SaaS.
 *
 * Controller ini sengaja hanya menyentuh Tenant & relasi akunnya. Model
 * operasional (Transaction, Product, StockMovement, AiAnalysis, Category)
 * TIDAK boleh diimpor di sini — ditegakkan oleh PlatformArchTest.
 *
 * Read-only, tanpa kecuali.
 *
 * Sempat ada satu pengecualian sejak `[BL-015]`: tipe usaha bisa dikoreksi dari
 * sini, sebagai jalan mengisi tenant lama yang mendaftar sebelum pertanyaannya
 * ada. Pengecualian itu dicabut. Kebutuhannya kini dijawab nilai bawaan pada
 * kolomnya plus form di Pengaturan milik pemilik toko — dan itu jawaban yang
 * lebih benar, karena mengubah keterangan usaha orang tanpa sepengetahuannya
 * bukan kewenangan penyedia layanan, sekalipun nilainya ikut menentukan tarif.
 *
 * Nilainya tetap TERLIHAT di sini. Yang dicabut adalah kuasa mengubahnya, bukan
 * kuasa mengetahuinya — pemilik SaaS tetap perlu tahu atas dasar apa sebuah
 * tarif jatuh ke tenant tertentu.
 */
class TenantController extends Controller
{
    public function __construct(private readonly AccountOverview $overview) {}

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

        // Tipe usaha tidak lagi ikut di daftar: ia pindah ke rincian, dan
        // labelnya dirakit di `AccountOverview` dari katalog yang sama
        // (`pricing-dimensions`). Mengirim katalognya ke sini hanya akan jadi
        // payload yang tidak ada yang membaca.
        return Inertia::render('Platform/Tenants/Index', [
            'tenants' => TenantResource::collection($tenants),
        ]);
    }

    /**
     * Rincian satu tenant: identitas, langganan, tagihan, dan kapabilitas
     * kasirnya — dipisah bertab, bukan satu gulungan.
     *
     * Halaman ini dulu milik modul langganan, sehingga daftar yang digerbang
     * `tenants` menaut ke alamat yang digerbang `subscriptions,payments`. Yang
     * pindah hanyalah kepemilikannya; isinya tetap disaring per modul oleh
     * `AccountOverview`, jadi staf yang hanya memegang daftar tenant menerima
     * halaman berisi sebagian — bukan halaman error.
     *
     * Angka omzet TIDAK ikut. Membukanya adalah tindakan tersendiri lewat rute
     * beraudit (`RevenueController`), dan pemisahan itu disengaja: menengok
     * keterangan tenant tidak boleh menghasilkan catatan "membuka data bisnis
     * klien" yang tak pernah benar-benar terjadi.
     */
    public function show(Request $request, Tenant $tenant): Response
    {
        PlatformAuditLog::recordRoutine('tenants.show', $tenant);

        return Inertia::render('Platform/Tenants/Show', [
            ...$this->overview->for($tenant, $request->user()),
            'revenue' => null,
        ]);
    }
}
