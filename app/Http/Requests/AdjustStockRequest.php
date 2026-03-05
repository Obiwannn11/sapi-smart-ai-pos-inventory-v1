<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty'   => 'required|integer|not_in:0',
            'notes' => 'required|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.not_in' => 'Jumlah adjustment tidak boleh 0.',
            'notes.required' => 'Alasan adjustment wajib diisi.',
        ];
    }
}
