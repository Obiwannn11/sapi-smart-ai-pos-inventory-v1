<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;

/**
 * Merakit context array ringkas & deterministik yang jadi base knowledge LLM.
 *
 * HANYA data agregat — tanpa PII (customer_name / table_number / baris mentah).
 *
 * Catatan scoping: query penjualan/transaksi ter-scope tenant lewat TenantScope
 * berbasis auth(), sedangkan $tenant hanya dipakai untuk nama & badge inventori
 * (BadgeHelperService memfilter eksplisit lewat $tenant->id). Pada request/test
 * terautentikasi di mana auth()->user()->tenant_id === $tenant->id keduanya
 * konsisten. Pemanggilan dari Job tanpa auth ditangani di layer Job (Phase AI-3).
 */
class AiContextService
{
    public function __construct(
        private ProfitService $profitService,
        private BadgeHelperService $badgeHelper,
    ) {}

    /**
     * Bangun konteks agregat untuk LLM.
     *
     * @return array<string, mixed>
     */
    public function buildContext(Tenant $tenant, Carbon $from, Carbon $to): array
    {
        $completed = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween($from, $to);

        $revenue = (float) (clone $completed)->sum('total_amount');
        $count = (clone $completed)->count();

        $topProducts = TransactionItem::query()
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveBetween($from, $to);
            })
            ->selectRaw('variant_name, SUM(qty) as qty, SUM(subtotal) as revenue')
            ->groupBy('variant_name')
            ->orderByDesc('qty')
            ->take(10)
            ->get();

        $effectiveDate = Transaction::effectiveDateSql();
        $dailyTrend = (clone $completed)
            ->selectRaw("DATE({$effectiveDate}) as date, COUNT(*) as count, SUM(total_amount) as revenue")
            ->groupByRaw("DATE({$effectiveDate})")
            ->orderBy('date')
            ->get();

        return [
            'business' => [
                'name' => $tenant->name,
                'period' => ['from' => $from->toDateString(), 'to' => $to->toDateString()],
            ],
            'sales' => [
                'revenue' => $revenue,
                'transaction_count' => $count,
                'average_ticket' => $count > 0 ? round($revenue / $count) : 0,
            ],
            'profit' => $this->profitService->overallProfit($from, $to),
            'projection' => $this->profitService->projection($from, $to),
            'profit_by_item' => $this->profitService->profitByProduct($from, $to),
            'top_products' => $topProducts,
            'daily_trend' => $dailyTrend,
            'inventory' => $this->badgeHelper->generate($tenant),
        ];
    }
}
