<?php

namespace App\Services\Ai;

use App\Models\ProductVariant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;

/**
 * Memetakan nama varian yang disebut hasil analisis ke barangnya di katalog
 * ([BL-100] tahap 2).
 *
 * Pemetaannya dikerjakan DI SERVER dan bukan diserahkan ke model. Menyuruh
 * model menulis markdown link sendiri terdengar lebih murah, tapi ia akan
 * mengarang tujuan untuk nama yang tidak ada — dan tautan mati yang terlihat
 * sah lebih buruk daripada teks biasa.
 *
 * TIGA keluaran, bukan dua, dan yang ketiga adalah keputusan pemilik
 * 2026-09-06:
 *
 *   `linked`   tepat satu varian bernama itu di katalog toko ini. Ditautkan.
 *   `missing`  tidak ada satu pun. Barangnya pernah dijual — namanya datang
 *              dari konteks yang dirakit dari `transaction_items` — jadi ia
 *              DITANDAI, bukan didiamkan: "produk ini sudah tidak ada di
 *              katalog" justru sering informasi yang dicari owner.
 *   (kosong)   lebih dari satu varian bernama sama. TIDAK masuk peta sama
 *              sekali dan tetap tampil sebagai teks biasa. Memilih salah satu
 *              diam-diam berarti mengantar owner ke barang yang keliru, dan ia
 *              tidak punya cara tahu.
 *
 * Yang menjaga `missing` tetap jujur ada di luar kelas ini: pemanggilnya hanya
 * boleh menyodorkan nama yang benar-benar ada di `context_variants` analisis
 * itu. Nama karangan model juga tidak punya varian aktif, dan menandainya
 * "sudah dihapus" akan mengubah halusinasi jadi pernyataan yang terlihat
 * berwenang.
 */
class VariantLinkResolver
{
    public const STATE_LINKED = 'linked';

    public const STATE_MISSING = 'missing';

    /**
     * @param  list<string>  $names
     * @return array<string, array{state: string, variant_id?: int, product_id?: int}>
     */
    public function resolve(Tenant $tenant, array $names): array
    {
        $names = array_values(array_unique(array_filter(
            array_map(fn ($name) => is_string($name) ? trim($name) : '', $names),
            fn (string $name) => $name !== '',
        )));

        if ($names === []) {
            return [];
        }

        // Lewat `whereHas('product')`, sama seperti `BadgeHelperService`:
        // `ProductVariant` tidak punya `tenant_id`, jadi batas tenantnya
        // datang dari produknya. `$tenant->id` ditulis eksplisit dan tidak
        // menggantungkan diri pada TenantScope — kelas ini dipanggil dari
        // controller hari ini, tapi tidak ada yang menghalanginya dipanggil
        // dari job besok, dan di sana `auth()` kosong.
        $variants = ProductVariant::query()
            ->whereHas('product', fn (Builder $query) => $query->where('tenant_id', $tenant->id))
            ->whereIn('name', $names)
            ->get(['id', 'product_id', 'name']);

        $byName = [];

        foreach ($variants as $variant) {
            $byName[$variant->name][] = $variant;
        }

        $map = [];

        foreach ($names as $name) {
            $matches = $byName[$name] ?? [];

            // Produk nonaktif TETAP ditautkan. Yang ditandai `missing` adalah
            // barang yang sudah tidak ada, bukan yang sedang dimatikan —
            // katalog owner menampilkan keduanya dan punya penyaring statusnya
            // sendiri, jadi tautannya tetap mendarat di tempat yang benar.
            if (count($matches) === 1) {
                $map[$name] = [
                    'state' => self::STATE_LINKED,
                    'variant_id' => $matches[0]->id,
                    'product_id' => $matches[0]->product_id,
                ];

                continue;
            }

            if ($matches === []) {
                $map[$name] = ['state' => self::STATE_MISSING];
            }
        }

        return $map;
    }
}
