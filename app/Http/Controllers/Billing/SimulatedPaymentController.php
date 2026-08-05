<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PlatformAuditLog;
use App\Services\Billing\InvoiceSettlement;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pelunasan peragaan — tagihan lunas tanpa bukti transfer apa pun.
 *
 * Ada karena permintaan pemilik untuk memperagakan alur "bayar lalu akses pulih"
 * kepada calon klien (`[BL-045]` butir 2). Ia BUKAN pengganti pembayaran, dan
 * bukan payment gateway sementara: yang dibutuhkan produksi adalah gateway
 * sungguhan, dan kerangkanya sudah disiapkan di `InvoiceSettlement`.
 *
 * Dua gerbang, keduanya wajib, dan keduanya dinilai di `canSimulate()`:
 * tenantnya bertanda peragaan DAN lingkungannya bukan produksi. Penanda saja
 * tidak cukup — ia ikut terbawa bila basis data peragaan pernah disalin, dan
 * satu salah setel akan membuka jalur yang melunasi tagihan tanpa bukti. Syarat
 * lingkungan membuat jalur ini tidak pernah ADA di produksi.
 *
 * Tidak ada logika pelunasan di sini sama sekali: ia memanggil
 * `InvoiceSettlement::settle()`, pintu yang sama dengan pemeriksaan manual
 * pemilik SaaS. Satu jalan masuk ke keadaan `active` lebih mudah
 * dipertanggungjawabkan daripada dua.
 */
class SimulatedPaymentController extends Controller
{
    public function __construct(private readonly InvoiceSettlement $settlement) {}

    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        abort_if($invoice->tenant_id !== $tenant->id, 403);

        // 404, bukan 403: di luar tenant peragaan rute ini sebaiknya tidak
        // terlihat pernah ada. Menjawab "terlarang" memberi tahu penanyanya
        // bahwa ada sesuatu di sini yang bisa dibuka dalam keadaan lain.
        abort_unless($this->settlement->canSimulate($tenant), 404);

        if ($invoice->isPaid()) {
            return back()->with('error', 'Tagihan ini sudah lunas.');
        }

        $this->settlement->settle($invoice, InvoiceSettlement::SOURCE_SIMULATION);

        // Dicatat sebagai kejadian sensitif meski ini cuma peragaan: sebuah
        // tagihan berpindah ke lunas tanpa seorang pun memeriksa bukti, dan
        // itu justru yang paling perlu meninggalkan jejak. `platform_user_id`
        // akan null — pelakunya memang bukan akun platform — dan `meta` yang
        // menyebut tenant serta penggunanya menutup celah itu.
        PlatformAuditLog::record('invoices.simulate', $invoice, [
            'tenant_id' => $invoice->tenant_id,
            'period' => $invoice->period,
            'amount' => (float) $invoice->amount,
            'by_user_id' => $request->user()->id,
            'environment' => app()->environment(),
        ]);

        return back()->with('success', 'Pembayaran peragaan diterima. Akses langganan dipulihkan.');
    }
}
