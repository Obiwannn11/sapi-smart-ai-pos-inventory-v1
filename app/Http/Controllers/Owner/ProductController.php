<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Models\Category;
use App\Models\ModifierGroup;
use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function __construct(
        private ImageService $imageService
    ) {}

    public function index(): Response
    {
        $products = Product::with(['category:id,name', 'variants:id,product_id,name,price,stock'])
            ->latest()
            ->get()
            ->map(function ($product) {
                $product->image_url = $this->imageService->url($product->image);
                return $product;
            });

        return Inertia::render('Owner/Products/Index', [
            'products' => $products,
            'categories' => Category::select('id', 'name')->get(),
        ]);
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
        if (!empty($data['modifier_group_ids'])) {
            $product->modifierGroups()->sync($data['modifier_group_ids']);
        }

        return redirect()->route('owner.products.index')
            ->with('success', 'Produk berhasil ditambahkan.');
    }

    public function edit(Product $product): Response
    {
        $product->load(['variants', 'modifierGroups:id']);
        $product->image_url = $this->imageService->url($product->image);

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
