<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\ImageService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private ImageService $imageService
    ) {}

    /**
     * Katalog produk, dengan keadaan awal penyaringnya diambil dari URL.
     *
     * `?q=` dibaca di sini dan diteruskan sebagai NILAI AWAL kotak pencarian,
     * bukan sebagai penyaring query ([BL-100] tahap 1). Katalognya memang
     * dikirim utuh dan disaring di client — sama seperti kategori dan status —
     * jadi menyaring di server berarti satu perjalanan bolak-balik untuk tiap
     * huruf yang diketik, demi hasil yang sudah ada di layar.
     *
     * Yang dijawab parameter ini adalah pertanyaan lain: sebuah tautan dari
     * luar halaman ini harus bisa mendarat pada barang yang dimaksudnya, bukan
     * pada katalog penuh yang sama.
     *
     * `?variant=` menjawab pertanyaan yang sama dengan lebih tepat, dan lahir
     * bersama pemakainya ([BL-100] tahap 2 & 3): hasil analisis AI kini
     * menautkan nama varian ke barangnya. Ia dipisahkan dari `?q=` karena
     * artinya berbeda — `?q=` adalah kata kunci yang boleh cocok dengan
     * banyak hal, `?variant=` menunjuk satu barang. Id, bukan nama, supaya
     * tautannya tidak putus saat variannya diganti nama.
     */
    public function index(Request $request): Response
    {
        $variantId = (int) $request->query('variant', 0) ?: null;

        return Inertia::render('Owner/Products/Index', [
            // Ditunda ([BL-037]): katalog lengkap beserta varian dan stoknya
            // adalah bagian terberat halaman ini, sementara tombol "Tambah
            // Produk" beserta ketiga penyaringnya sudah bisa dipakai tanpanya.
            //
            // URL gambar tidak ditempel di sini: Product meng-append image_url
            // dan image_thumb_url sendiri, jadi setiap permukaan mendapatkannya.
            'products' => Inertia::defer(fn () => Product::with(['category:id,name', 'variants:id,product_id,name,price,stock'])
                ->latest()
                ->get()),
            'categories' => Category::select('id', 'name')->get(),
            'filters' => [
                'q' => trim((string) $request->query('q', '')),
                // Id mentahnya ikut dikirim, terpisah dari hasil pencariannya
                // di bawah. Bedanya yang membuat layar bisa jujur: `variant`
                // terisi sementara `focus` null berarti tautannya menunjuk
                // barang yang sudah tidak ada — dan itu harus dikatakan, bukan
                // diam-diam berubah jadi katalog penuh.
                'variant' => $variantId,
            ],
            'focus' => $this->focusedVariant($variantId),
        ]);
    }

    /**
     * Barang yang ditunjuk `?variant=`, atau null bila ia sudah tidak ada.
     *
     * Mengembalikan `product_id` — bukan cuma varian itu sendiri — karena
     * katalognya berkartu per PRODUK. Yang bisa disaring layar adalah
     * produknya; variannya dipakai untuk menyebut nama yang dicari owner
     * kembali kepadanya, supaya ia tahu tautannya mendarat di tempat yang benar.
     *
     * @return array{variant_id: int, product_id: int, label: string}|null
     */
    private function focusedVariant(?int $variantId): ?array
    {
        if ($variantId === null) {
            return null;
        }

        $tenantId = auth()->user()->tenant_id;

        $variant = ProductVariant::query()
            ->whereHas('product', fn (Builder $query) => $query->where('tenant_id', $tenantId))
            ->with('product:id,name')
            ->find($variantId, ['id', 'product_id', 'name']);

        if ($variant === null) {
            return null;
        }

        return [
            'variant_id' => $variant->id,
            'product_id' => $variant->product_id,
            'label' => $variant->product->name.' - '.$variant->name,
        ];
    }

    public function create(): Response
    {
        return Inertia::render('Owner/Products/Form', [
            'categories' => Category::select('id', 'name')->get(),
            'modifierGroups' => ModifierGroup::select('id', 'name')->get(),
        ]);
    }

    public function store(StoreProductRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Upload image
        $imagePath = null;
        if ($request->hasFile('image')) {
            $imagePath = $this->imageService->upload($request->file('image'));
        }

        // Create product
        $product = Product::create([
            'name' => $data['name'],
            'category_id' => $data['category_id'] ?? null,
            'image' => $imagePath,
            'is_active' => $data['is_active'] ?? true,
        ]);

        // Create variants
        foreach ($data['variants'] as $variant) {
            $product->variants()->create($variant);
        }

        // Attach modifier groups
        if (! empty($data['modifier_group_ids'])) {
            $product->modifierGroups()->sync($data['modifier_group_ids']);
        }

        return redirect()->route('owner.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['variants', 'modifierGroups:id']);

        return Inertia::render('Owner/Products/Form', [
            'product' => $product,
            'categories' => Category::select('id', 'name')->get(),
            'modifierGroups' => ModifierGroup::select('id', 'name')->get(),
        ]);
    }

    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $data = $request->validated();

        // Upload image (replace old)
        if ($request->hasFile('image')) {
            if ($product->image) {
                $this->imageService->delete($product->image);
            }
            $data['image'] = $this->imageService->upload($request->file('image'));
        }

        $product->update(Arr::only($data, ['name', 'category_id', 'image', 'is_active']));

        // Sync modifier groups
        if (isset($data['modifier_group_ids'])) {
            $product->modifierGroups()->sync($data['modifier_group_ids']);
        }

        return redirect()->route('owner.products.index')
            ->with('success', 'Produk berhasil diperbarui.');
    }

    public function destroy(Product $product): RedirectResponse
    {
        // Hapus image dari storage
        if ($product->image) {
            $this->imageService->delete($product->image);
        }

        // Soft delete product + cascade soft delete variants
        $product->variants()->delete(); // soft delete all variants
        $product->delete();             // soft delete product

        return redirect()->route('owner.products.index')
            ->with('success', 'Produk berhasil dihapus.');
    }
}
