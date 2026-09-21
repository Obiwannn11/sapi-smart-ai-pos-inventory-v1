<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Owner/Categories/Index', [
            // Ditunda ([BL-037]): daftarnya menyusul sementara tombol tambah dan
            // formulirnya sudah bisa dipakai sejak cat pertama.
            'categories' => Inertia::defer(fn () => Category::withCount('products')->latest()->get()),
        ]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());

        return back()->with('success', 'Kategori berhasil ditambahkan.');
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $category->update($request->validated());

        return back()->with('success', 'Kategori berhasil diperbarui.');
    }

    public function destroy(Category $category): RedirectResponse
    {
        // Soft delete doesn't trigger DB-level nullOnDelete, so nullify manually
        $category->products()->update(['category_id' => null]);
        $category->delete(); // soft delete

        return back()->with('success', 'Kategori berhasil dihapus.');
    }
}
