<?php

namespace App\Http\Controllers\Owner\Settings;

use App\Http\Controllers\Controller;
use App\Services\TenantLogoService;
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
    public function __construct(private TenantLogoService $logos) {}

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
                // URL, bukan path. Path berkasnya tinggal di disk privat dan
                // tidak pernah menyeberang ke peramban — lihat TenantLogoService.
                'logo_url' => $this->logos->urlFor($tenant),
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
            // 5 MB sama dengan batas gambar produk. Berkas yang sampai ke sini
            // sudah dikecilkan di perangkat oleh ImageUpload.vue, jadi angka ini
            // menjaring yang melewati layar — bukan yang lewat jalur biasa.
            'logo' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:5120'],
            // Menghapus logo HARUS punya sinyalnya sendiri. Tanpa ini, "tidak
            // mengirim berkas" berarti dua hal sekaligus — "jangan sentuh" dan
            // "buang" — dan halaman ini menyimpan alamat serta nomor telepon
            // pada request yang sama.
            'remove_logo' => ['sometimes', 'boolean'],
        ]);

        $tenant = auth()->user()->tenant;

        // `logo` tidak pernah lolos apa adanya ke ->update(). Field kosong
        // sampai di sini sebagai null, dan null yang diteruskan akan MENGHAPUS
        // logo pada request yang sebenarnya hanya membetulkan nomor telepon.
        // Nilainya hanya boleh datang dari berkas yang benar-benar tersimpan.
        unset($validated['logo'], $validated['remove_logo']);

        if ($request->hasFile('logo')) {
            $validated['logo'] = $this->logos->upload($request->file('logo'), $tenant);
        }

        // Sengaja TIDAK menerapkan ulang preset kapabilitas jenis usaha
        // (`config/business-presets.php`, `[BL-034]`). Preset itu nilai awal
        // yang berlaku sekali saat pendaftaran; di sini ia akan jadi kejutan.
        // Pemilik yang sudah mematikan antrian dapur lalu membetulkan jenis
        // usahanya dari "lainnya" ke "kuliner" sedang memperbaiki keterangan
        // tokonya, bukan meminta modulnya dinyalakan kembali — dan menyalakannya
        // diam-diam berarti layar dapur muncul lagi tanpa ada yang menekan apa
        // pun. Kapabilitas diubah di "Cara Kerja Sistem", oleh orang yang tahu
        // ia sedang mengubahnya.
        $tenant->update($validated);

        // Penghapusan hanya berlaku bila tidak ada berkas pengganti. Unggahan
        // baru sudah membuang yang lama sendiri di dalam service; menjalankan
        // keduanya akan menghapus logo yang baru saja dipasang.
        if ($request->boolean('remove_logo') && ! $request->hasFile('logo')) {
            $this->logos->remove($tenant);
        }

        return back()->with('success', 'Profil usaha berhasil disimpan.');
    }
}
