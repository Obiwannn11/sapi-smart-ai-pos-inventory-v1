<?php

namespace App\Http\Controllers\Owner;

use App\Http\Controllers\Controller;
use App\Models\ProductVariant;
use App\Models\Transaction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class OfflineReviewController extends Controller
{
    /**
     * Daftar transaksi offline yang tersimpan dengan anomali.
     *
     * Halaman ini TIDAK mempertanyakan apakah penjualannya sah — uangnya sudah
     * diterima. Tugasnya menjelaskan APA yang melenceng saat perangkat offline
     * supaya owner bisa merapikan angkanya.
     */
    public function index(): Response
    {
        return Inertia::render('Owner/OfflineReview/Index', [
            // Ditunda ([BL-037]): daftar ini paling mahal di halaman — tiap
            // baris menarik item, modifier, dan pembayarannya, lalu alasan
            // penandaannya direkonstruksi satu per satu terhadap katalog.
            'transactions' => Inertia::defer(function () {
                $transactions = Transaction::where('sync_status', Transaction::SYNC_NEEDS_REVIEW)
                    ->with(['items.modifiers', 'payments.paymentMethod', 'user:id,name'])
                    ->orderByDesc('occurred_at')
                    ->paginate(20);

                $transactions->getCollection()->transform(function (Transaction $tx) {
                    $tx->review_reasons = $this->explainReasons($tx);

                    return $tx;
                });

                return $transactions;
            }),
            // Varian yang stoknya minus — akar masalah paling umum, dan yang
            // paling perlu tindakan (opname fisik lalu adjust). Tetap eager:
            // satu kueri pendek, dan inilah tindakan yang bisa dimulai owner
            // sambil daftar di bawahnya masih dimuat.
            'negativeVariants' => $this->negativeStockVariants(),
        ]);
    }

    /**
     * Tandai satu transaksi sudah dibereskan.
     *
     * Sengaja tidak mengubah stok: koreksi stok dilakukan owner lewat halaman
     * Stok (opname → adjust). Aksi ini hanya mencatat "sudah saya tinjau",
     * supaya badge tidak menyala selamanya.
     */
    public function resolve(Transaction $transaction): RedirectResponse
    {
        $user = Auth::user();

        if (! $user || $transaction->tenant_id !== $user->tenant_id) {
            abort(403, 'Anda tidak memiliki akses ke transaksi ini.');
        }

        if (! $transaction->needsReview()) {
            return back()->with('error', 'Transaksi ini sudah tidak perlu ditinjau.');
        }

        $transaction->update(['sync_status' => null]);

        return back()->with('success', "Transaksi {$transaction->code} ditandai sudah ditinjau.");
    }

    /**
     * Kenapa transaksi ini ditandai? Server hanya menyimpan satu flag, jadi
     * alasannya direkonstruksi di sini dengan membandingkan snapshot item
     * terhadap katalog sekarang.
     *
     * @return array<int, string>
     */
    private function explainReasons(Transaction $transaction): array
    {
        $reasons = [];

        foreach ($transaction->items as $item) {
            $variant = ProductVariant::withTrashed()->find($item->product_variant_id);

            if (! $variant) {
                $reasons[] = "{$item->variant_name}: produk tidak ditemukan lagi.";

                continue;
            }

            if ($variant->trashed()) {
                $reasons[] = "{$item->variant_name}: produk sudah dihapus dari katalog.";
            }

            if ($variant->stock < 0) {
                $reasons[] = "{$item->variant_name}: stok minus ({$variant->stock}), perlu opname fisik.";
            }

            // Sejak `[BL-115]` baris offline membawa jejak potongannya, jadi
            // potongan yang sah berhenti muncul di sini sebagai "harga beda".
            // Sebelumnya setiap penjualan berdiskon offline mengadukan dirinya
            // sendiri, dan daftar ini penuh oleh hal yang memang disengaja.
            $discount = (float) $item->discount_amount;

            if ($discount > 0) {
                if ($item->discount_rule_id !== null || $item->below_floor_approved_by !== null) {
                    continue;
                }

                $reasons[] = sprintf(
                    '%s: potongan Rp %s tanpa aturan diskon yang cocok. Periksa harga yang ditagih.',
                    $item->variant_name,
                    number_format($discount, 0, ',', '.'),
                );

                continue;
            }

            $sold = (float) ($item->original_unit_price ?? $item->unit_price);

            if (abs((float) $variant->price - $sold) >= 0.01) {
                $reasons[] = sprintf(
                    '%s: dijual Rp %s, harga katalog kini Rp %s.',
                    $item->variant_name,
                    number_format((float) $item->unit_price, 0, ',', '.'),
                    number_format((float) $variant->price, 0, ',', '.'),
                );
            }
        }

        if (empty($reasons)) {
            // Flag-nya menyala tapi katalog sudah dirapikan sejak sync — biasanya
            // owner sudah mengoreksi stok/harga tanpa menandai selesai.
            $reasons[] = 'Selisihnya sudah tidak ada, kemungkinan sudah Anda koreksi.';
        }

        return array_values(array_unique($reasons));
    }

    /**
     * Varian bertok minus milik tenant ini.
     *
     * @return array<int, array{id:int, product_name:string, variant_name:string, stock:int}>
     */
    private function negativeStockVariants(): array
    {
        $tenantId = Auth::user()->tenant_id;

        return ProductVariant::whereHas('product', fn ($q) => $q->where('tenant_id', $tenantId))
            ->where('stock', '<', 0)
            ->with('product:id,name')
            ->get()
            ->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'product_name' => $v->product->name,
                'variant_name' => $v->name,
                'stock' => $v->stock,
            ])
            ->all();
    }
}
