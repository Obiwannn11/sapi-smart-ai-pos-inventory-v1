<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\Transaction;

/**
 * Satu-satunya tempat aritmetika pajak DAN biaya layanan hidup
 * ([BL-065], diperluas oleh [BL-097]).
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
 *     subtotal + biaya layanan + pajak = total, TEPAT, dalam rupiah tersimpan.
 *
 * Empat angka, DUA yang dibulatkan (biaya layanan lalu pajak), dan tetap
 * TEPAT SATU yang diturunkan dengan pengurangan. Yang berbeda antar mode
 * hanyalah angka mana yang jadi jangkar, dan itu mengikuti kenyataan: di mode
 * exclusive harga katalog yang nyata, di mode inclusive uang yang berpindah
 * tangan yang nyata.
 *
 * **Urutannya — biaya layanan LEBIH DULU, lalu pajak atas keduanya**
 * ([BL-097] jawaban 1, diverifikasi 2026-09-07). Dasar pengenaan PBJT adalah
 * "jumlah pembayaran yang diterima penyedia makanan dan/atau minuman"
 * (UU HKPD Pasal 51, dirinci PP 35/2023 Pasal 19), dan biaya layanan adalah
 * uang yang diterima restoran. Urutan terbalik — memungut pajak hanya atas
 * subtotal — menyetorkan pajak lebih KECIL dari yang terutang, dan yang
 * menanggung kekurangannya adalah tenant, bukan aplikasi ini. Kalau suatu
 * saat ditemukan Perda yang benar-benar mengecualikannya, yang dibutuhkan
 * adalah kolom `service_charge_taxable` per tenant — bukan menukar bawaan ini.
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
     * Pasangan `noTax()` untuk biaya layanan ([BL-097]).
     *
     * Tidak ada `mode` di sini, dan ketiadaannya disengaja: pajak butuh mode
     * karena harga katalog BISA sudah mengandungnya, sedangkan biaya layanan
     * tidak pernah "sudah termasuk" — ia selalu dihitung dari harga katalog
     * lalu ditambahkan. Yang berbeda antar mode pajak hanyalah bagaimana
     * pajak diurai SESUDAHNYA.
     *
     * @return array{enabled: bool, rate: ?float, label: ?string}
     */
    public function noServiceCharge(): array
    {
        return ['enabled' => false, 'rate' => null, 'label' => null];
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
     * Konteks biaya layanan tenant SEKARANG ([BL-097]).
     *
     * @return array{enabled: bool, rate: ?float, label: ?string}
     */
    public function serviceContextFor(?Tenant $tenant): array
    {
        if (! $tenant || ! $tenant->service_charge_enabled) {
            return $this->noServiceCharge();
        }

        return [
            'enabled' => true,
            'rate' => (float) $tenant->service_charge_rate,
            'label' => $tenant->service_charge_label,
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
     * Biaya layanan yang DIBEKUKAN pada sebuah transaksi ([BL-097]).
     *
     * Penandanya `service_charge_rate`, BUKAN `service_charge_amount`:
     * penjualan bertarif yang nilainya terlalu kecil sehingga biaya
     * layanannya membulat ke nol tetap penjualan yang memungut biaya layanan,
     * dan menghitung ulangnya tidak boleh menghapus tarif bekunya.
     *
     * @return array{enabled: bool, rate: ?float, label: ?string}
     */
    public function serviceContextOf(Transaction $transaction): array
    {
        if ($transaction->service_charge_rate === null) {
            return $this->noServiceCharge();
        }

        return [
            'enabled' => true,
            'rate' => (float) $transaction->service_charge_rate,
            'label' => $transaction->service_charge_label,
        ];
    }

    /**
     * Urai satu angka dasar jadi empat angka uang.
     *
     * `$base` adalah jumlah seluruh baris penjualan — apa adanya, sebelum
     * biaya layanan dan pajak disentuh. Yang berbeda antar mode adalah PERAN
     * angka itu:
     *
     * - **exclusive** — `$base` adalah subtotalnya. Biaya layanan lalu pajak
     *   ditambahkan di atasnya, jadi yang dibayar pelanggan naik dan
     *   pendapatan toko tetap.
     * - **inclusive** — `$base` + biaya layanan adalah totalnya. Pajak diurai
     *   ke belakang dari angka itu, jadi yang dibayar pelanggan tidak berubah
     *   sepeser pun oleh pajak dan pendapatan toko yang turun.
     *
     * **Ongkos yang harus disadari di mode inclusive:** biaya layanan dihitung
     * dari harga katalog yang SUDAH mengandung pajak, jadi angka biaya layanan
     * yang tercetak adalah angka KOTOR sementara "Subtotal" tercetak bersih.
     * Benar secara aritmetika — invariannya tetap tepat — tapi tidak semua
     * pemilik toko akan membacanya begitu. Secara hitungan ini identik dengan
     * "biaya layanan dikenai pajak bertarif sama", jadi ia konsisten dengan
     * urutan yang dipilih di docblock kelas.
     *
     * @param  array{enabled: bool, mode: ?string, rate: ?float, label: ?string}  $context
     * @param  array{enabled?: bool, rate?: ?float, label?: ?string}  $service
     * @return array{subtotal: float, service_charge: float, tax: float, total: float}
     */
    public function apply(float $base, array $context, array $service = []): array
    {
        $serviceCharge = $this->serviceChargeOn($base, $service);
        $rate = (float) ($context['rate'] ?? 0);

        if (! ($context['enabled'] ?? false) || $rate <= 0) {
            return [
                'subtotal' => $base,
                'service_charge' => $serviceCharge,
                'tax' => 0.0,
                'total' => $base + $serviceCharge,
            ];
        }

        if (($context['mode'] ?? null) === Tenant::TAX_MODE_INCLUSIVE) {
            // Pajak diurai dari dalam: rate/(100+rate), bukan rate/100.
            // Salah satu dari dua rumus ini akan terlihat benar sekilas, dan
            // yang salah menghasilkan pajak ~11% lebih besar dari seharusnya.
            $total = $base + $serviceCharge;
            $tax = $this->roundToRupiah($total * $rate / (100 + $rate));

            // Subtotal yang DITURUNKAN — satu-satunya angka yang tidak
            // dibulatkan sendiri, sehingga penjumlahan struk tidak bisa meleset.
            return [
                'subtotal' => $total - $serviceCharge - $tax,
                'service_charge' => $serviceCharge,
                'tax' => $tax,
                'total' => $total,
            ];
        }

        // Dasar pengenaan pajak = subtotal + biaya layanan; lihat docblock kelas.
        $tax = $this->roundToRupiah(($base + $serviceCharge) * $rate / 100);

        return [
            'subtotal' => $base,
            'service_charge' => $serviceCharge,
            'tax' => $tax,
            'total' => $base + $serviceCharge + $tax,
        ];
    }

    /**
     * Kolom transaksi untuk satu penjualan — angka DAN konteks bekunya.
     *
     * Dipakai keempat jalur penulis transaksi supaya tidak ada satu pun yang
     * menulis angkanya tanpa ikut membekukan konteksnya.
     *
     * @param  array{enabled: bool, mode: ?string, rate: ?float, label: ?string}  $context
     * @param  array{enabled?: bool, rate?: ?float, label?: ?string}  $service
     * @return array<string, mixed>
     */
    public function columnsFor(float $base, array $context, array $service = []): array
    {
        $amounts = $this->apply($base, $context, $service);
        $taxed = ($context['enabled'] ?? false) && (float) ($context['rate'] ?? 0) > 0;
        $charged = ($service['enabled'] ?? false) && (float) ($service['rate'] ?? 0) > 0;

        return [
            'subtotal_amount' => $amounts['subtotal'],
            'tax_amount' => $amounts['tax'],
            'service_charge_amount' => $amounts['service_charge'],
            'total_amount' => $amounts['total'],
            'tax_rate' => $taxed ? $context['rate'] : null,
            'tax_mode' => $taxed ? $context['mode'] : null,
            'tax_label' => $taxed ? $context['label'] : null,
            'service_charge_rate' => $charged ? $service['rate'] : null,
            'service_charge_label' => $charged ? $service['label'] : null,
        ];
    }

    /**
     * Biaya layanan atas jumlah baris penjualan ([BL-097]).
     *
     * Selalu dari `$base` apa adanya, di KEDUA mode pajak — biaya layanan
     * tidak punya mode sendiri.
     *
     * @param  array{enabled?: bool, rate?: ?float, label?: ?string}  $service
     */
    private function serviceChargeOn(float $base, array $service): float
    {
        $rate = (float) ($service['rate'] ?? 0);

        if (! ($service['enabled'] ?? false) || $rate <= 0) {
            return 0.0;
        }

        return $this->roundToRupiah($base * $rate / 100);
    }

    /**
     * Rupiah tidak punya pecahan yang beredar — laci kasir tidak bisa
     * mengembalikan setengah rupiah, jadi biaya layanan dan pajak dibulatkan
     * ke rupiah penuh dan selisihnya diserap di barisnya masing-masing.
     */
    private function roundToRupiah(float $value): float
    {
        return round($value);
    }
}
