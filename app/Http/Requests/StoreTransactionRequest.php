<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Models\UpsellEvent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    public function rules(): array
    {
        $user = Auth::user();

        if (! $user) {
            return [];
        }

        $tenantId = $user->tenant_id;

        return [
            // Items
            'items' => 'required|array|min:1',
            'items.*.variant_id' => [
                'required',
                Rule::exists('product_variants', 'id')->where(function ($q) use ($tenantId) {
                    $q->whereIn('product_id', Product::where('tenant_id', $tenantId)->pluck('id'));
                }),
            ],
            'items.*.variant_name' => 'required|string|max:255',
            'items.*.qty' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*.id' => [
                'required',
                Rule::exists('modifiers', 'id')->where(function ($q) use ($tenantId) {
                    $q->whereIn('modifier_group_id',
                        \App\Models\ModifierGroup::where('tenant_id', $tenantId)->pluck('id')
                    );
                }),
            ],
            'items.*.modifiers.*.name' => 'required|string|max:255',
            'items.*.modifiers.*.extra_price' => 'required|numeric|min:0',
            'items.*.notes' => 'nullable|string|max:500',

            // Payments (nullable for open bill)
            'payments' => 'nullable|array|min:1',
            'payments.*.payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId),
            ],
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference_code' => 'nullable|string|max:255',

            // Notes
            'notes' => 'nullable|string|max:1000',

            // Identitas pesanan — nama pelanggan / nomor meja.
            //
            // Keduanya `nullable` dan tidak pernah saling mewajibkan. Mode
            // identitas milik owner mengatur apa yang DITANYAKAN kasir, bukan
            // apa yang diterima server: kasir yang melewati modal identitas di
            // tengah antrean panjang tetap harus bisa menyelesaikan penjualan.
            // Prinsip yang sama sudah dipakai untuk `upsell_events` ([BL-026]).
            //
            // `max:10` mengikuti lebar kolomnya, bukan angka yang dikarang —
            // batas yang lebih longgar dari kolomnya hanya memindahkan
            // kegagalan dari validasi ke driver database.
            'customer_name' => 'nullable|string|max:100',
            'table_number' => 'nullable|string|max:10',

            // Open bill flag
            'is_open_bill' => 'nullable|boolean',

            // Idempotency key (client-generated per checkout)
            'client_uuid' => 'nullable|uuid',

            // Nasib saran upsell yang muncul pada keranjang ini.
            //
            // Sengaja TIDAK memakai Rule::exists: kepemilikan tenant diperiksa
            // di UpsellEventRecorder, yang menolkan FK asing alih-alih menolak
            // request. Statistik upsell tidak boleh punya kuasa menggagalkan
            // penjualan yang sah.
            'upsell_events' => 'nullable|array|max:20',
            'upsell_events.*.type' => 'required|string|in:'.implode(',', UpsellEvent::types()),
            'upsell_events.*.status' => 'required|string|in:'.implode(',', UpsellEvent::statuses()),
            'upsell_events.*.reason' => 'nullable|string|max:50',
            'upsell_events.*.label' => 'required|string|max:255',
            'upsell_events.*.extra_amount' => 'nullable|numeric|min:0',
            'upsell_events.*.trigger_variant_id' => 'nullable|integer',
            'upsell_events.*.suggested_variant_id' => 'nullable|integer',
            'upsell_events.*.suggested_modifier_id' => 'nullable|integer',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal 1 item harus ada di transaksi.',
            'payments.required' => 'Metode pembayaran harus dipilih.',
        ];
    }

    /**
     * Validasi tambahan: total bayar >= total belanja.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Skip payment validation for open bill
            if ($this->boolean('is_open_bill')) {
                return;
            }

            // Payments required for non-open-bill
            if (empty($this->input('payments'))) {
                $validator->errors()->add('payments', 'Metode pembayaran harus dipilih.');

                return;
            }

            $totalBelanja = 0;
            foreach ($this->input('items', []) as $item) {
                $itemTotal = ($item['unit_price'] ?? 0) * ($item['qty'] ?? 0);
                if (! empty($item['modifiers'])) {
                    $modifierExtra = collect($item['modifiers'])->sum('extra_price');
                    $itemTotal += $modifierExtra * ($item['qty'] ?? 0);
                }
                $totalBelanja += $itemTotal;
            }

            $totalBayar = collect($this->input('payments', []))->sum('amount');

            if ($totalBayar < $totalBelanja) {
                $validator->errors()->add('payments', 'Total pembayaran kurang dari total belanja.');
            }
        });
    }
}
