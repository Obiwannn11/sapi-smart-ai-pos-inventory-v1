<?php

namespace App\Http\Requests;

use App\Models\ModifierGroup;
use App\Models\Product;
use App\Models\Transaction;
use App\Services\PaymentProofService;
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

            // Token, bukan berkasnya — lihat PaymentProofService. Jalur KEEMPAT
            // pembuat baris pembayaran, dan yang paling lama tidak punya kamera
            // ([BL-075]).
            'payments.*.proof_token' => 'nullable|uuid',

            // Meta
            'notes' => 'nullable|string|max:1000',
            'reason' => 'nullable|string|max:255',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $tenant = Auth::user()?->tenant;

            $missing = app(PaymentProofService::class)->missingProofs(
                $this->input('payments', []),
                $tenant,
                $this->exemptMethodIds($tenant?->id),
            );

            foreach ($missing as $index => $message) {
                $validator->errors()->add("payments.{$index}.proof_token", $message);
            }
        });
    }

    /**
     * Metode yang sudah ada pada transaksi ini sebelum diedit — mereka tidak
     * dituntut foto baru. Alasannya ditulis di PaymentProofService.
     *
     * Diambil dari BASIS DATA, bukan dari kiriman client: daftar yang boleh
     * dikarang pihak yang sedang dijaga bukan penjaga.
     *
     * @return array<int, int>
     */
    private function exemptMethodIds(?int $tenantId): array
    {
        $transaction = $this->route('transaction');

        if (! $transaction instanceof Transaction || $transaction->tenant_id !== $tenantId) {
            return [];
        }

        return $transaction->payments()->pluck('payment_method_id')->all();
    }

    public function messages(): array
    {
        return [
            'items.required' => 'Minimal 1 item harus ada di transaksi.',
            'payments.required' => 'Metode pembayaran harus dipilih.',
        ];
    }
}
