<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

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
            'profit_by_item' => $this->cappedProfitByItem($from, $to),
            'top_products' => $topProducts,
            'daily_trend' => $dailyTrend,
            'inventory' => $this->badgeHelper->generate($tenant),
        ];
    }

    /**
     * `profit_by_item` dengan jumlah barisnya dibatasi (`[BL-069]`).
     *
     * Satu-satunya bagian konteks yang tumbuh mengikuti ukuran tenant. Sisa
     * konteks sudah teragregasi — `top_products` di-`take(10)`, `daily_trend`
     * sepanjang periode — sehingga tenant 3.367 transaksi dan tenant 4.476
     * transaksi sama-sama berhenti di kisaran 1.850 token. Yang tidak berhenti
     * adalah daftar per produk, dan tanpa batas ini harga kuota AI ditetapkan
     * atas ongkos yang tidak punya atap.
     *
     * Yang tidak muat TIDAK dibuang diam-diam. Daftar yang dipotong tanpa tanda
     * akan terbaca model sebagai seluruh katalog, dan jawabannya akan menyebut
     * "produk paling merugi Anda" untuk produk yang kebetulan lolos batas.
     * Karena itu sisanya diringkas jadi satu baris agregat beserta jumlah
     * variannya.
     *
     * Urutannya tetap qty menurun, sama seperti sebelum ada batas ini: yang
     * dipotong adalah produk bervolume paling kecil. Konsekuensinya disadari —
     * penjual lambat yang marginnya buruk bisa jatuh ke ringkasan — dan itu
     * sebabnya `others` membawa `margin_pct`-nya sendiri, supaya model masih
     * bisa melihat kalau ekor katalognya secara keseluruhan tidak sehat.
     *
     * @return array{items: list<array<string, mixed>>, shown: int, total: int, others: array<string, mixed>|null}
     */
    private function cappedProfitByItem(Carbon $from, Carbon $to): array
    {
        $limit = max(1, (int) config('ai.context.profit_by_item_limit', 20));
        $rows = $this->profitService->profitByProduct($from, $to);

        $items = $rows->take($limit)->values()->all();
        $rest = $rows->slice($limit);

        return [
            'items' => $items,
            'shown' => count($items),
            'total' => $rows->count(),
            'others' => $rest->isEmpty() ? null : $this->summarize($rest),
        ];
    }

    /**
     * Ringkas baris yang tidak muat jadi satu agregat berbentuk sama.
     *
     * Bentuknya sengaja meniru baris biasa — `qty`, `revenue`, `cogs`,
     * `margin`, `margin_pct` — supaya model tidak perlu diajari membaca dua
     * bentuk yang berbeda untuk data yang sama.
     *
     * @param  Collection<int, array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function summarize(Collection $rows): array
    {
        $revenue = (float) $rows->sum('revenue');
        $cogs = (float) $rows->sum('cogs');
        $margin = $revenue - $cogs;

        return [
            'variants' => $rows->count(),
            'qty' => (int) $rows->sum('qty'),
            'revenue' => $revenue,
            'cogs' => $cogs,
            'margin' => $margin,
            'margin_pct' => $revenue > 0 ? round($margin / $revenue * 100, 2) : 0.0,
        ];
    }
}
