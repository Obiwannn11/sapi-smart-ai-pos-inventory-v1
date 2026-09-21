<?php

namespace App\Http\Requests;

use App\Models\DiscountRule;
use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Aturan diskon buatan owner ([BL-018]).
 *
 * Dipakai untuk tambah DAN sunting: bentuk yang divalidasi sama persis, dan
 * dua kelas yang identik adalah dua kelas yang suatu saat akan berbeda di satu
 * baris tanpa ada yang menyadarinya.
 */
class StoreDiscountRuleRequest extends FormRequest
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
            // Kepemilikan varian lewat produknya: ProductVariant tidak memakai
            // BelongsToTenant, jadi tidak ada scope global yang menolong.
            'product_variant_id' => [
                'required',
                Rule::exists('product_variants', 'id')->where(
                    fn ($query) => $query->whereIn('product_id', Product::where('tenant_id', $tenantId)->select('id'))
                ),
            ],
            'trigger' => ['required', Rule::in(DiscountRule::triggers())],

            // Batas atas 90%, bukan 100: potongan yang membuat harga jadi nol
            // bukan diskon, itu pemberian — dan jalurnya bukan di sini.
            'percent' => ['required', 'numeric', 'min:1', 'max:90'],
            'max_percent' => ['nullable', 'numeric', 'min:1', 'max:90', 'gte:percent'],

            // Wajib. Potongan tanpa alasan adalah potongan yang tidak bisa
            // dipertanggungjawabkan saat laporannya dibuka berbulan kemudian.
            'reason' => ['required', 'string', 'max:120'],

            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // `max_percent` hanya berarti untuk pemicu yang MENDALAM seiring
            // waktu. Menerimanya diam-diam pada pemicu lain akan membuat owner
            // mengira potongannya membesar padahal tidak.
            if ($this->filled('max_percent')
                && $this->input('trigger') !== DiscountRule::TRIGGER_NEAR_EXPIRY) {
                $validator->errors()->add(
                    'max_percent',
                    'Potongan terdalam hanya berlaku untuk pemicu "mendekati kedaluwarsa".',
                );
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Tulis alasan diskonnya — ia ikut tercatat pada tiap penjualan.',
            'max_percent.gte' => 'Potongan terdalam tidak boleh lebih kecil dari potongan awal.',
            'ends_on.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
        ];
    }
}
