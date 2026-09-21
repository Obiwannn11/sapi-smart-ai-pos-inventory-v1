<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\TenantConsent;
use App\Services\ConsentService;
use App\Services\Pricing\AdaptiveEligibility;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Inertia\Response;

/**
 * Halaman persetujuan jalur harga.
 *
 * Dua dokumen terpisah, bukan satu dokumen dengan pasal bersyarat. Yang paling
 * penting dari masing-masing justru saling bertolak belakang — jalur normal
 * tidak membuka data bisnis sama sekali, jalur subsidi membuka omzet — dan
 * dokumen bersyarat akan mengaburkan tepat bagian itu.
 */
class ConsentController extends Controller
{
    public function __construct(
        private readonly ConsentService $consents,
        private readonly SubscriptionService $subscriptions,
    ) {}

    public function show(Request $request, string $type = TenantConsent::TYPE_NORMAL): Response
    {
        abort_unless($this->isKnownType($type), 404);

        $tenant = $request->user()->tenant;
        $document = $this->consents->document($type);
        $agreed = $this->consents->latestFor($tenant, $type);
        $isOwner = $request->user()->isOwner();

        return inertia('Billing/Consent', [
            'type' => $type,
            // Markdown dirender di server dengan league/commonmark bawaan
            // Laravel. Teksnya berkas milik kita sendiri, bukan masukan
            // pengguna, jadi menyuntikkannya sebagai HTML aman di sini — dan
            // jauh lebih baik daripada menyalin penerjemah markdown kedua ke
            // sisi klien.
            'document' => [
                'version' => $document['version'],
                'html' => Str::markdown($document['body']),
            ],
            'agreement' => $agreed === null ? null : [
                'version' => $agreed->version,
                'agreed_at' => $agreed->agreed_at->toDateString(),
                'is_current' => $agreed->version === $document['version'],
            ],
            'can_agree' => $isOwner && $this->switchIsAllowed($tenant, $type),
            'blocked_reason' => $isOwner ? $this->blockedReason($tenant, $type) : null,
        ]);
    }

    public function store(Request $request, string $type = TenantConsent::TYPE_NORMAL): RedirectResponse
    {
        abort_unless($this->isKnownType($type), 404);

        $request->validate([
            // Dikirim oleh klien dan dicocokkan di server. Tanpa ini, halaman
            // yang dibiarkan terbuka berhari-hari bisa mencatat persetujuan
            // atas versi yang sudah diganti sementara itu.
            'version' => ['required', 'string'],
            'agreed' => ['required', 'accepted'],
        ]);

        $tenant = $request->user()->tenant;

        if ($request->input('version') !== $this->consents->currentVersion($type)) {
            return back()->with('error', 'Teks persetujuan sudah diperbarui. Silakan baca ulang versi terbarunya.');
        }

        if (! $this->switchIsAllowed($tenant, $type)) {
            return back()->with('error', $this->blockedReason($tenant, $type));
        }

        $this->consents->record($tenant, $request->user(), $type, $request->ip());

        if ($type === TenantConsent::TYPE_SUBSIDIZED) {
            $this->subscriptions->switchToSubsidized($tenant);

            return redirect()->route('billing.show')
                ->with('success', 'Pengajuan Harga Adaptif tercatat. Tarif Anda mengikuti kelompok omzet mulai periode berikutnya.');
        }

        return redirect()->route('billing.show')->with('success', 'Persetujuan tercatat. Terima kasih.');
    }

    /**
     * Cabut persetujuan jalur subsidi.
     *
     * Ringkasan omzet dihapus seketika, tapi harga subsidi tetap berlaku sampai
     * akhir periode berjalan — persis seperti yang dijanjikan dokumennya.
     */
    public function revokeSubsidy(Request $request): RedirectResponse
    {
        $tenant = $request->user()->tenant;

        $dicabut = $this->consents->revoke($tenant, TenantConsent::TYPE_SUBSIDIZED);

        if ($dicabut === 0) {
            return back()->with('error', 'Tidak ada persetujuan Harga Adaptif yang aktif.');
        }

        $this->subscriptions->scheduleTrackRevert($tenant);

        return back()->with('success', 'Persetujuan dicabut dan ringkasan omzet Anda dihapus. Tarif adaptif tetap berlaku sampai akhir periode berjalan.');
    }

    private function isKnownType(string $type): bool
    {
        return config("subscription.consents.{$type}") !== null;
    }

    /**
     * Jalur subsidi menuntut syarat di luar peran owner, dan syarat-syarat itu
     * ditanyakan ke `adaptiveVerdict()` — bukan diperiksa ulang di sini.
     *
     * Inilah titik penegakan yang sesungguhnya: halaman mana pun boleh
     * menampilkan tombolnya, tapi yang benar-benar memindahkan jalur adalah
     * `store()` di bawah. Pagar yang hanya hidup di layar bisa dilewati dengan
     * satu POST, dan pagar yang ditulis dua kali akan bercabang begitu salah
     * satunya diperbaiki (`[BL-055]`(c)).
     */
    private function switchIsAllowed($tenant, string $type): bool
    {
        if ($type !== TenantConsent::TYPE_SUBSIDIZED) {
            return true;
        }

        return $this->subscriptions->adaptiveVerdict($tenant)['eligible'];
    }

    /**
     * Kalimat penolakan yang menyebut sebabnya DAN jalan keluarnya.
     *
     * Tiga sebab, tiga jalan keluar yang sama sekali berbeda — menunggu,
     * membayar penuh, atau tidak melakukan apa-apa karena sudah di dalam.
     * Menyamaratakannya jadi "tidak bisa" memaksa tenant menebak yang mana,
     * dan tebakan yang salah berakhir di tiket dukungan.
     */
    private function blockedReason($tenant, string $type): ?string
    {
        if ($type !== TenantConsent::TYPE_SUBSIDIZED) {
            return null;
        }

        $verdict = $this->subscriptions->adaptiveVerdict($tenant);

        return match ($verdict['reason']) {
            AdaptiveEligibility::REASON_ELIGIBLE => null,
            AdaptiveEligibility::REASON_ACTIVE => 'Anda sudah berada di jalur Harga Adaptif. Tidak ada yang perlu diajukan lagi.',
            AdaptiveEligibility::REASON_ABOVE_CEILING => sprintf(
                'Omzet Anda %s, di atas batas %s untuk keringanan. Jalur yang berlaku bagi Anda adalah paket berbayar penuh.',
                $this->rupiah($verdict['revenue']),
                $this->rupiah($verdict['ceiling']),
            ),
            default => sprintf(
                'Perpindahan jalur harga hanya bisa dilakukan setiap %d bulan. Anda bisa mengajukannya lagi mulai %s.',
                SubscriptionService::trackSwitchMinimumMonths(),
                $verdict['available_at'] === null
                    ? '-'
                    : Carbon::parse($verdict['available_at'])->translatedFormat('j F Y'),
            ),
        };
    }

    private function rupiah(?float $amount): string
    {
        return $amount === null ? '-' : 'Rp '.number_format($amount, 0, ',', '.');
    }
}
