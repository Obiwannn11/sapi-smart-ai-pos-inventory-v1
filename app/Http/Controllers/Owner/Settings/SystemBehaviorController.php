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
                // Empat saklar per-jenis saran jual ([BL-099]). Bukan
                // kapabilitas modul dan bukan aturan kerja: ia menentukan
                // JENIS saran apa yang boleh dihasilkan mesin untuk toko ini.
                // Ada di sini karena laporan Saran Jual memisahkan angkanya
                // per jenis supaya kesimpulan itu bisa ditindaklanjuti, dan
                // sampai sekarang tindakannya tidak punya tempat.
                ...$this->upsellTypeSwitches($tenant),
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
            // Pajak punya endpoint tulisnya sendiri (`TaxSettingsController`),
            // tapi dibaca di sini supaya halamannya tetap satu GET ([BL-065]).
            'tax' => [
                'tax_enabled' => $tenant->tax_enabled,
                'tax_mode' => $tenant->tax_mode,
                'tax_rate' => (float) $tenant->tax_rate,
                'tax_label' => $tenant->tax_label,
                // Bukan turunan dari `tax_enabled`: yang mengunci adalah
                // adanya penjualan berpajak, bukan sakelarnya. Tenant yang
                // menyalakan pajak lalu berubah pikiran sebelum menjual apa
                // pun harus tetap bisa membatalkannya.
                'locked' => $tenant->taxLocked(),
                // Jendela yang sedang dibukakan operator ([BL-065] butir 4).
                // Dikirim terpisah dari `locked`, bukan menggantikannya:
                // "terkunci" dan "sedang dibukakan" adalah dua fakta berbeda,
                // dan layarnya perlu keduanya untuk bisa mengatakan sampai
                // kapan kesempatan itu berlaku.
                'lock_opened_until' => $tenant->taxLockOpen()
                    ? $tenant->tax_lock_opened_until->toIso8601String()
                    : null,
            ],
            // Biaya layanan ([BL-097]), endpoint tulisnya sendiri dan
            // dibaca di sini dengan alasan yang sama. Tidak ada `locked`
            // maupun `lock_opened_until` di sini — biaya layanan memang
            // tidak pernah dikunci; lihat `ServiceChargeSettingsController`.
            'serviceCharge' => [
                'service_charge_enabled' => $tenant->service_charge_enabled,
                'service_charge_rate' => (float) $tenant->service_charge_rate,
                'service_charge_label' => $tenant->service_charge_label,
            ],
            // Jenis yang dimatikan untuk SELURUH toko lewat `config/upsell.php`.
            // Dikirim supaya layarnya bisa mengunci saklarnya alih-alih
            // menawarkan tombol yang tidak mengubah apa pun — tanpa menyebut
            // berkas yang pembacanya tidak bisa buka ([BL-099]).
            'upsellTypesLockedGlobally' => array_values(array_filter(
                array_keys(Tenant::upsellTypeColumns()),
                fn (string $type) => ! config("upsell.types.{$type}", true),
            )),
            'taxModes' => Tenant::taxModes(),
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

    /**
     * Keempat saklar jenis saran, dibaca lewat satu daftar yang sama dengan
     * yang dipakai `UpsellIndexBuilder` dan pratinjau halaman Aturan.
     *
     * @return array<string, bool>
     */
    private function upsellTypeSwitches(Tenant $tenant): array
    {
        $switches = [];

        foreach (Tenant::upsellTypeColumns() as $column) {
            $switches[$column] = (bool) $tenant->{$column};
        }

        return $switches;
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'kitchen_queue_enabled' => 'boolean',
            'self_order_enabled' => 'boolean',
            'ai_enabled' => 'boolean',
            'payment_proof_enabled' => 'boolean',
            'upsell_mandatory' => 'boolean',
            'upsell_attach_enabled' => 'boolean',
            'upsell_pressed_stock_enabled' => 'boolean',
            'upsell_upsize_enabled' => 'boolean',
            // Sengaja TIDAK ditolak walau jenisnya sedang mati secara global:
            // saklar tenant menyimpan kehendak owner, dan config yang menang
            // saat dibaca. Menolaknya berarti kehendak itu hilang begitu
            // pemilik SaaS menyalakan jenisnya kembali ([BL-099]).
            'upsell_manual_enabled' => 'boolean',
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
