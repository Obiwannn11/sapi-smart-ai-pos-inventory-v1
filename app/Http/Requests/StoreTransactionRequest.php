<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            // Items
            'items'                           => 'required|array|min:1',
            'items.*.variant_id'              => 'required|exists:product_variants,id',
            'items.*.variant_name'            => 'required|string|max:255',
            'items.*.qty'                     => 'required|integer|min:1',
            'items.*.unit_price'              => 'required|numeric|min:0',
            'items.*.modifiers'               => 'nullable|array',
            'items.*.modifiers.*.id'          => 'required|exists:modifiers,id',
            'items.*.modifiers.*.name'        => 'required|string|max:255',
            'items.*.modifiers.*.extra_price' => 'required|numeric|min:0',

            // Payments
            'payments'                        => 'required|array|min:1',
            'payments.*.payment_method_id'    => 'required|exists:payment_methods,id',
            'payments.*.amount'               => 'required|numeric|min:0',
            'payments.*.reference_code'       => 'nullable|string|max:255',

            // Notes
            'notes' => 'nullable|string|max:1000',
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
            $totalBelanja = 0;
            foreach ($this->input('items', []) as $item) {
                $itemTotal = ($item['unit_price'] ?? 0) * ($item['qty'] ?? 0);
                if (!empty($item['modifiers'])) {
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
