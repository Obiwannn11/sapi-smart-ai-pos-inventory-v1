<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\PayOpenBillRequest;
use App\Models\Transaction;
use App\Services\OpenBillExpiryService;
use App\Services\TransactionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Pembereskan kas negatif — satu-satunya pintunya ([BL-031]).
 *
 * Kenapa hanya pemilik: kas negatif adalah selisih yang harus
 * dipertanggungjawabkan, dan membiarkan kasir menyuntingnya berarti orang yang
 * bertanggung jawab atas selisih itu juga yang bisa merapikannya. Ini
 * melanjutkan garis yang sudah ada di `canEditTransaction()` — yang ditambahkan
 * di sini adalah TEMPAT suntingannya boleh terjadi, bukan sekadar siapa.
 *
 * Dua pintu, dan pilihannya menentukan stok:
 *
 *   `settle()`  — pelanggannya akhirnya membayar. Stok tetap seperti adanya;
 *                 penjualannya memang terjadi.
 *   `writeOff()`— pemilik menyatakan tagihan ini tak akan pernah dibayar. Di
 *                 sinilah, dan hanya di sini, stok kembali.
 */
class UnsettledBillController extends Controller
{
    public function __construct(
        private TransactionService $transactions,
        private OpenBillExpiryService $expiry,
    ) {}

    /**
     * Pemilik menerima pelunasan terlambat.
     */
    public function settle(PayOpenBillRequest $request, Transaction $transaction): RedirectResponse
    {
        $owner = Auth::user();

        if (! $owner || $transaction->tenant_id !== $owner->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        try {
            $this->transactions->paySettledLateBill($transaction, $request->input('payments'), $owner);

            return back()->with('success', "Kas negatif {$transaction->code} ditutup — tagihan dilunasi.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    /**
     * Pemilik menghapuskan tagihan yang tak akan pernah dibayar.
     */
    public function writeOff(Transaction $transaction): RedirectResponse
    {
        $owner = Auth::user();

        if (! $owner || $transaction->tenant_id !== $owner->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        try {
            $this->expiry->writeOff($transaction, $owner);

            return back()->with('success', "Tagihan {$transaction->code} dihapuskan dan stoknya dikembalikan.");
        } catch (\Exception $e) {
            return back()->with('error', $e->getMessage());
        }
    }
}
