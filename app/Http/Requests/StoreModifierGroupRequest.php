<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreModifierGroupRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'        => 'required|string|max:255',
            'is_required' => 'boolean',
            'is_multiple' => 'boolean',
            'modifiers'            => 'required|array|min:1',
            'modifiers.*.id'       => 'nullable|integer',
            'modifiers.*.name'     => 'required|string|max:255',
            'modifiers.*.extra_price' => 'required|numeric|min:0',
        ];
    }

    public function messages(): array
    {
        return [
            'modifiers.required' => 'Minimal 1 modifier harus diisi.',
        ];
    }
}
