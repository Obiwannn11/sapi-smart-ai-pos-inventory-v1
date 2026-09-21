<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\TenantConsent;
use App\Services\ConsentService;
use App\Services\Pricing\SubsidyEstimator;
use App\Services\PricingService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Inertia\Response;

/**
 * Halaman pengajuan Harga Adaptif — `[BL-055]`(a).
 *
 * Sampai sekarang satu-satunya pintu ke jalur keringanan adalah halaman
 * persetujuan, yang tidak pernah menyebut dirinya sebagai pengajuan keringanan
 * dan tidak menampilkan satu pun angka. Sejak paket berbayar termurah Rp
 * 100.000, jalur ini naik pangkat jadi tier masuk de facto: tenant yang tidak
 * sanggup membayarnya tidak punya jalan lain. Pintu masuk yang tidak
 * menyebutkan namanya sendiri, untuk sesuatu sepenting itu, adalah cacat produk
 * — bukan sekadar kekurangan halaman.
 *
 * **Isinya dari data, bukan dari HTML.** Tangga bracket dibaca dari
 * `pricing_rules` lewat resolver yang sama dengan yang menetapkan tagihan, dan
 * ambangnya dihitung dari tangga itu juga. Daftar harga yang diketik di
 * template akan berselisih dengan tagihan pada hari pertama pemilik SaaS
 * menyunting sebuah bracket — dan yang dipercaya tenant adalah yang di layar.
 *
 * Terbuka untuk seluruh pengguna tenant, sama seperti halaman langganan:
 * tenant yang ditangguhkan diarahkan ke sini, dan menggerbangnya `role:owner`
 * akan memulangkan kasir ke 403 tanpa penjelasan. Yang mengikat usaha pada
 * perjanjian tetap milik owner, digerbang di rute persetujuannya sendiri.
 */
class AdaptiveController extends Controller
{
    public function show(
        Request $request,
        SubscriptionService $subscriptions,
        PricingService $pricing,
        ConsentService $consents,
        SubsidyEstimator $estimator,
    ): Response {
        $tenant = $request->user()->tenant;
        $subscription = $subscriptions->ensureFor($tenant);
        $subscription->loadMissing('plan');

        // Aturannya tinggal di model — lihat `Subscription::effectivePrice()`.
        // Halaman ini menyalin ungkapan `price_locked ?? base_price` dari
        // halaman langganan berikut jebakannya: kolomnya desimal, jadi `0.00`
        // bukan `null` dan tarif paket tidak pernah terpakai.
        $effectivePrice = $subscription->effectivePrice();
        $verdict = $subscriptions->adaptiveVerdict($tenant);

        return inertia('Billing/Adaptive', [
            'tenant' => [
                'name' => $tenant->name,
                'status' => $tenant->status,
                'is_owner' => $request->user()->isOwner(),
            ],
            'current' => [
                'plan_name' => $subscription->plan->name,
                'price' => $effectivePrice,
                'is_adaptive' => $subscription->isSubsidized(),
                // Kapan tarif yang berlaku sekarang mulai berlaku. Dua tanggal
                // yang berbeda, dan keduanya dikirim karena keduanya ditanyakan
                // orang yang berbeda: kapan jalurnya berpindah, dan kapan
                // angkanya terakhir benar-benar ditagihkan.
                'track_changed_at' => $subscription->track_changed_at?->toDateString(),
                'last_invoice' => $this->lastSubscriptionInvoice($tenant),
                'reverts_at' => $subscription->track_reverts_at?->toDateString(),
                'revert_reason' => $subscription->track_revert_reason,
            ],
            // Tangga lengkapnya, termasuk anak tangga yang TIDAK berlaku bagi
            // tenant ini. Menampilkan hanya bracket miliknya membuat harga
            // terlihat sebagai angka yang muncul entah dari mana; menampilkan
            // seluruh tangganya membuatnya bisa diperiksa sendiri.
            'ladder' => $pricing->adaptiveLadder(),
            'verdict' => $verdict,
            // Bracket berjalan untuk tenant yang sudah di dalam; perkiraan untuk
            // yang belum. Tidak pernah keduanya — yang satu fakta tercatat, yang
            // satu hitungan sesaat, dan menyandingkannya hanya mengaburkan mana
            // yang mana.
            'bracket' => $subscription->isSubsidized()
                ? $pricing->currentBracketFor($tenant)
                : null,
            // Pembandingnya tarif yang benar-benar bersaing, bukan Rp 0 yang
            // dibayar tenant masa coba (`[BL-044]`(c)) — lihat
            // `SubscriptionService::comparisonPriceFor()`.
            'estimate' => $subscription->isSubsidized()
                ? null
                : $estimator->estimateFor($tenant, $subscriptions->comparisonPriceFor($subscription)),
            'consent' => [
                'agreed' => $consents->hasAgreedToCurrent($tenant, TenantConsent::TYPE_SUBSIDIZED),
                'current_version' => $consents->currentVersion(TenantConsent::TYPE_SUBSIDIZED),
            ],
            'switch_minimum_months' => SubscriptionService::trackSwitchMinimumMonths(),
        ]);
    }

    /**
     * Tagihan langganan terakhir — bukti terakhir bahwa sebuah tarif benar-benar
     * diterapkan, bukan sekadar dihitung.
     *
     * `KIND_UPGRADE` sengaja di luar: tagihan penambahan seat bukan tarif
     * langganan, dan memajangnya di sini akan menjawab "berapa tarif Anda"
     * dengan angka yang sama sekali lain.
     *
     * @return array{period: string, amount: float, status: string}|null
     */
    private function lastSubscriptionInvoice($tenant): ?array
    {
        $invoice = $tenant->invoices()
            ->where('kind', Invoice::KIND_SUBSCRIPTION)
            ->latest('period')
            ->latest('id')
            ->first();

        return $invoice === null ? null : [
            'period' => $invoice->period,
            'amount' => (float) $invoice->amount,
            'status' => $invoice->status,
        ];
    }
}
