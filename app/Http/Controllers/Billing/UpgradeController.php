<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pembelian dan pelepasan seat oleh tenant, dan pengunggahan bukti bayar.
 *
 * **Alur seat berubah total 2026-08-07** (`[BL-053]`). Sebelumnya: tenant
 * meminta tambahan → tagihan sekali bayar terbit → bukti diunggah → seat
 * berlaku. Sejak seat jadi komponen bulanan, tagihan sekali bayar itu menagih
 * dua kali untuk hak yang sama — sekali di muka, lalu tiap bulan sesudahnya.
 *
 * Yang berjalan sekarang: seat naik seketika, gratis sampai periode berjalan
 * habis, dan mulai muncul di tagihan bulanan berikutnya. Tidak ada tagihan di
 * tengah bulan, tidak ada bukti transfer, tidak ada antrean pemeriksaan. Karena
 * hak itu tidak lagi berhenti sendiri, ada pasangannya: pelepasan seat, yang
 * berlaku satu periode penuh ke depan.
 *
 * `storeProof()` tetap di sini dan tetap dipakai — ia melayani tagihan
 * LANGGANAN, bukan seat.
 */
class UpgradeController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Beli seat tambahan. Berlaku sekarang, tertagih mulai periode berikutnya.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'additional_seats' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $tenant = $request->user()->tenant;

        // Peninggalan, dan tetap perlu. Tak ada lagi tagihan `KIND_UPGRADE` yang
        // lahir, tapi yang terbit sebelum 2026-08-07 masih bisa menggantung —
        // dan melunasinya menulis `seats = grants_seats`, angka dari dunia lama
        // yang akan MENURUNKAN jatah tenant yang baru saja membeli di sini.
        if ($this->subscriptions->openUpgradeInvoice($tenant) !== null) {
            return back()->with('error', 'Masih ada tagihan penambahan pengguna lama yang belum selesai. Selesaikan dulu yang itu.');
        }

        $subscription = $this->subscriptions->grantSeats($tenant, $validated['additional_seats']);

        return back()->with('success', sprintf(
            'Jatah pengguna Anda kini %d dan langsung bisa dipakai. Tambahannya gratis sampai periode ini habis, '
            .'lalu masuk tagihan bulanan sebesar %s per pengguna.',
            $subscription->seats,
            'Rp '.number_format((float) $subscription->plan->extra_seat_price, 0, ',', '.'),
        ));
    }

    /**
     * Lepas seat tambahan. Berlaku di akhir periode berikutnya.
     *
     * Dua penjaga, dan keduanya menolak dengan kalimat alih-alih diam-diam
     * memotong angkanya: melepas lebih banyak daripada yang dibeli, dan melepas
     * seat yang masih diduduki staf aktif. Yang kedua adalah keputusan pemilik
     * 2026-08-07 — mematikan akun kasir di tengah jam kerja sebagai efek samping
     * penghematan tagihan adalah kerugian yang jauh lebih besar.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'released_seats' => ['required', 'integer', 'min:1', 'max:20'],
        ]);

        $tenant = $request->user()->tenant;
        $subscription = $this->subscriptions->ensureFor($tenant);
        $ceiling = $this->subscriptions->seatReleaseCeiling($subscription);

        if ($ceiling < 1) {
            return back()->with('error', $subscription->entitledExtraSeats() < 1
                ? 'Anda tidak punya pengguna tambahan untuk dilepas — semua kursi Anda berasal dari paket.'
                : 'Semua kursi Anda sedang dipakai staf aktif. Nonaktifkan salah satu staf dulu, baru kursinya bisa dilepas.');
        }

        if ($validated['released_seats'] > $ceiling) {
            return back()->with('error', sprintf(
                'Paling banyak %d pengguna tambahan yang bisa dilepas sekarang — sisanya masih dipakai staf aktif.',
                $ceiling,
            ));
        }

        $subscription = $this->subscriptions->releaseSeats($tenant, $validated['released_seats']);

        return back()->with('success', sprintf(
            'Pelepasan %d pengguna tercatat dan berlaku %s. Sampai tanggal itu kursinya masih bisa dipakai, '
            .'dan tagihan sesudahnya sudah tidak memuatnya.',
            $validated['released_seats'],
            $subscription->seat_release_at->translatedFormat('j F Y'),
        ));
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
