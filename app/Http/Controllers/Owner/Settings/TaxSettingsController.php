<?php

namespace App\Http\Controllers\Owner\Settings;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Setelan pajak ([BL-065]) — endpoint tersendiri, tetap di halaman Cara Kerja
 * Sistem.
 *
 * Alasannya sama persis dengan yang sudah tertulis di
 * `SystemBehaviorController`, hanya lebih kuat: menyimpan tarif pajak tidak
 * boleh berada dalam satu permintaan dengan mematikan antrian dapur. Dua dari
 * empat field di bawah TERKUNCI setelah penjualan berpajak pertama, dan
 * penguncian yang menumpang endpoint bersama akan tergoda dilonggarkan supaya
 * form lain tetap bisa menyimpan.
 *
 * Halaman GET-nya tetap satu, dilayani `SystemBehaviorController::index()` —
 * "TIGA halaman, tiga endpoint" `[BL-039]` bicara soal halaman, dan ini bukan
 * halaman baru.
 */
class TaxSettingsController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $tenant = auth()->user()->tenant;
        $locked = $tenant->taxLocked();

        $validated = $request->validate([
            'tax_enabled' => 'required|boolean',
            'tax_mode' => ['required', Rule::in(array_keys(Tenant::taxModes()))],
            // Batas atas 100 hanya menjaga agar angkanya punya arti. Tarif
            // nyata jauh di bawahnya — PPN 11%, PBJT paling tinggi 10% — tapi
            // mematoknya di 11 atau 12 berarti memilihkan tarif untuk orang
            // yang membaca Perda daerahnya sendiri.
            'tax_rate' => 'required|numeric|min:0|max:100',
            'tax_label' => 'nullable|string|max:20',
        ]);

        // Label WAJIB begitu pajak menyala. Ia memilih kata yang tercetak di
        // struk pelanggan, dan PPN (negara) serta PB1/PBJT (daerah) menyebut
        // dasar hukum yang berbeda. Menerima null di sini berarti mencetak
        // struk yang tidak bisa menyebut apa yang dipungutnya.
        if ($validated['tax_enabled'] && blank($validated['tax_label'])) {
            throw ValidationException::withMessages([
                'tax_label' => 'Pilih dulu jenis pajaknya — kata ini yang tercetak di struk pelanggan.',
            ]);
        }

        if ($locked) {
            $this->assertLockedFieldsUnchanged($tenant, $validated);
        }

        $tenant->update($validated);

        return back()->with('success', 'Setelan pajak berhasil disimpan.');
    }

    /**
     * Setelah penjualan berpajak pertama, sakelar dan mode tidak lagi boleh
     * berubah lewat jalur pemilik ([BL-065] butir 3 & 4).
     *
     * Keduanya mengubah ARTI angka uang yang sudah tercatat: mematikan pajak
     * di tengah jalan melubangi riwayat pungutan, dan menukar mode membuat
     * omzet sebelum dan sesudahnya tidak sebanding. Tarif dan label sengaja
     * TIDAK ikut terkunci — tarif memang berubah di dunia nyata, dan
     * perubahannya berlaku maju tanpa membuat angka lama tidak sebanding.
     *
     * Yang menolak di sini bukan kata akhir: pembukaannya ada pada operator
     * platform, teraudit — bukan pada pemilik yang sedang terburu-buru.
     *
     * @param  array<string, mixed>  $validated
     */
    private function assertLockedFieldsUnchanged(Tenant $tenant, array $validated): void
    {
        $errors = [];

        if ((bool) $validated['tax_enabled'] !== (bool) $tenant->tax_enabled) {
            $errors['tax_enabled'] = 'Pajak sudah dipungut pada penjualan yang tercatat, jadi sakelarnya tidak bisa diubah sendiri. Hubungi operator untuk membukanya.';
        }

        if ($validated['tax_mode'] !== $tenant->tax_mode) {
            $errors['tax_mode'] = 'Mode pajak terkunci sejak penjualan berpajak pertama — mengubahnya membuat omzet sebelum dan sesudahnya tidak sebanding.';
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
