<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'              => 'required|string|max:255',
            'category_id'       => 'nullable|exists:categories,id',
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120', // 5 MB
            'is_active'         => 'boolean',
            'modifier_group_ids' => 'nullable|array',
            'modifier_group_ids.*' => 'exists:modifier_groups,id',

            // Variants (minimal 1 wajib saat create)
            'variants'              => 'required|array|min:1',
            'variants.*.name'       => 'required|string|max:255',
            'variants.*.sku'        => 'nullable|string|max:100',
            'variants.*.price'      => 'required|numeric|min:0',
            'variants.*.cost_price' => 'required|numeric|min:0',
            'variants.*.stock'      => 'required|integer|min:0',
            'variants.*.expiry_date' => 'nullable|date',
        ];
    }

    public function messages(): array
    {
        return [
            'variants.required' => 'Minimal 1 varian produk harus diisi.',
            'variants.*.price.required' => 'Harga jual wajib diisi.',
            'variants.*.cost_price.required' => 'Harga modal wajib diisi.',
            'image.max' => 'Ukuran gambar maksimal 5 MB.',
        ];
    }
}
