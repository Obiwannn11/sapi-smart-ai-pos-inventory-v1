<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use Illuminate\Http\Request;

/**
 * Satu bentuk untuk semua penyedia pembayaran — `[BL-059]`(a).
 *
 * Kontraknya sengaja sempit: menerbitkan tagihan ke penyedia, lalu membaca
 * kabar balik darinya. Yang TIDAK ada di sini adalah melunasi tagihan —
 * `InvoiceSettlement` tetap satu-satunya pintu menuju `active`, dan sebuah
 * driver yang bisa melunasi sendiri akan membuat tiap penyedia baru menjadi
 * jalur uang baru.
 *
 * Konsekuensi yang diharapkan: memasang Sumopod (`[BL-060]`) berarti menulis
 * satu kelas yang mengisi kontrak ini, tanpa menyentuh controller, halaman,
 * maupun tabel.
 */
interface PaymentGateway
{
    /** Nama pendek penyedia; ikut tertulis di `payment_attempts.gateway`. */
    public function key(): string;

    /**
     * Nilai `settled_via` yang dipakai ketika pembayarannya melunasi tagihan.
     *
     * Dipisah per penyedia, bukan satu nilai `gateway` untuk semua: pelunasan
     * dari gateway tiruan tidak boleh bisa menyamar sebagai uang sungguhan di
     * laporan pendapatan mana pun.
     */
    public function settlementSource(): string;

    /**
     * Kanal pembayaran yang benar-benar bisa dipakai di akun ini.
     *
     * @return list<array{code: string, label: string, hint: string}>
     */
    public function availableChannels(): array;

    /**
     * Terbitkan tagihan ke penyedia dan simpan instruksinya.
     */
    public function createCharge(Invoice $invoice, string $channel): PaymentAttempt;

    /**
     * Baca notifikasi penyedia, setelah memastikan ia memang datang darinya.
     *
     * @throws InvalidCallbackSignature bila tanda tangannya tidak cocok
     */
    public function verifyCallback(Request $request): CallbackResult;
}
