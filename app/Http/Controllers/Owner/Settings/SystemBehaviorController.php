<?php

namespace App\Http\Controllers\Owner\Settings;

use App\Http\Controllers\Controller;
use App\Models\AiAnalysis;
use App\Models\Tenant;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Cara Kerja Sistem — kapabilitas modul, aturan kerja kasir, dan identitas
 * pesanan.
 *
 * Pecahan kedua dari halaman "Profil Usaha" lama (`[BL-039]`). Risikonya
 * berbeda jenis dari Profil & Merek: mematikan sesuatu di sini bisa
 * menggantung pekerjaan yang sedang berjalan (itu sebabnya `featureWarnings`
 * ada) dan `upsell_mandatory` bahkan bisa menahan tombol bayar. Karena itu ia
 * punya endpoint sendiri — supaya menyunting alamat toko tidak pernah berada
 * satu permintaan dengan mematikan antrian dapur.
 */
class SystemBehaviorController extends Controller
{
    public function index(): Response
    {
        $tenant = auth()->user()->tenant;

        return Inertia::render('Owner/Settings/Operations', [
            'features' => [
                'kitchen_queue_enabled' => $tenant->kitchen_queue_enabled,
                'self_order_enabled' => $tenant->self_order_enabled,
                'ai_enabled' => $tenant->ai_enabled,
                // Kapabilitas modul juga, dan bawaannya MATI ([BL-075]).
                // Menyalakannya menambah satu langkah ke setiap penjualan
                // non-tunai; toko yang tidak memintanya tidak seharusnya
                // menemukannya sudah menyala.
                'payment_proof_enabled' => $tenant->payment_proof_enabled,
                // Bukan kapabilitas modul seperti empat di atas, melainkan
                // ATURAN KERJA: menyalakannya menahan tombol bayar sampai tiap
                // saran dijawab. Dikelompokkan di sini karena tempatnya sama di
                // mata owner, tapi sengaja TIDAK masuk Tenant::hasFeature() —
                // ia tidak menggerbangi rute atau modul apa pun ([BL-025]).
                'upsell_mandatory' => $tenant->upsell_mandatory,
                // Bukan boolean seperti yang lain, dan bukan kapabilitas modul:
                // ini cara outlet mengenali pesanannya. Ikut di sini karena
                // tempatnya sama di mata owner ([BL-026]).
                'order_identity_mode' => $tenant->order_identity_mode,
                // Bukan kapabilitas dan bukan aturan kerja, melainkan ANGKA
                // KEBIJAKAN ([BL-018]): lantai harga tiap varian dihitung
                // `cost_price × (1 + margin/100)`, dan rumus diskon tidak
                // pernah boleh turun di bawahnya.
                'min_margin_percent' => (float) $tenant->min_margin_percent,
                // Angka kebijakan kedua ([BL-087]): batas di mana uang yang KELUAR
                // dari laci masih boleh dicatat kasir tanpa menunggu pemilik.
                // Nilainya menentukan bentuk fiturnya, bukan cuma besarannya —
                // 0 berarti setiap pengeluaran menunggu persetujuan.
                'cash_payout_approval_threshold' => (float) $tenant->cash_payout_approval_threshold,
            ],
            'orderIdentityModes' => Tenant::orderIdentityModes(),
            // Dipakai memperingatkan owner sebelum ia mematikan fitur yang
            // masih ada pekerjaan berjalan di baliknya.
            'featureWarnings' => [
                'active_self_orders' => Transaction::where('source', Transaction::SOURCE_SELF_ORDER)
                    ->whereNotNull('fulfillment_status')
                    ->where('fulfillment_status', '!=', Transaction::FULFILLMENT_DONE)
                    ->count(),
                'pending_analyses' => AiAnalysis::where('status', AiAnalysis::STATUS_PENDING)->count(),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kitchen_queue_enabled' => 'boolean',
            'self_order_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'payment_proof_enabled' => 'boolean',
            'upsell_mandatory' => 'boolean',
            // Batas atas 90%: lantai yang menuntut margin lebih tinggi dari
            // itu membuat fitur diskonnya tidak pernah bisa menawarkan apa pun,
            // dan owner akan menyimpulkan ia rusak.
            'min_margin_percent' => ['sometimes', 'numeric', 'min:0', 'max:90'],
            // Tanpa batas atas yang masuk akal: ambang setinggi apa pun sah,
            // itu hanya berarti pemilik memercayai kasirnya sepenuhnya. Yang
            // dijaga cuma agar ia bukan angka negatif, yang tak punya arti.
            'cash_payout_approval_threshold' => ['sometimes', 'numeric', 'min:0', 'max:99999999'],
            // `sometimes` dengan alasan yang sama seperti `business_type` di
            // halaman Profil: field yang tidak dikirim berarti "jangan sentuh",
            // bukan "kembalikan ke none".
            'order_identity_mode' => ['sometimes', 'required', Rule::in(array_keys(Tenant::orderIdentityModes()))],
        ]);

        auth()->user()->tenant->update($validated);

        return back()->with('success', 'Cara kerja sistem berhasil disimpan.');
    }
}
