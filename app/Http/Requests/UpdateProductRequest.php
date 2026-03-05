<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
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
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_active'         => 'boolean',
            'modifier_group_ids' => 'nullable|array',
            'modifier_group_ids.*' => 'exists:modifier_groups,id',
        ];
    }
}
