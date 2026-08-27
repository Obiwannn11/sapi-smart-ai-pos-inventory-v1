<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Transaction;

/**
 * Satu-satunya tempat aritmetika pajak hidup ([BL-065]).
 *
 * Alasan kelas ini ada bukan kerapian, melainkan bahwa perhitungan total
 * keranjang sudah tersebar di tujuh tempat — tiga di antaranya JavaScript,
 * satu berjalan offline dan menyerahkan struk ke pelanggan sebelum server
 * pernah melihat transaksinya. Aturan yang diturunkan ulang di tiap tempat
 * suatu hari akan berbeda satu rupiah, dan yang salah bukan basis data
 * melainkan kertas yang sudah dibawa pulang.
 *
 * Invarian yang dijaga seluruh kelas ini, di kedua mode:
 *
 *     subtotal + pajak = total, TEPAT, dalam rupiah tersimpan.
 *
 * Caranya selalu sama: bulatkan tepat SATU angka — pajaknya — lalu turunkan
 * angka ketiga dengan pengurangan. Yang berbeda antar mode hanyalah angka
 * mana yang jadi jangkar, dan itu mengikuti kenyataan: di mode exclusive
 * harga katalog yang nyata, di mode inclusive uang yang berpindah tangan
 * yang nyata.
 */
class TaxCalculator
{
    /**
     * Konteks kosong — dipakai transaksi tanpa pajak.
     *
     * `mode`, `rate`, dan `label` sengaja `null` alih-alih 0/'exclusive':
     * kolomnya menyimpan apa adanya, dan `null` di sana berarti "transaksi
     * ini lahir tanpa pajak", yang berbeda artinya dari "dipajaki nol persen".
     *
     * @return array{enabled: bool, mode: ?string, rate: ?float, label: ?string}
     */
    public function noTax(): array
    {
        return ['enabled' => false, 'mode' => null, 'rate' => null, 'label' => null];
    }

    /**
     * Konteks pajak tenant SEKARANG — yang akan dibekukan ke penjualan baru.
     *
     * @return array{enabled: bool, mode: ?string, rate: ?float, label: ?string}
     */
    public function contextFor(?Tenant $tenant): array
    {
        if (! $tenant || ! $tenant->tax_enabled) {
            return $this->noTax();
        }

        return [
            'enabled' => true,
            'mode' => $tenant->tax_mode,
            'rate' => (float) $tenant->tax_rate,
            'label' => $tenant->tax_label,
        ];
    }

    /**
     * Konteks yang sudah DIBEKUKAN pada sebuah transaksi.
     *
     * Dipakai saat transaksi lama dihitung ulang (`TransactionEditService`):
     * mengedit qty penjualan bulan lalu tidak boleh memungut ulang dengan
     * tarif yang berlaku hari ini.
     *
     * @return array{enabled: bool, mode: ?string, rate: ?float, label: ?string}
     */
    public function contextOf(Transaction $transaction): array
    {
        if ($transaction->tax_mode === null) {
            return $this->noTax();
        }

        return [
            'enabled' => true,
            'mode' => $transaction->tax_mode,
            'rate' => (float) $transaction->tax_rate,
            'label' => $transaction->tax_label,
        ];
    }

    /**
     * Urai satu angka dasar jadi tiga angka uang.
     *
     * `$base` adalah jumlah seluruh baris penjualan — apa adanya, sebelum
     * pajak disentuh. Yang berbeda antar mode adalah PERAN angka itu:
     *
     * - **exclusive** — `$base` adalah subtotalnya. Pajak ditambahkan di
     *   atasnya, jadi yang dibayar pelanggan naik dan pendapatan toko tetap.
     * - **inclusive** — `$base` adalah totalnya. Pajak diurai ke belakang
     *   dari angka itu, jadi yang dibayar pelanggan tidak berubah sepeser pun
     *   dan pendapatan toko yang turun.
     *
     * @param  array{enabled: bool, mode: ?string, rate: ?float, label: ?string}  $context
     * @return array{subtotal: float, tax: float, total: float}
     */
    public function apply(float $base, array $context): array
    {
        $rate = (float) ($context['rate'] ?? 0);

        if (! ($context['enabled'] ?? false) || $rate <= 0) {
            return ['subtotal' => $base, 'tax' => 0.0, 'total' => $base];
        }

        if (($context['mode'] ?? null) === Tenant::TAX_MODE_INCLUSIVE) {
            // Pajak diurai dari dalam: rate/(100+rate), bukan rate/100.
            // Salah satu dari dua rumus ini akan terlihat benar sekilas, dan
            // yang salah menghasilkan pajak ~11% lebih besar dari seharusnya.
            $tax = $this->roundToRupiah($base * $rate / (100 + $rate));

            return ['subtotal' => $base - $tax, 'tax' => $tax, 'total' => $base];
        }

        $tax = $this->roundToRupiah($base * $rate / 100);

        return ['subtotal' => $base, 'tax' => $tax, 'total' => $base + $tax];
    }

    /**
     * Kolom transaksi untuk satu penjualan — angka DAN konteks bekunya.
     *
     * Dipakai keempat jalur penulis transaksi supaya tidak ada satu pun yang
     * menulis angkanya tanpa ikut membekukan konteksnya.
     *
     * @param  array{enabled: bool, mode: ?string, rate: ?float, label: ?string}  $context
     * @return array<string, mixed>
     */
    public function columnsFor(float $base, array $context): array
    {
        $amounts = $this->apply($base, $context);
        $taxed = ($context['enabled'] ?? false) && (float) ($context['rate'] ?? 0) > 0;

        return [
            'subtotal_amount' => $amounts['subtotal'],
            'tax_amount' => $amounts['tax'],
            'total_amount' => $amounts['total'],
            'tax_rate' => $taxed ? $context['rate'] : null,
            'tax_mode' => $taxed ? $context['mode'] : null,
            'tax_label' => $taxed ? $context['label'] : null,
        ];
    }

    /**
     * Rupiah tidak punya pecahan yang beredar — laci kasir tidak bisa
     * mengembalikan setengah rupiah, jadi pajak dibulatkan ke rupiah penuh
     * dan selisihnya diserap di baris pajak itu sendiri.
     */
    private function roundToRupiah(float $value): float
    {
        return round($value);
    }
}
