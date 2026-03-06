<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = auth()->user()->tenant_id;

        return [
            'name'              => 'required|string|max:255',
            'category_id'       => ['nullable', Rule::exists('categories', 'id')->where('tenant_id', $tenantId)],
            'image'             => 'nullable|image|mimes:jpg,jpeg,png,webp|max:5120',
            'is_active'         => 'boolean',
            'modifier_group_ids' => 'nullable|array',
            'modifier_group_ids.*' => Rule::exists('modifier_groups', 'id')->where('tenant_id', $tenantId),
        ];
    }
}
