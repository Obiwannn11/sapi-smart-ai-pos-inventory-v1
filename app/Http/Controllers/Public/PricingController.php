<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Pricing\PublicPricing;
use Illuminate\View\View;

/**
 * Halaman harga publik.
 *
 * Menjawab `[BL-041]`(c): sampai sekarang tidak ada satu pun permukaan tanpa
 * sesi yang membacakan aturan tarif yang benar-benar berlaku. Mesin "kelas
 * sesuai omzet" sudah berjalan sejak lama dan tangganya sudah bisa dilihat
 * tenant di `/langganan/harga-adaptif` (`[BL-055]`) — yang belum ada adalah
 * halaman untuk orang yang belum mendaftar.
 *
 * Seluruh angkanya dari `PublicPricing`, yang membaca `plans` dan
 * `pricing_rules`. Tidak ada satu pun nominal yang diketik di Blade: halaman
 * harga yang bisa berbeda dari tagihan sungguhan adalah cacat terburuk yang
 * bisa dimiliki halaman harga.
 */
class PricingController extends Controller
{
    public function index(PublicPricing $pricing): View
    {
        return view('public.pricing', [
            'pricing' => $pricing->snapshot(),
        ]);
    }
}
