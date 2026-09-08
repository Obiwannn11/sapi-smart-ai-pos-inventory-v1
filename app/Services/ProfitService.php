<?php

namespace App\Services;

use App\Models\Tenant;
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
 *
 * **Pajak dan margin ([BL-065]).** Margin dihitung terhadap pendapatan toko,
 * BUKAN terhadap uang yang berpindah tangan. Keduanya berbeda sejak pajak
 * masuk ke kasir, dan bedanya persis sebesar pajaknya — di KEDUA mode:
 *
 *   - exclusive: pelanggan membayar harga + pajak, jadi `total_amount` memuat
 *     pajak yang tidak pernah jadi milik toko;
 *   - inclusive: pelanggan membayar harga katalog yang sudah mengandung
 *     pajak, jadi `total_amount` tidak bergerak sama sekali saat pajak
 *     dinyalakan — dan justru itu jebakannya. Marginnya tetap terbaca seperti
 *     sebelumnya padahal margin sebenarnya sudah turun.
 *
 * **Biaya layanan dan margin ([BL-097] jawaban 3).** Bawaannya: biaya
 * layanan BUKAN pendapatan toko — di banyak tempat ia dikumpulkan lalu
 * dibagikan ke staf. Menganggapnya pendapatan mengulang persis cacat yang
 * baru diperbaiki di atas, dan cacat itu tidak terlihat dari angkanya
 * sendiri. Karena `service_charge_amount` kolomnya sendiri dan tidak pernah
 * dilebur ke `subtotal_amount`, `net_revenue` mengecualikannya DENGAN
 * SENDIRINYA — tidak ada satu kueri pun yang perlu diubah untuk itu.
 * Membalik jawabannya nanti cukup dengan menjumlahkannya kembali di sini,
 * tanpa migrasi dan tanpa menyentuh satu baris transaksi lama.
 *
 * Untuk tarif 11% pada toko bermargin nyata 30%, memakai `total_amount`
 * melaporkan 36,9% — meleset hampir tujuh poin, sama besar di kedua mode.
 * Angka ini tidak pernah dilihat manusia yang bisa curiga: pembacanya konteks
 * analisis AI dan MCP `GetProfitTool`. Karena itu payload membawa ketiganya
 * apa adanya — yang dibayar pelanggan, pajaknya, dan pendapatan tokonya —
 * alih-alih menyerahkan pengurangannya ke model.
 */
class ProfitService
{
    /**
     * Ringkasan profit keseluruhan pada rentang tanggal.
     *
     * `revenue` TIDAK berubah arti — ia tetap uang yang dibayar pelanggan,
     * sejalan dengan `total_amount` di seluruh basis kode. Yang dipakai
     * sebagai dasar margin adalah `net_revenue`, dan `gross_profit` diturunkan
     * darinya. Konsekuensinya `revenue - cogs != gross_profit` untuk tenant
     * yang memungut pajak, dan itu memang benar: selisihnya bukan keuntungan
     * yang hilang, melainkan pajak yang tidak pernah jadi milik toko.
     *
     * @return array{revenue: float, net_revenue: float, tax: float, service_charge: float, cogs: float, gross_profit: float, margin_pct: float}
     */
    public function overallProfit(Carbon $from, Carbon $to): array
    {
        $totals = Transaction::where('status', Transaction::STATUS_COMPLETED)
            ->whereEffectiveBetween($from, $to)
            ->selectRaw('COALESCE(SUM(total_amount), 0) as paid')
            ->selectRaw('COALESCE(SUM(subtotal_amount), 0) as net_revenue')
            ->selectRaw('COALESCE(SUM(tax_amount), 0) as tax')
            ->selectRaw('COALESCE(SUM(service_charge_amount), 0) as service_charge')
            ->first();

        $netRevenue = (float) $totals->net_revenue;
        $cogs = (float) $this->cogsQuery($from, $to)->value('cogs');

        $grossProfit = $netRevenue - $cogs;
        $marginPct = $netRevenue > 0 ? round($grossProfit / $netRevenue * 100, 2) : 0.0;

        return [
            'revenue' => (float) $totals->paid,
            'net_revenue' => $netRevenue,
            'tax' => (float) $totals->tax,
            // Angka keempat, dibawa apa adanya alih-alih menyerahkan
            // pengurangannya ke model ([BL-097]).
            'service_charge' => (float) $totals->service_charge,
            'cogs' => $cogs,
            'gross_profit' => $grossProfit,
            'margin_pct' => $marginPct,
        ];
    }

