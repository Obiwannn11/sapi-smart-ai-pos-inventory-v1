<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Ubah varian yang sudah ada: nama, SKU, dan harga saja.
 *
 * `stock` dan `expiry_date` sengaja tidak ada di sini, berbeda dari
 * `StoreVariantRequest` ([BL-111]). Keduanya hidup di batch dan hanya berubah
 * lewat restock atau adjustment di halaman Stok, supaya setiap perubahan
 * tercatat. Nilai yang tetap dikirim klien lama diabaikan, karena
 * `validated()` tidak memuatnya.
 */
class UpdateVariantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, string>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'required|numeric|min:0',
        ];
    }
}
