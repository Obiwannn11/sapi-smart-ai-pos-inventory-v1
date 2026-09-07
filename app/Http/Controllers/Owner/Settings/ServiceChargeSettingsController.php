<?php

namespace App\Http\Controllers\Owner\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Setelan biaya layanan ([BL-097] Tahap 3) — endpoint tersendiri, tetap di
 * halaman Cara Kerja Sistem.
 *
 * Alasan endpoint terpisah sama seperti `TaxSettingsController`: menyimpan
 * tarif yang tercetak di struk pelanggan tidak boleh berada dalam satu
 * permintaan dengan mematikan antrian dapur.
 *
 * **Yang sengaja TIDAK ada di sini, dan bukan karena terlewat:** seluruh
 * mesin penguncian. Tidak ada `assertLockedFieldsUnchanged()`, tidak ada
 * `serviceChargeLocked()`, tidak ada jendela yang dibukakan operator platform.
 * Penguncian `tax_mode` dibeli oleh dua hal yang biaya layanan tidak punya —
 * kewajiban hukum memungut, dan riwayat pungutan yang tidak boleh berlubang.
 * Biaya layanan adalah pilihan komersial pemilik toko: ia boleh menyalakannya
 * bulan ini, mematikannya bulan depan, dan menaikkannya bulan berikutnya tanpa
 * melanggar apa pun. Yang menjaga kebenaran angkanya bukan kunci, melainkan
 * pembekuan per transaksi — tarif dan label ikut tersimpan di penjualannya,
 * jadi struk lama tetap mencetak angka yang sama walau setelannya sudah
 * berubah sepuluh kali.
 *
 * Itu menghapus satu tahap penuh dari lingkup pekerjaan ini, dan sengaja
 * dicatat di sini supaya pembaca berikutnya tidak menganggapnya lubang.
 */
class ServiceChargeSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $tenant = auth()->user()->tenant;

        $validated = $request->validate([
            'service_charge_enabled' => 'required|boolean',
            // Batas atas 100 hanya menjaga agar angkanya punya arti, sama
            // seperti tarif pajak. Biaya layanan yang lazim 5–10%, tapi
            // mematoknya di sana berarti memilihkan angka untuk orang yang
            // sedang menghitung ongkos pelayanannya sendiri.
            'service_charge_rate' => 'required|numeric|min:0|max:100',
            'service_charge_label' => 'nullable|string|max:30',
        ]);

        // Label WAJIB begitu biaya layanan menyala, alasan yang sama persis
        // dengan `tax_label`: kata ini tercetak di struk pelanggan, dan
        // "Biaya Layanan" tidak sama dengan "Service Charge" bagi toko yang
        // memilih salah satunya dengan sengaja.
        if ($validated['service_charge_enabled'] && blank($validated['service_charge_label'])) {
            throw ValidationException::withMessages([
                'service_charge_label' => 'Isi dulu namanya — kata ini yang tercetak di struk pelanggan.',
            ]);
        }

        $tenant->update($validated);

        return back()->with('success', 'Setelan biaya layanan berhasil disimpan.');
    }
}
