<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVariantRequest;
use App\Models\Product;
use App\Models\ProductVariant;
use Illuminate\Http\RedirectResponse;

class VariantController extends Controller
{
    public function store(StoreVariantRequest $request, Product $product): RedirectResponse
    {
        $product->variants()->create($request->validated());

        return back()->with('success', 'Varian berhasil ditambahkan.');
    }

    public function update(StoreVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
    {
        if ($variant->product_id !== $product->id) {
            abort(404);
        }

        $variant->update($request->validated());

        return back()->with('success', 'Varian berhasil diperbarui.');
    }

    public function destroy(Product $product, ProductVariant $variant): RedirectResponse
    {
        if ($variant->product_id !== $product->id) {
            abort(404);
        }

        $variant->delete(); // soft delete

        return back()->with('success', 'Varian berhasil dihapus.');
    }
}
