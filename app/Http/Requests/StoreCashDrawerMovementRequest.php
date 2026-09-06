<?php

namespace App\Http\Requests;

use App\Models\CashDrawerMovement;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCashDrawerMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([CashDrawerMovement::TYPE_PAYOUT, CashDrawerMovement::TYPE_DEPOSIT])],
            // `gt:0` bukan `min:0`: mencatat pengeluaran nol rupiah tidak
            // pernah berarti apa pun, dan barisnya hanya akan mengotori daftar
            // yang dipakai pemilik menelusuri selisih.
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999'],
            // WAJIB, dan itu inti fiturnya ([BL-087]). Alasan tertulis adalah
            // satu-satunya hal yang membedakan pencatatan ini dari uang yang
            // hilang begitu saja — sama seperti `discount_reason` pada harga di
            // bawah lantai margin.
            'reason' => ['required', 'string', 'min:3', 'max:200'],
            // OPSIONAL, dan itu keputusannya ([BL-093]). Sebagian pengeluaran
            // memang tidak berstruk — parkir, tukar receh — dan mewajibkan
            // foto akan mengulang kesalahan yang alasan-wajib hindari dari
            // sisi lain: kasir yang tidak bisa mencatat tetap mengeluarkan
            // uangnya, dan selisihnya muncul tanpa keterangan apa pun.
            //
            // PDF tidak diterima, sama seperti bukti bayar kasir: ini foto
            // yang diambil di tempat, dan berkas yang tidak bisa dipratinjau
            // berarti bukti yang tidak pernah bisa diperiksa siapa pun.
            'proof' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'reason.required' => 'Alasan wajib diisi — tanpa itu, uangnya tercatat hilang begitu saja.',
            'amount.gt' => 'Nominal harus lebih dari nol.',
            'proof.image' => 'Lampiran harus berupa foto.',
            'proof.max' => 'Ukuran foto maksimal 8 MB.',
        ];
    }
}
