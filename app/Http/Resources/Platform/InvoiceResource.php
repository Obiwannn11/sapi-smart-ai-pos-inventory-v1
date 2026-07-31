<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk tagihan sebagaimana boleh dilihat pemilik SaaS.
 *
 * DAFTAR PUTIH. Nominal tagihan memang boleh terlihat — itu harga yang kita
 * tetapkan sendiri, bukan omset tenant. Keduanya mudah tertukar saat membaca
 * layar, jadi ditulis di sini terang-terangan: angka di halaman ini adalah
 * TAGIHAN, bukan pendapatan tenant.
 *
 * @property-read \App\Models\Invoice $resource
 */
class InvoiceResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'period' => $this->period,
            // Tagihan langganan berkala dan tagihan penambahan pengguna terlihat
            // sama begitu keduanya berbaris di satu daftar riwayat — hanya
            // nominalnya yang berbeda, dan itu tidak cukup untuk membedakan
            // "iuran bulan ini" dari "tambah satu kasir".
            'kind' => $this->kind,
            'grants_seats' => $this->grants_seats,
            'previous_seats' => $this->previous_seats,
            'amount' => (float) $this->amount,
            'status' => $this->status,
            'due_date' => $this->due_date?->toDateString(),
            'has_proof' => $this->proof_path !== null,
            'submitted_at' => $this->submitted_at?->toDateString(),
            'paid_at' => $this->paid_at?->toDateString(),
            'verified_at' => $this->verified_at?->toDateString(),
            'rejection_reason' => $this->rejection_reason,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'status' => $this->tenant->status,
            ]),
            'verifier' => $this->whenLoaded('verifier', fn () => $this->verifier?->name),
        ];
    }
}
