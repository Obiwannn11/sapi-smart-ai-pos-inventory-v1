<?php

namespace App\Http\Resources\Platform;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Bentuk tenant sebagaimana boleh dilihat pemilik SaaS.
 *
 * DAFTAR PUTIH, bukan daftar hitam: field ditulis satu per satu dan sengaja
 * TIDAK memakai parent::toArray(). Kalau nanti tabel `tenants` bertambah kolom
 * (mis. kunci API AI), kolom itu tidak ikut bocor dengan sendirinya — harus
 * ditambahkan sadar-sadar di sini.
 *
 * Yang HARAM masuk ke sini: omset, laba, isi transaksi, katalog produk, stok,
 * dan `ai_api_key`. Untuk tenant jalur subsidi, omset akan datang dari tabel
 * ringkasan terpisah di Tahap C — bukan dari model operasional.
 *
 * @property-read \App\Models\Tenant $resource
 */
class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'slug' => $this->slug,
            'registered_at' => $this->created_at?->toDateString(),
            'user_count' => $this->users_count,
            // Penanda administratif, bukan data bisnis: apakah pendaftarnya
            // sudah membuktikan alamat surelnya, dan apakah pola pendaftarannya
            // perlu ditinjau. Keduanya soal keabsahan akun, bukan soal isi
            // usahanya.
            'is_verified' => $this->whenLoaded('owners', fn () => $this->owners->first()?->hasVerifiedEmail() ?? false),
            'flagged_at' => $this->flagged_at?->toDateString(),
            'flag_reason' => $this->flag_reason,
            'owner' => $this->whenLoaded('owners', fn () => $this->owners->map(fn ($owner) => [
                'name' => $owner->name,
                'email' => $owner->email,
            ])->first()),
        ];
    }
}
