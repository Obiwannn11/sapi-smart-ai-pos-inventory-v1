<?php

namespace App\Http\Requests;

use App\Services\PaymentProofService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Pelunasan open bill.
 *
 * Dipindahkan dari validasi inline di POSController saat `[BL-075]` mendarat.
 * Alasannya bukan kerapian: open bill adalah jalur KEDUA yang membuat baris
 * pembayaran, dan kewajiban foto bukti bayar yang hanya dipasang di jalur
 * checkout akan menganga persis di tempat yang paling mudah tidak diuji —
 * meja yang memesan dulu lalu membayar QRIS saat pulang.
 */
class PayOpenBillRequest extends FormRequest
{
    public function authorize(): bool
    {
        return Auth::check();
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = Auth::user()?->tenant_id;

        return [
            'payments' => 'required|array|min:1',
            'payments.*.payment_method_id' => [
                'required',
                Rule::exists('payment_methods', 'id')->where('tenant_id', $tenantId),
            ],
            'payments.*.amount' => 'required|numeric|min:0',
            'payments.*.reference_code' => 'nullable|string|max:255',

            // Token, bukan berkasnya — lihat PaymentProofService.
            'payments.*.proof_token' => 'nullable|uuid',
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $missing = app(PaymentProofService::class)
                ->missingProofs($this->input('payments', []), Auth::user()?->tenant);

            foreach ($missing as $index => $message) {
                $validator->errors()->add("payments.{$index}.proof_token", $message);
            }
        });
    }
}
