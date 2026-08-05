<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Gerbang tunggal untuk berkas milik tenant.
 *
 * Gambar produk dulu disajikan lewat symlink public/storage, yang artinya
 * setiap berkas terbuka untuk siapa saja yang tahu path-nya — termasuk toko
 * lain. Sekarang berkasnya privat dan hanya keluar lewat sini, setelah dua
 * pemeriksaan: pengguna sudah masuk (middleware 'auth'), dan produknya benar
 * milik tenant pengguna itu.
 */
class MediaController extends Controller
{
    public function __construct(
        private ImageService $imageService
    ) {}

    /**
     * Sajikan satu rendition gambar produk.
     *
     * Selalu 404 — bukan 403 — untuk produk milik tenant lain: jawaban yang
     * membedakan "bukan milikmu" dari "tidak ada" mengubah URL ini menjadi alat
     * untuk menghitung produk toko sebelah.
     */
    public function productImage(Request $request, Product $product, string $size): StreamedResponse
    {
        abort_unless($product->tenant_id === $request->user()->tenant_id, 404);
        abort_unless((bool) $product->image, 404);

        $path = $this->imageService->pathFor($product->image, $size);

        abort_if($path === null || ! $this->imageService->disk()->exists($path), 404);

        return $this->imageService->disk()->response($path, null, [
            'Content-Type' => 'image/webp',
            // Isi tiap URL tak pernah berubah — mengganti gambar menghasilkan
            // nama berkas baru, jadi query `v`-nya ikut berganti. 'private'
            // menjaga proxy bersama tidak menyimpannya untuk pengguna lain.
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
