<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\TenantConsent;
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
        ]);
    }
}
