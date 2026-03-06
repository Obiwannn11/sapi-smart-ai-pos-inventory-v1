<?php

namespace App\Services;

use App\Models\ProductVariant;
use App\Models\Tenant;

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
            ->whereDoesntHave('transactionItems', function ($q) {
                $q->where('created_at', '>=', now()->subDays(30));
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

        return $badges;
    }
}
