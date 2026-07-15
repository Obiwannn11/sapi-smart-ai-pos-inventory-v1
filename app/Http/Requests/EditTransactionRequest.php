<?php

namespace App\Http\Requests;

use App\Models\ModifierGroup;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class EditTransactionRequest extends FormRequest
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
            // Items — harga & nama otoritatif dari DB, client hanya kirim variant + qty.
            'items' => 'required|array|min:1',
            'items.*.variant_id' => [
                'required',
                Rule::exists('product_variants', 'id')->where(function ($q) use ($tenantId) {
                    $q->whereIn('product_id', Product::where('tenant_id', $tenantId)->pluck('id'));
                }),
            ],
            'items.*.qty' => 'required|integer|min:1',
            'items.*.notes' => 'nullable|string|max:500',
            'items.*.modifiers' => 'nullable|array',
            'items.*.modifiers.*.id' => [
                'required',
                Rule::exists('modifiers', 'id')->where(function ($q) use ($tenantId) {
                    $q->whereIn('modifier_group_id', ModifierGroup::where('tenant_id', $tenantId)->pluck('id'));
                }),
            ],

            // Payments
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId),
            ],
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference_code' => 'nullable|string|max:255',

            // Meta
            'notes' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal 1 item harus ada di transaksi.',
            'payments.required' => 'Metode pembayaran harus dipilih.',
        ];
    }
}
