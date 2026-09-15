<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use App\Services\StockBatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $variant = $this->route('variant');

        return [
            'qty' => 'required|integer|not_in:0',
            'notes' => 'required|string|max:500',
            // Batch sasaran ([BL-111]), dan hanya milik varian yang dikoreksi:
            // batch varian lain di sini berarti mengurangi stok barang lain
            // lewat halaman barang ini. Kosong = urutan otomatis.
            'batch_id' => [
                'nullable',
                'integer',
                Rule::exists('product_stock_batches', 'id')
                    ->where('product_variant_id', $variant instanceof ProductVariant ? $variant->id : 0),
            ],
            'new_batch' => 'nullable|boolean',
            'expiry_date' => 'nullable|date',
            'no_expiry' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.not_in' => 'Jumlah adjustment tidak boleh 0.',
            'notes.required' => 'Alasan adjustment wajib diisi.',
            'batch_id.exists' => 'Batch yang dipilih bukan milik varian ini.',
        ];
    }

    /**
     * Batch baru hanya untuk koreksi naik, dan mengikuti aturan tanggal yang
     * sama dengan restock: varian yang pernah bertanggal menuntut tanggal, atau
     * pernyataan bahwa batch ini memang tidak bertanggal.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if (! $this->boolean('new_batch')) {
                    return;
                }

                if ((int) $this->input('qty') < 0) {
                    $validator->errors()->add('qty', 'Batch baru hanya untuk menambah stok.');
                }

                if (filled($this->input('batch_id'))) {
                    $validator->errors()->add('batch_id', 'Pilih batch yang ada atau batch baru, tidak keduanya.');
                }

                $variant = $this->route('variant');

                if (
                    $variant instanceof ProductVariant
                    && blank($this->input('expiry_date'))
                    && ! $this->boolean('no_expiry')
                    && app(StockBatchService::class)->tracksExpiry($variant)
                ) {
                    $validator->errors()->add(
                        'expiry_date',
                        'Varian ini punya tanggal kedaluwarsa. Isi tanggal batch baru, atau centang "Batch ini tidak punya tanggal kedaluwarsa".',
                    );
                }
            },
        ];
    }
}
