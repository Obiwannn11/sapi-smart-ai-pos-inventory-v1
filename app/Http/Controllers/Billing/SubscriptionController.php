<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantConsent;
use App\Services\Billing\Gateways\PaymentGatewayManager;
use App\Services\Billing\InvoiceSettlement;
use App\Services\ConsentService;
use App\Services\Pricing\SubsidyEstimator;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Halaman langganan sisi tenant.
 *
 * Sengaja terbuka untuk SEMUA pengguna tenant, bukan hanya owner: begitu
 * tenant ditangguhkan, setiap halaman lain mengarah ke sini. Kalau halaman ini
 * pun digerbang `role:owner`, kasir yang sedang bekerja akan mendarat di 403
 * tanpa penjelasan apa pun. Tindakan yang mengubah keadaan (persetujuan,
 * unggah bukti bayar) tetap milik owner — digerbang di rutenya sendiri.
 */
class SubscriptionController extends Controller
{
    public function show(
        Request $request,
        SubscriptionService $subscriptions,
        ConsentService $consents,
        PricingService $pricing,
        SubsidyEstimator $estimator,
        InvoiceSettlement $settlement,
        PaymentGatewayManager $gateways,
    ): Response {
        $tenant = $request->user()->tenant;
        $subscription = $subscriptions->ensureFor($tenant);
        $subscription->loadMissing('plan');

        $effectivePrice = (float) ($subscription->price_locked ?? $subscription->plan->base_price);
        $normalConsent = $consents->latestFor($tenant, TenantConsent::TYPE_NORMAL);

        return inertia('Billing/Show', [
            'tenant' => [
                'name' => $tenant->name,
                'status' => $tenant->status,
                'is_owner' => $request->user()->isOwner(),
            ],
            'subscription' => [
                'plan_name' => $subscription->plan->name,
                'base_price' => (float) $subscription->plan->base_price,
                'price_locked' => $subscription->price_locked === null
                    ? null
                    : (float) $subscription->price_locked,
                'pricing_track' => $subscription->pricing_track,
                'seats' => $subscription->seats,
                'seats_used' => $subscription->activeSeatsUsed(),
                'trial_ends_at' => $subscription->trial_ends_at?->toDateString(),
                // Paket yang akan dihuni tenant begitu masa gratisnya habis —
                // `[BL-052]`(c). Diberitahukan SEBELUM hari-H, bukan lewat
                // tagihan yang tiba-tiba muncul: masa gratis yang berakhir
                // dengan tagihan pertama tanpa satu pun peringatan terbaca
                // sebagai jebakan, bukan sebagai kesepakatan.
                //
                // `null` begitu perpindahannya terjadi — paket di baris atas
                // sudah menyebut namanya sendiri, dan kalimat "akan pindah ke
                // Paid 1" di halaman tenant yang SUDAH di Paid 1 hanya
                // membingungkan.
                'post_trial_plan' => $this->postTrialPlanFor($subscription),
                'current_period_end' => $subscription->current_period_end?->toDateString(),
                // Tanggal penangguhan dihitung dan DITAMPILKAN, bukan disimpan
                // diam-diam. Tenant di masa tenggang berhak tahu persis kapan
                // pintunya tertutup, bukan sekadar bahwa ia akan tertutup.
                'suspends_at' => $subscriptions->suspensionDateFor($tenant)?->toDateString(),
            ],
            'consent' => [
                'agreed' => $consents->hasAgreedToCurrent($tenant, TenantConsent::TYPE_NORMAL),
                // Versi dikirim BERPASANGAN supaya halamannya bisa membedakan
                // dua keadaan yang sebelumnya menyatu jadi "belum disetujui":
                // belum pernah menyetujui apa pun, versus pernah menyetujui
                // teks yang kini sudah diganti. Yang kedua bukan kelalaian
                // tenant, dan menyebutnya begitu tidak jujur.
                'current_version' => $consents->currentVersion(TenantConsent::TYPE_NORMAL),
                'agreed_version' => $normalConsent?->version,
                'agreed_at' => $normalConsent?->agreed_at?->toDateString(),
                // Nama penyetujunya, bukan sekadar "sudah disetujui". Ini bukti,
                // dan bukti yang tidak menyebut siapa membuktikan lebih sedikit.
                'agreed_by' => $normalConsent?->user?->name,
            ],
            'subsidy' => [
                'is_active' => $subscription->isSubsidized(),
                'agreed' => $consents->hasAgreedToCurrent($tenant, TenantConsent::TYPE_SUBSIDIZED),
                'can_switch' => $subscriptions->canSwitchTrack($tenant),
                'switch_available_at' => $subscriptions->trackSwitchAvailableAt($tenant)?->toDateString(),
                'reverts_at' => $subscription->track_reverts_at?->toDateString(),
                // Tenant selalu boleh melihat omzetnya sendiri berikut angka
                // persisnya — ini datanya. Yang dibatasi adalah pandangan
                // pengelola layanan, bukan pandangan pemiliknya.
                'bracket' => $subscription->isSubsidized()
                    ? $pricing->currentBracketFor($tenant)
                    : null,
                // Perkiraan untuk tenant yang BELUM di jalur adaptif: apakah
                // omsetnya memang jatuh di kelompok bertarif lebih rendah.
                // Tanpa ini, ajakan pindah jalur meminta tenant menyerahkan
                // angka penjualannya demi tarif yang tak pernah ia lihat lebih
                // dulu — tukar-menukar yang tidak seimbang.
                //
                // Dihitung on-the-fly dan tidak pernah disimpan; lihat docblock
                // SubsidyEstimator untuk batasnya terhadap gerbang privasi.
                'estimate' => $subscription->isSubsidized()
                    ? null
                    : $estimator->estimateFor($tenant, $effectivePrice),
            ],
            'invoices' => $tenant->invoices()
                ->latest('id')
                ->take(12)
                ->get()
                ->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'period' => $invoice->period,
                    'kind' => $invoice->kind,
                    'amount' => (float) $invoice->amount,
                    'status' => $invoice->status,
                    'due_date' => $invoice->due_date?->toDateString(),
                    'rejection_reason' => $invoice->rejection_reason,
                    'has_proof' => $invoice->proof_path !== null,
                ]),
            'upgrade' => [
                'extra_seat_price' => (float) $subscription->plan->extra_seat_price,
                'has_open_request' => $subscriptions->openUpgradeInvoice($tenant) !== null,
                // Batas satu upgrade per bulan kalender, turunan indeks unik
                // `(tenant_id, period, kind)`. Dikirim supaya formulirnya tidak
                // ditawarkan untuk kemudian ditolak — lihat `[BL-050]`.
                'closed_for_period' => $subscriptions->hasUpgradeInvoiceThisPeriod($tenant),
                // Dinyatakan terus terang, bukan disembunyikan: tenant berhak
                // tahu bahwa penambahan pengguna kini menunggu pemeriksaan, dan
                // kenapa.
                'is_provisional_blocked' => $subscription->provisional_blocked,
            ],
            // Peragaan (`[BL-045]`). `false` di produksi dan untuk tenant biasa,
            // sehingga panelnya tidak pernah dirender sama sekali di sana —
            // bukan sekadar disembunyikan CSS.
            'simulation' => [
                'enabled' => $settlement->canSimulate($tenant),
            ],
            // Payment gateway (`[BL-059]`). Ditanyakan lewat manager, BUKAN
            // dengan me-resolve drivernya: driver tiruan melempar exception di
            // produksi, dan halaman langganan adalah tempat terakhir yang boleh
            // mati gara-gara satu variabel `.env` salah setel — di sanalah
            // tenant yang ditangguhkan mencari jalan keluarnya.
            'payment' => [
                'enabled' => $gateways->has($configuredDriver = (string) config('subscription.payment.driver')),
                'is_simulated' => $gateways->isSimulated($configuredDriver),
            ],
        ]);
    }

    /**
     * Nama dan tarif paket tujuan setelah masa gratis, bila memang akan ada
     * perpindahan.
     *
     * `null` untuk tiga keadaan yang sama-sama berarti "tak ada yang perlu
     * diumumkan": tenant tidak sedang di paket gratis, langganannya tak punya
     * tanggal akhir masa gratis, atau pemilik SaaS belum menunjuk paket tujuan
     * mana pun. Yang terakhir sengaja tidak berbunyi apa-apa di sisi tenant —
     * salah setel platform bukan kabar yang berguna baginya; yang menagihnya
     * adalah peringatan di `/platform/pricing-rules` dan keluaran
     * `subscriptions:advance-lifecycle`.
     *
     * @return array{name: string, base_price: float}|null
     */
    protected function postTrialPlanFor(Subscription $subscription): ?array
    {
        if ($subscription->trial_ends_at === null || $subscription->plan->slug !== Plan::SLUG_DEFAULT) {
            return null;
        }

        $target = Plan::postTrialTarget();

        if ($target === null || $target->slug === Plan::SLUG_DEFAULT) {
            return null;
        }

        return [
            'name' => $target->name,
            'base_price' => (float) $target->base_price,
        ];
    }
}