    /**
     * Profit per varian produk (untuk saran diskon).
     *
     * Dikelompokkan per PRODUK dan varian sekaligus. `variant_name` yang
     * terdenormalisasi hanya menyimpan nama variannya — "Hot", "Single",
     * "Plain" — dan nama itu dipakai ulang oleh produk yang berbeda;
     * mengelompokkan padanya saja melebur margin Cafe Latte "Hot" dengan Kopi
     * Susu "Hot" ke dalam satu baris yang tidak mewakili barang mana pun.
     * Nama produknya sudah ada di join yang memang sudah dilakukan untuk
     * `cost_price`, jadi yang perlu ditambahkan cuma satu tabel lagi.
     *
     * **Pajak per baris tidak disimpan** — hanya per transaksi. Padahal arti
     * `transaction_items.subtotal` berbeda antar mode: di exclusive ia sudah
     * bersih (jumlahnya sama dengan `subtotal_amount` transaksinya), sedangkan
     * di inclusive ia harga katalog yang SUDAH mengandung pajak. Tanpa koreksi
     * di bawah, ringkasan dan rincian akan mengirim dua angka margin yang
     * berbeda untuk periode yang sama — berdampingan, di satu payload, kepada
     * model yang tidak punya cara mencurigainya.
     *
     * Karena itu baris inclusive diurai dengan tarif yang DIBEKUKAN di
     * transaksinya. Hasil penjumlahannya bisa meleset rupiah dari
     * `subtotal_amount` transaksi karena pembulatannya jatuh di tempat lain.
     * `[BL-065]` butir 8 menolak pembulatan per item untuk STRUK — di sana
     * pelanggan memverifikasi angka tercetak sambil berdiri di depan kasir.
     * Di sini tidak ada yang memegang selisihnya, dan angka yang konsisten
     * satu sama lain lebih berharga daripada rupiah terakhir.
     *
     * @return Collection<int, array{product_name: string, variant_name: string, qty: int, revenue: float, net_revenue: float, cogs: float, margin: float, margin_pct: float}>
     */
    public function profitByProduct(Carbon $from, Carbon $to): Collection
    {
        return TransactionItem::query()
            ->join('product_variants', 'transaction_items.product_variant_id', '=', 'product_variants.id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            // Join ini hanya membawa konteks pajaknya; penyaring DAN scope
            // tenant tetap datang dari whereHas di bawah.
            ->join('transactions', 'transaction_items.transaction_id', '=', 'transactions.id')
            ->whereHas('transaction', function ($q) use ($from, $to) {
                $q->where('status', Transaction::STATUS_COMPLETED)
                    ->whereEffectiveBetween($from, $to);
            })
            ->selectRaw('products.name as product_name, transaction_items.variant_name')
            ->selectRaw('SUM(transaction_items.qty) as qty')
            ->selectRaw('SUM(transaction_items.subtotal) as revenue')
            ->selectRaw(
                'SUM(CASE WHEN transactions.tax_mode = ? THEN transaction_items.subtotal * 100.0 / (100 + transactions.tax_rate)'
                .' ELSE transaction_items.subtotal END) as net_revenue',
                [Tenant::TAX_MODE_INCLUSIVE]
            )
            ->selectRaw('SUM(transaction_items.qty * product_variants.cost_price) as cogs')
            ->groupBy('products.name', 'transaction_items.variant_name')
            ->orderByDesc('qty')
            ->get()
            ->map(function ($row) {
                $netRevenue = round((float) $row->net_revenue, 2);
                $cogs = (float) $row->cogs;
                $margin = $netRevenue - $cogs;

                return [
                    'product_name' => $row->product_name,
                    'variant_name' => $row->variant_name,
                    'qty' => (int) $row->qty,
                    'revenue' => (float) $row->revenue,
                    'net_revenue' => $netRevenue,
                    'cogs' => $cogs,
                    'margin' => $margin,
                    'margin_pct' => $netRevenue > 0 ? round($margin / $netRevenue * 100, 2) : 0.0,
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
