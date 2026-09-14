<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SyncOfflineTransactionsRequest extends FormRequest
{
    /**
     * Batas item per batch — menjaga satu request tetap dalam batas waktu
     * eksekusi. Client memecah antrean panjang menjadi beberapa flush.
     */
    public const MAX_BATCH_SIZE = 50;

    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * Validasi BENTUK payload saja — sengaja tidak memakai Rule::exists di sini.
     *
     * Berbeda dari StoreTransactionRequest (yang boleh menolak seluruh request),
     * batch sync harus tahan "poison pill": satu transaksi antre yang merujuk
     * variant terhapus tidak boleh mem-422-kan seluruh batch, karena itu akan
     * membuat antrean macet selamanya dan penjualan lain ikut tertahan.
     *
     * Kepemilikan tenant, cash-only, dan kewajaran occurred_at divalidasi
     * PER ITEM di TransactionService::commitOffline(), sehingga item bermasalah
     * gagal sendirian dan sisanya tetap tersimpan.
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'transactions' => 'required|array|min:1|max:'.self::MAX_BATCH_SIZE,
            'transactions.*.client_uuid' => 'required|uuid',
            'transactions.*.occurred_at' => 'required|date',
            'transactions.*.device_id' => 'nullable|string|max:64',
            'transactions.*.total_amount' => 'nullable|numeric|min:0',
            'transactions.*.notes' => 'nullable|string|max:1000',
            'transactions.*.customer_name' => 'nullable|string|max:100',
            'transactions.*.table_number' => 'nullable|string|max:10',

            'transactions.*.items' => 'required|array|min:1',
            'transactions.*.items.*.variant_id' => 'required|integer',
            'transactions.*.items.*.variant_name' => 'required|string|max:255',
            'transactions.*.items.*.qty' => 'required|integer|min:1',
            'transactions.*.items.*.unit_price' => 'required|numeric|min:0',
            'transactions.*.items.*.notes' => 'nullable|string|max:500',
            // Alasan kasir menjual barang basi saat offline ([BL-108]). Tanpanya
            // penjualan tetap tersimpan, tapi ditandai untuk ditinjau owner.
            'transactions.*.items.*.expired_confirmation_reason' => 'nullable|string|max:200',
            'transactions.*.items.*.modifiers' => 'nullable|array',
            'transactions.*.items.*.modifiers.*.id' => 'required|integer',
            'transactions.*.items.*.modifiers.*.name' => 'required|string|max:255',
            'transactions.*.items.*.modifiers.*.extra_price' => 'required|numeric|min:0',

            'transactions.*.payments' => 'required|array|min:1',
            'transactions.*.payments.*.payment_method_id' => 'required|integer',
            'transactions.*.payments.*.amount' => 'required|numeric|min:0',

            // Nasib saran upsell yang menumpang outbox. Bentuk saja, seperti
            // sisa payload ini — isinya disaring UpsellEventRecorder.
            'transactions.*.upsell_events' => 'nullable|array|max:20',
            'transactions.*.upsell_events.*.type' => 'required|string|max:30',
            'transactions.*.upsell_events.*.status' => 'required|string|max:20',
            'transactions.*.upsell_events.*.reason' => 'nullable|string|max:50',
            'transactions.*.upsell_events.*.label' => 'required|string|max:255',
            'transactions.*.upsell_events.*.extra_amount' => 'nullable|numeric|min:0',
            'transactions.*.upsell_events.*.trigger_variant_id' => 'nullable|integer',
            'transactions.*.upsell_events.*.suggested_variant_id' => 'nullable|integer',
            'transactions.*.upsell_events.*.suggested_modifier_id' => 'nullable|integer',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'transactions.required' => 'Tidak ada transaksi untuk disinkronkan.',
            'transactions.max' => 'Terlalu banyak transaksi dalam satu batch.',
            'transactions.*.client_uuid.required' => 'Setiap transaksi offline wajib punya client_uuid.',
            'transactions.*.occurred_at.required' => 'Setiap transaksi offline wajib punya waktu kejadian.',
            'transactions.*.payments.required' => 'Transaksi offline wajib menyertakan pembayaran tunai.',
        ];
    }
}
