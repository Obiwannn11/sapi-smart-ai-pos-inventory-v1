<?php

namespace App\Services;

use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Sumber kebenaran tunggal untuk profit.
 *
 * Profit tidak pernah disimpan di database, jadi service ini menurunkannya:
 * revenue − COGS, dengan COGS = SUM(qty × product_variants.cost_price).
 *
 * Catatan penting:
 * - Query ter-scope tenant otomatis lewat TenantScope pada Transaction
 *   (butuh konteks auth; saat dipanggil dari Job, tenant di-handle di Job).
 * - Join ke product_variants memakai query builder biasa sehingga
 *   global scope SoftDeletes TIDAK ikut → varian yang sudah soft-deleted
 *   tetap terhitung (COGS historis tidak hilang).
 * - COGS memakai cost_price varian SAAT INI, bukan biaya historis saat transaksi.
 *   Ini limitasi yang diketahui.
 */
class ProfitService
{
    /**
     * Ringkasan profit keseluruhan pada rentang tanggal.
     *
     * @return array{revenue: float, cogs: float, gross_profit: float, margin_pct: float}
     */
    public function overallProfit(Carbon $from, Carbon $to): array
    {
        $revenue = (float) Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween($from, $to)
            ->sum('total_amount');

        $cogs = (float) $this->cogsQuery($from, $to)->value('cogs');

        $grossProfit = $revenue - $cogs;
        $marginPct = $revenue > 0 ? round($grossProfit / $revenue * 100, 2) : 0.0;

        return [
            'revenue' => $revenue,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'margin_pct' => $marginPct,
        ];
    }

    /**
     * Profit per varian produk (untuk saran diskon).
     *
     * Digroup berdasarkan variant_name terdenormalisasi, sama seperti
     * ReportController::daily().
     *
     * @return Collection<int, array{variant_name: string, qty: int, revenue: float, cogs: float, margin: float, margin_pct: float}>
     */
    public function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        return TransactionItem::query()
            ->join('product_variants', 'transaction_items.product_variant_id', '=', 'product_variants.id')
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveBetween($from, $to);
            })
            ->selectRaw('transaction_items.variant_name')
            ->selectRaw('SUM(transaction_items.qty) as qty')
            ->selectRaw('SUM(transaction_items.subtotal) as revenue')
            ->selectRaw('SUM(transaction_items.qty * product_variants.cost_price) as cogs')
            ->groupBy('transaction_items.variant_name')
            ->orderByDesc('qty')
            ->get()
            ->map(function ($row) {
                $revenue = (float) $row->revenue;
                $cogs = (float) $row->cogs;
                $margin = $revenue - $cogs;

                return [
                    'variant_name' => $row->variant_name,
                    'qty' => (int) $row->qty,
                    'revenue' => $revenue,
                    'cogs' => $cogs,
                    'margin' => $margin,
                    'margin_pct' => $revenue > 0 ? round($margin / $revenue * 100, 2) : 0.0,
                ];
            });
    }

    /**
     * Proyeksi sederhana: rata-rata gross profit harian × jumlah hari periode berikutnya.
     *
     * Asumsi: tren linear, tanpa musiman. Indikasi, bukan forecast presisi.
     *
     * @return array{basis_days: int, avg_daily_profit: float, projected_next_period: float}
     */
    public function projection(Carbon $from, Carbon $to): array
    {
        $days = max(1, (int) $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1);
        $profit = $this->overallProfit($from, $to)['gross_profit'];
        $avgDaily = $profit / $days;

        return [
            'basis_days' => $days,
            'avg_daily_profit' => round($avgDaily, 2),
            'projected_next_period' => round($avgDaily * $days, 2),
        ];
    }

    /**
     * Query COGS dasar: SUM(qty × cost_price).
     *
     * Memakai join query builder (bukan relasi Eloquent) supaya varian yang
     * sudah soft-deleted tetap ikut terhitung.
     */
    private function cogsQuery(Carbon $from, Carbon $to): Builder
    {
        return TransactionItem::query()
            ->join('product_variants', 'transaction_items.product_variant_id', '=', 'product_variants.id')
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveBetween($from, $to);
            })
            ->selectRaw('SUM(transaction_items.qty * product_variants.cost_price) as cogs');
    }
}
