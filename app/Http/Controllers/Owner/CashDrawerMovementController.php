<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\CashDrawerMovement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Persetujuan uang keluar laci ([BL-087]).
 *
 * **Owner-eksklusif, bukan modul RBAC baru** — alasan yang sama persis dengan
 * `UpsellRuleController`: menyetujui uang yang keluar dari laci adalah
 * keputusan pemilik usaha, bukan tugas yang dilimpahkan. Menggantungkannya
 * pada izin modul `cash_drawer` akan memberi kuasa MENYETUJUI kepada setiap
 * kasir yang diberi hak membuka sesi kas — yaitu justru orang yang
 * pengeluarannya sedang ditinjau.
 *
 * Yang ditinjau di sini hanya mutasi di atas ambang tenant. Yang di bawahnya
 * sudah disetujui otomatis saat dicatat dan tidak pernah sampai ke layar ini.
 */
class CashDrawerMovementController extends Controller
{
    public function approve(CashDrawerMovement $movement): RedirectResponse
    {
        return $this->review($movement, CashDrawerMovement::STATUS_APPROVED, 'disetujui');
    }

    public function reject(CashDrawerMovement $movement): RedirectResponse
    {
        return $this->review($movement, CashDrawerMovement::STATUS_REJECTED, 'ditolak');
    }

    /**
     * Satu jalur untuk kedua keputusan.
     *
     * Keduanya menulis kolom yang sama dan menuntut penjaga yang sama;
     * memisahkannya jadi dua metode penuh berarti penjaga itu harus diingat
     * dua kali — dan yang terlupa akan selalu yang menolak, karena ia lebih
     * jarang dipakai.
     */
    private function review(CashDrawerMovement $movement, string $status, string $verb): RedirectResponse
    {
        $user = Auth::user();

        if (! $user?->isOwner() || $movement->tenant_id !== $user->tenant_id) {
            abort(403, 'Hanya pemilik yang dapat meninjau mutasi kas.');
        }

        // Keputusan yang sudah diambil tidak diputar balik dari layar ini.
        // Membalik persetujuan berarti `expected_amount` sebuah sesi berubah
        // SESUDAH kasirnya menghitung uang dan menandatangani selisihnya — dan
        // itu menuduh orang atas angka yang berbeda dari yang ia lihat.
        if ($movement->status !== CashDrawerMovement::STATUS_PENDING) {
            return back()->with('error', 'Mutasi ini sudah pernah ditinjau.');
        }

        $movement->update([
            'status' => $status,
            'reviewed_by' => $user->id,
            'reviewed_at' => now(),
        ]);

        return back()->with('success', "Mutasi kas {$verb}.");
    }
}
