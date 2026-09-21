<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreDiscountRuleRequest;
use App\Models\DiscountRule;
use App\Models\Product;
use App\Services\DiscountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Aturan diskon buatan owner ([BL-018]).
 *
 * **Halaman ini ADALAH langkah persetujuannya.** Backlognya meminta fitur ini
 * dimulai dari "sarankan lalu owner menyetujui, bukan otomatis", karena harga
 * yang turun sendiri secara keliru adalah uang yang keluar dan sukar ditarik
 * kembali. Sistem tidak pernah menurunkan harga atas inisiatifnya sendiri; ia
 * memberlakukan baris yang owner tuliskan di sini.
 *
 * **Owner-eksklusif**, sama alasannya dengan `[BL-074]`: ini keputusan harga,
 * dan menggantungkannya pada permission `reports` akan memberi kuasa menulis
 * kepada siapa pun yang hanya diberi hak membaca laporan.
 */
class DiscountRuleController extends Controller
{
    public function __construct(
        private readonly DiscountService $discounts,
    ) {}

    public function index(Request $request): Response
    {
        $tenant = $request->user()->tenant;

        return Inertia::render('Owner/DiscountRules/Index', [
            'minMarginPercent' => (float) $tenant->min_margin_percent,

            // Ditunda ([BL-037]).
            'rules' => Inertia::defer(fn () => DiscountRule::with([
                'variant:id,product_id,name,price,cost_price,stock,expiry_date',
                'variant.product:id,name',
            ])
                ->orderByDesc('id')
                ->get()
                ->map(fn (DiscountRule $rule) => [
                    ...$rule->toArray(),
                    // Harga hasil aturannya dihitung DI SERVER dan dikirim jadi,
                    // bukan diturunkan ulang di layar. Rumusnya — pendalaman
                    // seiring tanggal, jepitan lantai, pembulatan ke atas —
                    // punya satu tempat, dan layar yang menghitung sendiri
                    // adalah tempat kedua yang perlahan menyimpang.
                    'effective' => $rule->variant
                        ? $this->discounts->priceFor($rule->variant, $tenant, $rule)
                        : null,
                ])),

            'variants' => Inertia::defer(fn () => Product::where('is_active', true)
                ->with('variants:id,product_id,name,price,cost_price,stock')
                ->orderBy('name')
                ->get()
                ->flatMap(fn (Product $product) => $product->variants->map(fn ($variant) => [
                    'id' => $variant->id,
                    'label' => $product->name.' - '.$variant->name,
                    'price' => (float) $variant->price,
                    // Lantainya ikut supaya owner melihat batasnya SAAT
                    // memilih barangnya, bukan setelah aturannya ditolak.
                    'floor' => $this->discounts->floorFor($variant, $tenant),
                ]))
                ->values()),
        ]);
    }

    public function store(StoreDiscountRuleRequest $request): RedirectResponse
    {
        DiscountRule::create($request->validated());

        return back()->with('success', 'Aturan diskon ditambahkan.');
    }

    public function update(StoreDiscountRuleRequest $request, DiscountRule $discountRule): RedirectResponse
    {
        $discountRule->update($request->validated());

        return back()->with('success', 'Aturan diskon diperbarui.');
    }

    /**
     * Nyalakan/matikan satu aturan.
     *
     * Terpisah dari `update()` dengan alasan yang sama seperti di
     * `UpsellRuleController`: mematikan diskon adalah tindakan satu klik, dan
     * di sini taruhannya lebih tinggi — memaksanya melewati validasi formulir
     * penuh berarti aturan yang produknya sudah berubah tidak bisa dihentikan
     * saat owner paling ingin menghentikannya.
     */
    public function toggle(DiscountRule $discountRule): RedirectResponse
    {
        $discountRule->update(['is_active' => ! $discountRule->is_active]);

        return back()->with('success', $discountRule->is_active
            ? 'Diskon dinyalakan.'
            : 'Diskon dihentikan — kasir kembali memakai harga katalog.');
    }

    public function destroy(DiscountRule $discountRule): RedirectResponse
    {
        // Penjualan yang memakainya TIDAK ikut terhapus: kolomnya nullOnDelete,
        // dan alasannya sudah disalin ke tiap barisnya saat penjualan terjadi.
        $discountRule->delete();

        return back()->with('success', 'Aturan diskon dihapus.');
    }
}
