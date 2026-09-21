<?php

namespace App\Http\Requests;

use App\Models\ProductVariant;
use App\Services\StockBatchService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class RestockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'qty' => 'required|integer|min:1',
            'notes' => 'nullable|string|max:500',
            'expiry_date' => 'nullable|date|after_or_equal:today',
            // Pernyataan sadar bahwa kiriman ini memang tidak bertanggal. Hanya
            // berarti untuk varian yang pernah bertanggal; lihat after().
            'no_expiry' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'qty.min' => 'Jumlah restock minimal 1.',
            'expiry_date.after_or_equal' => 'Tanggal kedaluwarsa tidak boleh di masa lalu.',
        ];
    }

    /**
     * Varian yang pernah bertanggal wajib bertanggal lagi ([BL-111]).
     *
     * Sejak stok disimpan per batch, restock tanpa tanggal tidak lagi mewarisi
     * tanggal lama: ia melahirkan batch tanpa tanggal, yang dijual paling akhir
     * dan tidak pernah dianggap kedaluwarsa. Kolom yang boleh dikosongkan
     * dikosongkan orang yang sibuk ([BL-107]: 324 restock, 0 bertanggal), jadi
     * pengosongan harus jadi pernyataan (`no_expiry`), bukan kelalaian.
     *
     * Ditegakkan di sini, bukan hanya dengan tanda bintang di formulir.
     *
     * @return array<int, callable>
     */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                $variant = $this->route('variant');

                if (! $variant instanceof ProductVariant || filled($this->input('expiry_date')) || $this->boolean('no_expiry')) {
                    return;
                }

                if (app(StockBatchService::class)->tracksExpiry($variant)) {
                    $validator->errors()->add(
                        'expiry_date',
                        'Varian ini punya tanggal kedaluwarsa. Isi tanggalnya, atau centang "Kiriman ini tidak punya tanggal kedaluwarsa".',
                    );
                }
            },
        ];
    }
}
