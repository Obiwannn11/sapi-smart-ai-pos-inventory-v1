<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Tenant;
use App\Models\Transaction;
use App\Models\TransactionItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Isi link data untuk AI (`[BL-102]`): satu paket tetap tanpa parameter,
 * ditulis sebagai teks Markdown.
 *
 * Teks, bukan JSON: pengambil halaman AI menerima teks, HTML, dan PDF, dan JSON
 * belum tentu termasuk. Paketnya tetap karena AI tidak bisa menyusun URL
 * dengan parameter lain sendiri, jadi satu kali ambil harus sudah cukup untuk
 * pertanyaan yang umum.
 *
 * Seluruh angka datang dari layanan yang sama dengan tool MCP, supaya jawaban
 * lewat link dan lewat MCP tidak bisa berbeda. Query ter-scope tenant lewat
 * TenantScope berbasis auth(), jadi kelas ini hanya boleh dipanggil dalam
 * request yang sudah terautentikasi sebagai owner tenant tersebut.
 */
class ConnectorSummaryService
{
    /**
     * Batas produk di daftar menu. Yang tidak muat disebut jumlahnya, bukan
     * dibuang diam-diam: daftar terpotong tanpa tanda akan dibacakan AI sebagai
     * seluruh katalog saat tes baca di prompt bawaan.
     */
    private const MENU_LIMIT = 100;

    public function __construct(
        private AiContextService $context,
        private ProfitService $profit,
        private ProductCatalogService $catalog,
    ) {}

    /**
     * Paket data toko sebagai teks Markdown.
     */
    public function toMarkdown(Tenant $tenant, Carbon $now): string
    {
        $endOfToday = $now->copy()->endOfDay();
        $last30From = $now->copy()->subDays(29)->startOfDay();

        $context = $this->context->buildContext($tenant, $last30From, $endOfToday);

        return implode("\n\n", [
            $this->header($tenant, $now),
            $this->salesSection($now),
            $this->profitSection($context['profit'], $last30From, $endOfToday),
            $this->topProductsSection($context['top_products']),
            $this->profitByItemSection($context['profit_by_item']),
            $this->menuSection(),
        ])."\n";
    }

    private function header(Tenant $tenant, Carbon $now): string
    {
        return implode("\n", [
            '# Data toko: '.$tenant->name,
            '',
            'Diambil dari SAPI pada '.$this->date($now, 'j F Y H.i').' ('.BusinessClock::timezone().'). Semua angka dalam rupiah dan hanya berupa agregat, tanpa data pelanggan.',
            '',
            '- **Omzet**: uang yang dibayar pelanggan, termasuk pajak dan service charge.',
            '- **Laba kotor**: pendapatan bersih (tanpa pajak dan service charge) dikurangi modal barang.',
            '- Hanya transaksi yang selesai yang dihitung. Transaksi yang dibatalkan tidak ikut.',
        ]);
    }

