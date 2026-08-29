<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\PlatformAuditLog;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Jalan buka kunci pajak, teraudit ([BL-065] butir 4).
 *
 * `tax_enabled` dan `tax_mode` terkunci begitu tenant memungut pajak pada satu
 * penjualan: keduanya mengubah ARTI angka uang yang sudah tercatat. Layar
 * setelan pemilik sudah menyuruhnya "hubungi operator" sejak pajak mendarat —
 * dan sampai controller ini ada, operator tidak punya apa pun untuk menjawab.
 * Janji tanpa mekanisme lebih buruk daripada larangan yang jujur.
 *
 * YANG DIBUKA ADALAH KUNCINYA, BUKAN SETELANNYA. Tidak ada satu pun jalur di
 * sini yang menulis `tax_enabled`, `tax_mode`, `tax_rate`, atau `tax_label`.
 * Operator mengembalikan kemampuan pemilik toko memilih; yang memilih tetap
 * pemilik toko, di layarnya sendiri, dengan peringatan yang sudah ada di sana.
 *
 * Pembedaan itu bukan kerapian. `TenantController` pernah punya kuasa mengubah
 * `business_type` tenant dan kuasa itu DICABUT, dengan alasan yang berlaku
 * persis sama di sini: cara kerja usaha orang bukan milik penyedia layanan,
 * sekalipun nilainya ikut menentukan tarif. Controller ini hidup terpisah dari
 * `TenantController` justru supaya "rincian tenant itu read-only, tanpa
 * kecuali" tetap benar apa adanya.
 *
 * Alasan WAJIB ditulis. Pembukaan tanpa alasan adalah baris audit yang tidak
 * bisa menjawab pertanyaan yang membuatnya dicatat — pola yang sama dengan
 * penjualan di bawah lantai margin (`[BL-018]`), yang juga menuntut alasan
 * tertulis alih-alih sekadar persetujuan.
 */
class TenantTaxLockController extends Controller
{
    /**
     * Berapa lama satu pembukaan berlaku.
     *
     * Tujuh hari, bukan sehari: operator dan pemilik toko jarang duduk di
     * meja yang sama, dan jendela yang habis sebelum pemiliknya sempat
     * membuka aplikasinya hanya menghasilkan permintaan kedua. Bukan pula
     * tanpa batas — kunci yang dibuka selamanya adalah kunci yang mati.
     */
    private const WINDOW_DAYS = 7;

    /**
     * Buka kuncinya untuk satu perubahan.
     */
    public function store(Request $request, Tenant $tenant): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string|min:10|max:500',
        ]);

        $until = now()->addDays(self::WINDOW_DAYS);

        $tenant->update(['tax_lock_opened_until' => $until]);

        PlatformAuditLog::record('tenants.tax-lock.open', $tenant, [
            'reason' => $validated['reason'],
            'until' => $until->toIso8601String(),
            'window_days' => self::WINDOW_DAYS,
            // Keadaan pajak SEBELUM dibuka. Pertanyaan yang muncul saat sebuah
            // pembukaan dipersoalkan selalu "dari apa ke apa", dan jawabannya
            // tidak bisa direkonstruksi dari kolom yang sudah berubah.
            'tax_before' => [
                'enabled' => (bool) $tenant->tax_enabled,
                'mode' => $tenant->tax_mode,
                'rate' => (float) $tenant->tax_rate,
                'label' => $tenant->tax_label,
            ],
        ]);

        return back()->with('success', "Kunci pajak {$tenant->name} dibuka sampai {$until->translatedFormat('j F Y H:i')}.");
    }

    /**
     * Tutup kembali sebelum jendelanya habis.
     *
     * Ada karena pembukaan bisa keliru — salah tenant, salah paham, atau
     * keputusannya berubah. Tanpa ini, satu-satunya cara membatalkan adalah
     * menunggu tujuh hari sambil berharap tidak ada yang memakainya.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $sebelum = $tenant->tax_lock_opened_until?->toIso8601String();

        $tenant->update(['tax_lock_opened_until' => null]);

        PlatformAuditLog::record('tenants.tax-lock.close', $tenant, [
            'was_open_until' => $sebelum,
        ]);

        return back()->with('success', "Kunci pajak {$tenant->name} ditutup kembali.");
    }
}
