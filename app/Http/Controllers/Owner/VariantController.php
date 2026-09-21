<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVariantRequest;
use App\Http\Requests\UpdateVariantRequest;
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

    /**
     * Ubah nama, SKU, dan harga varian.
     *
     * Stok dan tanggal kedaluwarsa TIDAK diterima di sini ([BL-111]). Keduanya
     * hidup di batch, dan menulisnya dari formulir ini melewati catatan mutasi
     * stok sekaligus mengganti tanggal batch tanpa jejak. Tempatnya halaman
     * Stok: restock dan adjustment. Saat varian dibuat, stok awalnya tetap
     * diterima dan jadi batch pembuka.
     */
    public function update(UpdateVariantRequest $request, Product $product, ProductVariant $variant): RedirectResponse
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
