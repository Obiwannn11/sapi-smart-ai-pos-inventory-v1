<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;

class BadgeHelperService
{
    /**
     * Generate semua badges untuk tenant.
     *
     * @return array Array of badge objects
     */
    public function generate(Tenant $tenant): array
    {
        $badges = [];

        // Scope helper: semua variant milik tenant ini
        // ProductVariant tidak punya tenant_id langsung → query via product
        $variantScope = ProductVariant::whereHas('product', function ($q) use ($tenant) {
            $q->where('tenant_id', $tenant->id);
        });

        // --- Badge 1: Stok Kritis (≤ 5, belum habis) ---
        $lowStock = (clone $variantScope)
            ->where('stock', '<=', 5)
            ->where('stock', '>', 0)
            ->with('product:id,name')
            ->get();

        if ($lowStock->count() > 0) {
            $badges[] = [
                'type' => 'low_stock',
                'severity' => 'warning',
                'title' => 'Stok Kritis',
                'count' => $lowStock->count(),
                'message' => "{$lowStock->count()} varian mendekati habis",
                'items' => $lowStock->map(fn ($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => $v->stock,
                ])->toArray(),
            ];
        }

        // --- Badge 2: Stok Habis ---
        $outOfStock = (clone $variantScope)
            ->where('stock', '<=', 0)
            ->with('product:id,name')
            ->get();

        if ($outOfStock->count() > 0) {
            $badges[] = [
                'type' => 'out_of_stock',
                'severity' => 'danger',
                'title' => 'Stok Habis',
                'count' => $outOfStock->count(),
                'message' => "{$outOfStock->count()} varian kehabisan stok",
                'items' => $outOfStock->map(fn ($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => 0,
                ])->toArray(),
            ];
        }

        // --- Badge 3: Dead Stock (0 penjualan dalam 30 hari, stok > 0) ---
        $deadStock = (clone $variantScope)
            ->where('stock', '>', 0)
            ->whereDoesntHave('transactionItems.transaction', function ($q) {
                $q->where('transactions.created_at', '>=', now()->subDays(30))
                    ->where('transactions.status', 'completed');
            })
            ->with('product:id,name')
            ->get();

        if ($deadStock->count() > 0) {
            $badges[] = [
                'type' => 'dead_stock',
                'severity' => 'info',
                'title' => 'Dead Stock',
                'count' => $deadStock->count(),
                'message' => "{$deadStock->count()} varian tidak terjual 30 hari terakhir",
                'items' => $deadStock->map(fn ($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => $v->stock,
                ])->toArray(),
            ];
        }

        // --- Badge 4: Sudah Expired (expiry_date < hari ini) ---
        $alreadyExpired = (clone $variantScope)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '<', now()->startOfDay())
            ->where('stock', '>', 0)
            ->with('product:id,name')
            ->get();

        if ($alreadyExpired->count() > 0) {
            $badges[] = [
                'type' => 'expired',
                'severity' => 'danger',
                'title' => 'Sudah Expired',
                'count' => $alreadyExpired->count(),
                'message' => "{$alreadyExpired->count()} varian sudah kedaluwarsa",
                'items' => $alreadyExpired->map(fn ($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => $v->stock,
                    'expiry_date' => $v->expiry_date->format('Y-m-d'),
                ])->toArray(),
            ];
        }

        // --- Badge 5: Mendekati Expired (expiry_date dalam 7 hari ke depan) ---
        $nearExpiry = (clone $variantScope)
            ->whereNotNull('expiry_date')
            ->where('expiry_date', '>=', now()->startOfDay())
            ->where('expiry_date', '<=', now()->addDays(7))
            ->where('stock', '>', 0)
            ->with('product:id,name')
            ->get();

        if ($nearExpiry->count() > 0) {
            $badges[] = [
                'type' => 'near_expiry',
                'severity' => 'warning',
                'title' => 'Mendekati Expired',
                'count' => $nearExpiry->count(),
                'message' => "{$nearExpiry->count()} varian mendekati kedaluwarsa",
                'items' => $nearExpiry->map(fn ($v) => [
                    'id' => $v->id,
                    'product_name' => $v->product->name,
                    'variant_name' => $v->name,
                    'stock' => $v->stock,
                    'expiry_date' => $v->expiry_date->format('Y-m-d'),
                ])->toArray(),
            ];
        }

        // --- Badge 6: Perlu Koreksi (sync) ---
        // Transaksi offline yang tersimpan dengan anomali: stok jadi minus, harga
        // berbeda dari katalog, atau produknya sudah dihapus. Penjualannya sah dan
        // tidak pernah ditolak — tapi angkanya perlu dirapikan owner.
        $needsReview = Transaction::where('tenant_id', $tenant->id)
            ->where('sync_status', Transaction::SYNC_NEEDS_REVIEW)
            ->orderByDesc('occurred_at')
            ->get(['id', 'code', 'occurred_at', 'total_amount', 'device_id']);

        if ($needsReview->count() > 0) {
            $badges[] = [
                'type' => 'needs_review',
                'severity' => 'warning',
                'title' => 'Perlu Koreksi (sync)',
                'count' => $needsReview->count(),
                'message' => "{$needsReview->count()} transaksi offline perlu ditinjau",
                'items' => $needsReview->map(fn ($t) => [
                    'id' => $t->id,
                    'code' => $t->code,
                    'occurred_at' => $t->effectiveDate()->format('Y-m-d H:i'),
                    'total_amount' => $t->total_amount,
                    'device_id' => $t->device_id,
                ])->toArray(),
            ];
        }

        return $badges;
    }
}
