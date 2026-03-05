<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StorePaymentMethodRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:255',
            'type'      => 'required|in:cash,qris_static,qris_dynamic,bank_transfer',
            'is_active' => 'boolean',
        ];
    }
}
