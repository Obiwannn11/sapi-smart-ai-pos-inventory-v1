<?php

namespace App\Http\Controllers;

use App\Models\CashDrawerMovement;
use App\Models\Product;
use App\Models\TransactionPayment;
use App\Services\CashMovementProofService;
use App\Services\ImageService;
use App\Services\PaymentProofService;
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
        private ImageService $imageService,
        private PaymentProofService $paymentProofs,
        private CashMovementProofService $movementProofs,
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

    /**
     * Sajikan foto bukti bayar non-tunai sebuah pembayaran ([BL-075]).
     *
     * TransactionPayment tidak punya `tenant_id` sendiri — ia menggantung pada
     * transaksinya. Pemeriksaannya karena itu lewat transaksi, dan relasinya
     * dimuat eksplisit supaya tidak ada jalur di mana pemeriksaan itu terlewat
     * karena relasinya kebetulan belum dimuat.
     *
     * 404 untuk milik tenant lain, dengan alasan yang sama seperti gambar
     * produk: jawaban yang membedakan "bukan milikmu" dari "tidak ada" mengubah
     * URL ini jadi alat menghitung penjualan toko sebelah.
     */
    public function paymentProof(Request $request, TransactionPayment $payment, string $size): StreamedResponse
    {
        $payment->loadMissing('transaction:id,tenant_id');

        abort_unless($payment->transaction?->tenant_id === $request->user()->tenant_id, 404);

        $path = $this->paymentProofs->pathFor($payment, $size);

        abort_if($path === null || ! $this->paymentProofs->files()->disk()->exists($path), 404);

        return $this->paymentProofs->files()->disk()->response($path, null, [
            'Content-Type' => 'image/webp',
            // Sama seperti gambar produk: isi tiap URL tak pernah berubah, dan
            // 'private' menjaga proxy bersama tidak menyimpan bukti bayar satu
            // toko untuk dilayani ke toko lain.
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }

    /**
     * Sajikan foto struk sebuah mutasi kas ([BL-093]).
     *
     * Pemeriksaan tenantnya menjawab 404, bukan 403 — sama seperti dua rute
     * media di atas. Jawaban yang membedakan "bukan milikmu" dari "tidak ada"
     * mengubah URL ini jadi alat untuk menghitung mutasi kas toko sebelah.
     *
     * TIDAK dibatasi ke pemilik: kasir yang mencatatnya perlu bisa melihat
     * kembali foto yang baru ia lampirkan, dan barisnya sudah muncul di
     * layarnya sendiri. Yang dijaga batas tenant, bukan batas peran.
     */
    public function cashMovementProof(Request $request, CashDrawerMovement $movement, string $size): StreamedResponse
    {
        abort_unless($movement->tenant_id === $request->user()->tenant_id, 404);

        $path = $this->movementProofs->pathFor($movement, $size);

        abort_if($path === null || ! $this->movementProofs->files()->disk()->exists($path), 404);

        return $this->movementProofs->files()->disk()->response($path, null, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'private, max-age=31536000, immutable',
        ]);
    }
}