    private function salesSection(Carbon $now): string
    {
        $today = $now->copy()->startOfDay();
        $endOfToday = $now->copy()->endOfDay();
        $lastMonth = $now->copy()->subMonthNoOverflow();

        // Label menyebut rentangnya sendiri. "Bulan ini" yang baru berjalan
        // setengah akan dibandingkan AI dengan bulan lalu yang penuh kalau
        // tidak dikatakan.
        $periods = [
            'Hari ini' => [$today, $endOfToday],
            '7 hari terakhir' => [$today->copy()->subDays(6), $endOfToday],
            '30 hari terakhir' => [$today->copy()->subDays(29), $endOfToday],
            'Bulan ini (sampai hari ini)' => [$now->copy()->startOfMonth(), $endOfToday],
            'Bulan lalu (sebulan penuh)' => [$lastMonth->copy()->startOfMonth(), $lastMonth->copy()->endOfMonth()],
        ];

        $lines = [
            '## Ringkasan penjualan',
            '',
            '| Periode | Tanggal | Omzet | Transaksi | Rata-rata per transaksi | Laba kotor |',
            '|---|---|---|---|---|---|',
        ];

        foreach ($periods as $label => [$from, $to]) {
            $completed = Transaction::where('status', Transaction::STATUS_COMPLETED)
                ->whereEffectiveBetween($from, $to);

            $revenue = (float) (clone $completed)->sum('total_amount');
            $count = (clone $completed)->count();

            $lines[] = $this->row([
                $label,
                $this->dateRange($from, $to),
                $this->rupiah($revenue),
                (string) $count,
                $this->rupiah($count > 0 ? $revenue / $count : 0),
                $this->rupiah($this->profit->overallProfit($from, $to)['gross_profit']),
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{revenue: float, net_revenue: float, tax: float, service_charge: float, cogs: float, gross_profit: float, margin_pct: float}  $profit
     */
    private function profitSection(array $profit, Carbon $from, Carbon $to): string
    {
        return implode("\n", [
            '## Profit 30 hari terakhir ('.$this->dateRange($from, $to).')',
            '',
            '- Omzet: '.$this->rupiah($profit['revenue']),
            '- Pendapatan bersih: '.$this->rupiah($profit['net_revenue']),
            '- Pajak: '.$this->rupiah($profit['tax']),
            '- Service charge: '.$this->rupiah($profit['service_charge']),
            '- Modal barang: '.$this->rupiah($profit['cogs']),
            '- Laba kotor: '.$this->rupiah($profit['gross_profit']).' (margin '.$this->percent($profit['margin_pct']).')',
        ]);
    }

    /**
     * @param  Collection<int, TransactionItem>  $topProducts
     */
    private function topProductsSection(Collection $topProducts): string
    {
        $lines = ['## Produk terlaris 30 hari terakhir', ''];

        if ($topProducts->isEmpty()) {
            $lines[] = 'Belum ada penjualan dalam 30 hari terakhir.';

            return implode("\n", $lines);
        }

        $lines[] = '| Produk | Varian | Terjual | Omzet |';
        $lines[] = '|---|---|---|---|';

        foreach ($topProducts as $item) {
            $lines[] = $this->row([
                (string) $item->product_name,
                (string) $item->variant_name,
                (string) (int) $item->qty,
                $this->rupiah((float) $item->revenue),
            ]);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array{items: list<array<string, mixed>>, shown: int, total: int, others: array<string, mixed>|null}  $profitByItem
     */
    private function profitByItemSection(array $profitByItem): string
    {
        $lines = ['## Laba per produk 30 hari terakhir', ''];

        if ($profitByItem['total'] === 0) {
            $lines[] = 'Belum ada penjualan dalam 30 hari terakhir.';

            return implode("\n", $lines);
        }

        $lines[] = '| Produk | Varian | Terjual | Pendapatan bersih | Modal | Laba | Margin |';
        $lines[] = '|---|---|---|---|---|---|---|';

        foreach ($profitByItem['items'] as $item) {
            $lines[] = $this->row([
                (string) $item['product_name'],
                (string) $item['variant_name'],
                (string) $item['qty'],
                $this->rupiah($item['net_revenue']),
                $this->rupiah($item['cogs']),
                $this->rupiah($item['margin']),
                $this->percent($item['margin_pct']),
            ]);
        }

        if ($others = $profitByItem['others']) {
            $lines[] = $this->row([
                $others['variants'].' varian lain (digabung)',
                '',
                (string) $others['qty'],
                $this->rupiah($others['net_revenue']),
                $this->rupiah($others['cogs']),
                $this->rupiah($others['margin']),
                $this->percent($others['margin_pct']),
            ]);
            $lines[] = '';
            $lines[] = "Ditampilkan {$profitByItem['shown']} dari {$profitByItem['total']} varian terlaris. Sisanya digabung di baris terakhir.";
        }

        return implode("\n", $lines);
    }

    private function menuSection(): string
    {
        $products = $this->catalog->activeMenu()->sortBy('name')->values();

        $lines = ['## Produk yang dijual', ''];

        if ($products->isEmpty()) {
            $lines[] = 'Belum ada produk aktif.';

            return implode("\n", $lines);
        }

        $lines[] = "Produk aktif saat ini: {$products->count()} produk.";

        $shown = $products->take(self::MENU_LIMIT);

        foreach ($shown->groupBy(fn (Product $product) => $product->category?->name ?? 'Tanpa kategori') as $category => $items) {
            $lines[] = '';
            $lines[] = '### '.$category;

            foreach ($items as $product) {
                $variants = $product->variants
                    ->map(fn (ProductVariant $variant) => $variant->name.' '.$this->rupiah((float) $variant->price).' (stok '.$variant->stock.')')
                    ->implode('; ');

                $lines[] = '- '.$product->name.($variants !== '' ? ': '.$variants : '');
            }
        }

        if ($products->count() > self::MENU_LIMIT) {
            $lines[] = '';
            $lines[] = 'Dan '.($products->count() - self::MENU_LIMIT).' produk aktif lain yang tidak ditampilkan di sini.';
        }

        return implode("\n", $lines);
    }

    private function rupiah(float $amount): string
    {
        return 'Rp '.number_format($amount, 0, ',', '.');
    }

    private function percent(float $value): string
    {
        return number_format($value, 1, ',', '.').'%';
    }

    private function date(Carbon $date, string $format): string
    {
        return $date->copy()->locale('id')->translatedFormat($format);
    }

    private function dateRange(Carbon $from, Carbon $to): string
    {
        return $from->isSameDay($to)
            ? $this->date($from, 'j M Y')
            : $this->date($from, 'j M Y').' sampai '.$this->date($to, 'j M Y');
    }

    /**
     * Satu baris tabel Markdown. `|` di nama produk akan memotong kolom, jadi
     * di-escape.
     *
     * @param  list<string>  $cells
     */
    private function row(array $cells): string
    {
        $escaped = array_map(fn (string $cell) => str_replace(['|', "\n"], ['\|', ' '], $cell), $cells);

        return '| '.implode(' | ', $escaped).' |';
    }
}
