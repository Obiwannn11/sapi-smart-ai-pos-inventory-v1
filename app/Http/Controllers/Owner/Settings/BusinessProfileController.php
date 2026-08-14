<?php

namespace App\Http\Controllers\Owner\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Profil & Merek — keterangan usaha yang tampil di struk, plus jenis usaha.
 *
 * Pecahan pertama dari halaman "Profil Usaha" lama (`[BL-039]`). Yang
 * membedakannya dari dua pecahan lain bukan tata letak melainkan RISIKO:
 * halaman ini boleh sering diubah dan salah ketik di sini tidak menggantung
 * pekerjaan siapa pun — kecuali satu kolom, `business_type`, yang ikut
 * menentukan tarif periode berikutnya dan karena itu diberi kalimat
 * peringatannya sendiri di layar.
 */
class BusinessProfileController extends Controller
{
    public function index(): Response
    {
        $tenant = auth()->user()->tenant;

        return Inertia::render('Owner/Settings/Index', [
            'tenant' => [
                'name' => $tenant->name,
                'address' => $tenant->address,
                'phone' => $tenant->phone,
                // Jenis usaha diatur DI SINI, oleh pemiliknya sendiri.
                //
                // Dulu kolom ini sengaja tidak ada di halaman ini dan hanya bisa
                // diubah pemilik SaaS, dengan alasan ia dasar penetapan harga.
                // Alasannya benar soal akibatnya, keliru soal siapa yang berhak:
                // yang tahu jenis usahanya adalah pemilik toko, dan mengubah
                // keterangan usaha orang tanpa sepengetahuannya bukan kewenangan
                // penyedia layanan sekalipun angkanya ikut bergeser.
                //
                // Yang menjaga tagihan tetap bisa dijelaskan bukan larangan
                // mengedit, melainkan dua hal yang sudah ada: `effective_from`
                // pada aturan harga, dan `invoices.pricing_context` yang
                // membekukan keadaan tenant saat tagihan terbit. Tagihan yang
                // sudah keluar tidak berubah oleh suntingan hari ini.
                'business_type' => $tenant->business_type,
            ],
            'businessTypes' => config('pricing-dimensions.business_type.options', []),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'address' => 'nullable|string|max:500',
            'phone' => 'nullable|string|max:50',
            // `sometimes` + `required`, bukan `nullable`: sejak kolom ini punya
            // bawaan, tidak ada lagi keadaan "belum dijawab" yang sah, jadi
            // nilai KOSONG ditolak. Tapi field yang tidak dikirim sama sekali
            // berarti "jangan sentuh" — bukan "kosongkan".
            'business_type' => ['sometimes', 'required', Rule::in(array_keys(config('pricing-dimensions.business_type.options', [])))],
        ]);

        auth()->user()->tenant->update($validated);

        return back()->with('success', 'Profil usaha berhasil disimpan.');
    }
}
