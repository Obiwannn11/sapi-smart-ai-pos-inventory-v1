<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/**
 * Aturan saran jual buatan owner ([BL-074]).
 *
 * Dipakai untuk tambah DAN sunting: bentuk yang divalidasi sama persis, dan
 * dua kelas yang identik adalah dua kelas yang suatu saat akan berbeda di satu
 * baris tanpa ada yang menyadarinya.
 */
class StoreUpsellRuleRequest extends FormRequest
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

        // Kepemilikan varian diperiksa lewat produknya: ProductVariant tidak
        // memakai BelongsToTenant, jadi tidak ada scope global yang menolong.
        // Pola yang sama dipakai StoreTransactionRequest dan SellableVariantQuery.
        $ownedVariant = fn () => Rule::exists('product_variants', 'id')->where(
            fn ($query) => $query->whereIn('product_id', Product::where('tenant_id', $tenantId)->select('id'))
        );

        return [
            // Nullable = aturan tanpa pemicu, ditawarkan pada setiap keranjang.
            'trigger_variant_id' => ['nullable', $ownedVariant()],
            'suggested_variant_id' => ['required', $ownedVariant()],

            // Panjangnya mengikuti lebar kolom, bukan angka yang dikarang:
            // batas yang lebih longgar dari kolomnya hanya memindahkan
            // kegagalan dari validasi ke driver database.
            'note' => ['nullable', 'string', 'max:120'],

            // `starts_on` boleh di masa depan — pemilik memintanya bisa
            // disetel dari jauh hari.
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],

            'priority' => ['nullable', 'integer', 'min:0', 'max:999'],
            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            // Menawarkan barang yang sama dengan pemicunya bukan saran, itu
            // pengulangan — dan di layar kasir ia tampil sebagai "beli Teh
            // Manis, tawarkan Teh Manis".
            if ($this->input('trigger_variant_id')
                && (int) $this->input('trigger_variant_id') === (int) $this->input('suggested_variant_id')) {
                $validator->errors()->add(
                    'suggested_variant_id',
                    'Barang yang disarankan tidak boleh sama dengan pemicunya.',
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
            'suggested_variant_id.required' => 'Pilih barang yang ingin disarankan.',
            'ends_on.after_or_equal' => 'Tanggal berakhir tidak boleh sebelum tanggal mulai.',
        ];
    }
}
