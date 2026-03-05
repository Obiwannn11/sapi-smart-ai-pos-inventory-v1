<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty'         => 'required|integer|min:1',
            'notes'       => 'nullable|string|max:500',
            'expiry_date' => 'nullable|date|after_or_equal:today',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.min' => 'Jumlah restock minimal 1.',
            'expiry_date.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh di masa lalu.',
        ];
    }
}
