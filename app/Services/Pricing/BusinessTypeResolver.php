<?php

namespace App\Services\Pricing;

use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * Tipe usaha tenant — dimensi bertipe atribut.
 *
 * Nilainya tidak dihitung dari apa pun; ia ditanyakan saat pendaftaran dan
 * bisa dikoreksi pemilik SaaS dari panel. Dua akibatnya perlu disadari:
 *
 * 1. Tenant yang mendaftar sebelum pertanyaannya ada mengembalikan `null`, dan
 *    aturan yang menyebut dimensi ini tidak akan cocok untuk mereka. Itu
 *    perilaku yang diinginkan — menebaknya dari nama usaha akan menghasilkan
 *    dasar harga yang salah tanpa ada yang menyadari.
 * 2. `$asOf` DIABAIKAN, dan ini keterbatasan yang disadari: kolomnya menyimpan
 *    nilai sekarang tanpa riwayat, jadi tenant yang mengubah tipe usahanya akan
 *    membuat perhitungan ulang periode lama memakai tipe yang baru. Untuk
 *    sekarang itu bisa ditoleransi karena tipe usaha nyaris tak pernah berubah,
 *    dan setiap tagihan sudah membekukan nilainya di `invoices.pricing_context`
 *    — jadi jawaban "kenapa harganya segini" tetap tersimpan meski nilainya
 *    kemudian berubah.
 */
class BusinessTypeResolver implements DimensionResolver
{
    public function resolve(Tenant $tenant, ?Carbon $asOf = null): float|string|null
    {
        return $tenant->business_type;
    }
}
