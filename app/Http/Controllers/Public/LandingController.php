<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Services\Pricing\PublicPricing;

class LandingController extends Controller
{
    /**
     * Display the single-page landing page for SAPI.
     *
     * Harganya dibacakan dari `plans` dan `pricing_rules`, tidak diketik di
     * Blade. Sebelum ini bagian harga memajang dua paket yang tidak pernah ada
     * di sistem — "Core POS Rp 149k" dan "Smart SAPI Rp 299k" — sehingga calon
     * klien yang mendaftar setelah membacanya akan mendapati tagihan yang sama
     * sekali lain (`[BL-032]` butir 2).
     *
     * @return \Illuminate\View\View
     */
    public function index(PublicPricing $pricing)
    {
        return view('public.landing', [
            'pricing' => $pricing->snapshot(),
        ]);
    }

    public function docs()
    {
        return view('public.api-docs');
    }
}
