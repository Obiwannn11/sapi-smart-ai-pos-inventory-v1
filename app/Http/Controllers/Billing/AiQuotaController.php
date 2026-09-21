<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\AiBlockPrice;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pembelian dan pelepasan kuota AI tambahan oleh tenant — `[BL-069]`,
 * keputusan pemilik 2026-08-19.
 *
 * Berdiri sendiri, tidak menumpang di `UpgradeController`, meski alurnya
 * kembar: kelas itu menceritakan riwayat seat yang panjang dan spesifik —
 * tagihan sekali bayar yang dicabut, bukti transfer, tagihan `KIND_UPGRADE`
 * peninggalan yang masih harus dijaga. Kuota AI tidak punya satu pun dari itu,
 * dan menempelkannya ke sana akan membuat dua cerita berbeda dibaca sebagai
 * satu.
 *
 * Yang dijual adalah PLAFON HARIAN, bukan kredit habis-pakai: satu blok
 * menaikkan jatah analisis per hari selama blok itu dimiliki, dan tagihannya
 * berulang tiap bulan. Konsekuensinya disebut apa adanya di kalimat yang
 * dikembalikan kedua metode di bawah — sebagian pembeli akan membayar sebulan
 * penuh untuk kapasitas yang mereka pakai beberapa hari saja, dan itu keputusan
 * yang diambil sadar, bukan yang boleh disembunyikan dari yang membayarnya.
 */
class AiQuotaController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * Beli blok kuota AI. Berlaku sekarang, tertagih mulai periode berikutnya.
     */
    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'blocks' => ['required', 'integer', 'min:1'],
        ]);

        $tenant = $request->user()->tenant;
        $subscription = $this->subscriptions->ensureFor($tenant);
        $ceiling = $this->subscriptions->aiQuotaPurchaseCeiling($subscription);

        // Menolak dengan kalimat, bukan diam-diam memotong angkanya: tenant yang
        // mengetik 5 lalu mendapat 2 tanpa diberi tahu akan mengira sisanya
        // hilang, dan tagihan bulan depan tidak akan cocok dengan ingatannya.
        if ($ceiling < 1) {
            return back()->with('error', sprintf(
                'Anda sudah di batas maksimum %d blok kuota AI. Hubungi kami kalau memang butuh lebih.',
                (int) config('subscription.ai_quota.max_blocks'),
            ));
        }

        if ($validated['blocks'] > $ceiling) {
            return back()->with('error', sprintf(
                'Paling banyak %d blok lagi yang bisa dibeli sekarang — batas maksimumnya %d blok.',
                $ceiling,
                (int) config('subscription.ai_quota.max_blocks'),
            ));
        }

        $subscription = $this->subscriptions->grantAiQuota($tenant, $validated['blocks']);

        return back()->with('success', sprintf(
            'Jatah analisis AI Anda bertambah %d per hari dan langsung berlaku hari ini. '
            .'Tambahannya gratis sampai periode ini habis, lalu masuk tagihan bulanan sebesar %s per blok.',
            $validated['blocks'] * (int) config('subscription.ai_quota.block_size'),
            'Rp '.number_format(AiBlockPrice::current(), 0, ',', '.'),
        ));
    }

    /**
     * Lepas blok kuota AI. Berlaku satu periode penuh ke depan.
     *
     * Tanpa padanan penjaga "masih dipakai staf aktif" yang ada di pelepasan
     * seat, dan ketiadaannya disengaja: kuota AI tidak diduduki siapa pun.
     * Yang terjadi paling buruk adalah jatah harian turun kembali ke angka
     * paketnya — dan itu justru yang tenant minta.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'blocks' => ['required', 'integer', 'min:1'],
        ]);

        $tenant = $request->user()->tenant;
        $subscription = $this->subscriptions->ensureFor($tenant);
        $ceiling = $this->subscriptions->aiQuotaReleaseCeiling($subscription);

        if ($ceiling < 1) {
            return back()->with('error', $subscription->hasPendingAiQuotaRelease()
                ? 'Semua blok kuota AI Anda sudah dijadwalkan dilepas.'
                : 'Anda tidak punya blok kuota AI tambahan untuk dilepas.');
        }

        if ($validated['blocks'] > $ceiling) {
            return back()->with('error', sprintf(
                'Paling banyak %d blok yang bisa dilepas sekarang.',
                $ceiling,
            ));
        }

        $subscription = $this->subscriptions->releaseAiQuota($tenant, $validated['blocks']);

        return back()->with('success', sprintf(
            'Pelepasan %d blok kuota AI tercatat dan berlaku %s. Sampai tanggal itu jatah hariannya '
            .'masih penuh, dan tagihan sesudahnya sudah tidak memuatnya.',
            $validated['blocks'],
            $subscription->ai_quota_release_at->translatedFormat('j F Y'),
        ));
    }
}
