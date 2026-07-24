<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Penambahan seat oleh tenant, dan pengunggahan bukti transfernya.
 *
 * Alur upgrade provisional: tenant meminta tambahan seat → tagihan terbit →
 * bukti diunggah → seat LANGSUNG berlaku → pemilik SaaS memeriksa belakangan.
 * Menunggu verifikasi manual berarti warung yang kedatangan kasir baru pagi ini
 * tidak bisa mempekerjakannya sampai seseorang membuka email — dan itu alasan
 * yang buruk untuk menahan sebuah usaha.
 */
class UpgradeController extends Controller
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'additional_seats' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $tenant = $request->user()->tenant;

        if ($this->subscriptions->openUpgradeInvoice($tenant) !== null) {
            return back()->with('error', 'Masih ada permintaan penambahan pengguna yang belum selesai. Selesaikan dulu yang itu.');
        }

        $this->subscriptions->requestSeatUpgrade($tenant, $validated['additional_seats']);

        return back()->with('success', 'Tagihan penambahan pengguna diterbitkan. Unggah bukti transfer untuk mengaktifkannya.');
    }

    /**
     * Unggah bukti transfer untuk sebuah tagihan.
     */
    public function storeProof(Request $request, Invoice $invoice): RedirectResponse
    {
        abort_if($invoice->tenant_id !== $request->user()->tenant_id, 403);

        $request->validate([
            'proof' => ['required', 'file', 'mimes:jpg,jpeg,png,pdf', 'max:4096'],
        ]);

        if ($invoice->isPaid()) {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        // Disk privat, bukan `public`: bukti transfer memuat nama dan nomor
        // rekening. Pemilik SaaS membukanya lewat rute yang digerbang izinnya
        // sendiri, bukan lewat URL yang bisa ditebak siapa saja.
        $path = $request->file('proof')->store('proofs', 'local');

        $invoice->update([
            'proof_path' => $path,
            'status' => Invoice::STATUS_AWAITING_VERIFICATION,
            'submitted_at' => now(),
            'rejection_reason' => null,
        ]);

        $applied = $this->subscriptions->applyProvisionalUpgrade($invoice);

        return back()->with('success', $applied
            ? 'Bukti diterima dan pengguna tambahan langsung aktif. Kami periksa buktinya menyusul.'
            : 'Bukti diterima. Kami akan memeriksanya, dan perubahannya berlaku setelah itu.');
    }
}
