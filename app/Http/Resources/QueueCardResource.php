<?php

namespace App\Http\Resources;

use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Satu kartu di papan antrian.
 *
 * Daftar putih, bukan `parent::toArray()`. Model `Transaction` membawa
 * `total_amount`, `user_id`, dan seluruh kolom sinkronisasi offline ke layar
 * yang tidak membutuhkannya — papan hanya perlu apa yang dilihat operator.
 *
 * @mixin Transaction
 */
class QueueCardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // Identitas kartu adalah id/code. `queue_number` hanya label
            // panggil — jangan dipakai sebagai kunci di sisi klien.
            'id' => $this->id,
            'code' => $this->code,
            'queue_number' => $this->queue_number,

            'fulfillment_status' => $this->fulfillment_status,
            'source' => $this->source,
            'order_type' => $this->order_type,
            'customer_name' => $this->customer_name,
            'table_number' => $this->table_number,
            'notes' => $this->notes,

            // Menyalakan penanda BELUM BAYAR. Open bill masuk papan dengan
            // `status = pending`, dan operator tunggal yang merangkap masak dan
            // kasir bisa menandainya selesai tanpa pernah menagih.
            'is_paid' => $this->status === Transaction::STATUS_COMPLETED,
            'amount_due' => $this->status === Transaction::STATUS_COMPLETED
                ? 0
                : (float) $this->total_amount,

            // Dasar timer "menunggu 7m". Dikirim sebagai ISO supaya klien yang
            // menghitung, bukan server — papan di-poll, dan angka yang dihitung
            // server akan membeku di antara dua polling.
            'waiting_since' => $this->effectiveDate()->toIso8601String(),
            'preparing_at' => $this->preparing_at?->toIso8601String(),
            'ready_at' => $this->ready_at?->toIso8601String(),

            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn ($item) => [
                'id' => $item->id,
                'name' => $item->variant_name,
                'qty' => $item->qty,
                'notes' => $item->notes,
                'modifiers' => $item->relationLoaded('modifiers')
                    ? $item->modifiers->map(fn ($m) => ['id' => $m->id, 'name' => $m->modifier_name])->all()
                    : [],
            ])->all()),
        ];
    }
}
