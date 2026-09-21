<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Sumber angka bagi satu dimensi harga.
 *
 * Implementasinya sengaja tidak tahu apa pun soal aturan, operator, maupun
 * tarif: tugasnya hanya menjawab "berapa nilai dimensi ini bagi tenant ini".
 * Pemisahan itu yang membuat menambah kategori harga baru berarti menambah satu
 * kelas kecil, bukan menyunting mesin pencocokannya.
 */
interface DimensionResolver
{
    /**
     * Nilai dimensi bagi tenant, atau `null` bila tidak bisa ditentukan.
     *
     * `null` BUKAN nol dan bukan "tidak apa-apa". Ia berarti angkanya tidak
     * diketahui — belum pernah dihitung, atau tidak boleh dibaca — dan syarat
     * apa pun yang menyebut dimensi ini akan gugur karenanya. Mengembalikan 0
     * sebagai ganti null akan membuat tenant yang datanya belum ada tampak
     * seperti tenant beromzet nol, lalu jatuh ke bracket termurah.
     *
     * `$asOf` ada demi grandfathering: menghitung ulang periode lama harus
     * memakai nilai yang berlaku SAAT ITU. Dimensi yang tidak punya riwayat
     * (mis. tipe usaha) boleh mengabaikannya, dan itu keterbatasan yang
     * disadari — lihat catatan di kelas masing-masing.
     */
    public function resolve(Tenant $tenant, ?Carbon $asOf = null): float|string|null;
}
