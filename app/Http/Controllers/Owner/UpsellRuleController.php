<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUpsellRuleRequest;
use App\Models\Product;
use App\Models\UpsellRule;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aturan saran jual yang ditulis owner ([BL-074]).
 *
 * **Halamannya berdiri sendiri, bukan ditambahkan ke Pengaturan.** Editornya
 * butuh tabel, pencarian produk, dan jendela tanggal; menjejalkannya ke formulir
 * Pengaturan akan mengulang persis keluhan `[BL-039]` yang baru saja dibereskan
 * dengan memecah "Profil Usaha" jadi tiga halaman.
 *
 * **Owner-eksklusif, bukan modul RBAC baru.** Memilih barang mana yang didorong
 * adalah keputusan pemilik usaha, bukan tugas yang dilimpahkan — dan
 * menggantungkannya pada permission `reports` akan memberi kuasa MENULIS kepada
 * siapa pun yang hanya diberi hak MEMBACA laporan.
 */
class UpsellRuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Owner/UpsellRules/Index', [
            // Ditunda ([BL-037]): tombol tambah dan formulirnya sudah bisa
            // dipakai sejak cat pertama, daftarnya menyusul.
            'rules' => Inertia::defer(fn () => UpsellRule::with([
                'triggerVariant:id,product_id,name',
                'triggerVariant.product:id,name',
                'suggestedVariant:id,product_id,name,price,stock',
                'suggestedVariant.product:id,name',
            ])
                ->orderByDesc('priority')
                ->orderByDesc('id')
                ->get()),

            // Daftar varian untuk kedua pemilihnya. Dikirim sekali dan dipakai
            // dua kali — pemicu dan yang disarankan — supaya tidak ada endpoint
            // pencarian tersendiri untuk katalog yang seukuran ini.
            'variants' => Inertia::defer(fn () => Product::where('is_active', true)
                ->with('variants:id,product_id,name,price,stock')
                ->orderBy('name')
                ->get()
                ->flatMap(fn (Product $product) => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'label' => $product->name.' - '.$variant->name,
                    'price' => (float) $variant->price,
                    'stock' => $variant->stock,
                ]))
                ->values()),
        ]);
    }

    public function store(StoreUpsellRuleRequest $request): RedirectResponse
    {
        UpsellRule::create($request->validated());

        return back()->with('success', 'Aturan saran jual ditambahkan.');
    }

    public function update(StoreUpsellRuleRequest $request, UpsellRule $upsellRule): RedirectResponse
    {
        $upsellRule->update($request->validated());

        return back()->with('success', 'Aturan saran jual diperbarui.');
    }

    /**
     * Nyalakan/matikan satu aturan.
     *
     * Terpisah dari `update()` dengan sengaja: mematikan aturan adalah tindakan
     * satu klik dari tabel, dan memaksanya melewati validasi formulir penuh
     * berarti aturan yang produknya sudah terhapus tidak bisa dimatikan sama
     * sekali — persis saat owner paling ingin mematikannya.
     */
    public function toggle(UpsellRule $upsellRule): RedirectResponse
    {
        $upsellRule->update(['is_active' => ! $upsellRule->is_active]);

        return back()->with('success', $upsellRule->is_active
            ? 'Aturan dinyalakan.'
            : 'Aturan dimatikan — kasir tidak lagi melihatnya.');
    }

    public function destroy(UpsellRule $upsellRule): RedirectResponse
    {
        $upsellRule->delete();

        return back()->with('success', 'Aturan saran jual dihapus.');
    }
}
