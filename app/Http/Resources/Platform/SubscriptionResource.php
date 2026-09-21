<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk langganan sebagaimana boleh dilihat pemilik SaaS.
 *
 * DAFTAR PUTIH, mengikuti alasan yang sama seperti TenantResource: kolom baru
 * di `subscriptions` tidak boleh ikut terkirim dengan sendirinya.
 *
 * Semua yang ada di sini adalah keterangan KOMERSIAL — paket, tarif, seat,
 * periode. Tidak satu pun berasal dari data operasional tenant. Omset jalur
 * subsidi menyusul di Tahap C dan akan datang dari tabel ringkasan tersendiri,
 * bukan dari `transactions`.
 *
 * @property-read \App\Models\Subscription $resource
 */
class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'tenant' => $this->whenLoaded('tenant', fn () => [
                'id' => $this->tenant->id,
                'name' => $this->tenant->name,
                'status' => $this->tenant->status,
            ]),
            'plan_name' => $this->whenLoaded('plan', fn () => $this->plan->name),
            'pricing_track' => $this->pricing_track,
            'seats' => $this->seats,
            'seat_high_water' => $this->seat_high_water,
            'price_locked' => $this->price_locked === null ? null : (float) $this->price_locked,
            'trial_ends_at' => $this->trial_ends_at?->toDateString(),
            'current_period_end' => $this->current_period_end?->toDateString(),
        ];
    }
}
