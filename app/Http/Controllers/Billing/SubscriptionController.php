<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\TenantConsent;
use App\Services\Ai\AiQuota;
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
        AiQuota $quota,
    ): Response {
        $tenant = $request->user()->tenant;
        $subscription = $subscriptions->ensureFor($tenant);
        $subscription->loadMissing('plan');

        // Aturannya tinggal di model, bukan di sini: halaman ini bukan satu-
        // satunya yang menjawab "berapa tarif Anda", dan `price_locked` punya
        // satu jebakan (nol yang bukan null) yang akan terlewat di penyalinan
        // pertama. Lihat `Subscription::effectivePrice()`.
        $effectivePrice = $subscription->effectivePrice();
        $normalConsent = $consents->latestFor($tenant, TenantConsent::TYPE_NORMAL);
        $verdict = $subscriptions->adaptiveVerdict($tenant);

        return inertia('Billing/Show', [
            'tenant' => [
                'name' => $tenant->name,
                'status' => $tenant->status,
                'is_owner' => $request->user()->isOwner(),
            ],
            'subscription' => [
                'plan_name' => $subscription->plan->name,
                // Satu angka, sudah jadi — bukan `base_price` dan `price_locked`
                // mentah yang harus disatukan lagi oleh template. Halaman ini
                // pernah menyalin `price_locked ?? base_price` ke dalam
                // Vue-nya, dan salinan itulah yang memajang "Rp 0/bulan" kepada
                // tenant bertarif Rp 100.000.
                'effective_price' => $effectivePrice,
                'pricing_track' => $subscription->pricing_track,
                'seats' => $subscription->seats,
                'seats_used' => $subscription->activeSeatsUsed(),
                // Asal-usul kursinya dipecah, bukan cuma totalnya (`[BL-053]`).
                // Sejak seat tambahan jadi komponen tagihan bulanan, "6 kursi"
                // saja tidak lagi menjawab pertanyaan yang benar-benar
                // ditanyakan tenant saat melihat tagihannya: mana yang sudah
                // termasuk paket, dan mana yang ia bayar sendiri tiap bulan.
                'included_seats' => $subscription->plan->included_seats,
                'extra_seats' => $subscription->purchased_extra_seats,
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
            // Kuota AI ikut dikirim ke halaman langganan (`[BL-067]`(b)).
            // Sebelum ini isi paket terpecah dua: kursi dijawab di sini, kuota
            // AI hanya di Pengaturan, dan tidak ada satu pun layar yang
            // menjawab "paket saya dapat apa saja". Bentuknya sama persis
            // dengan yang dipakai Pengaturan dan AI Analysis — satu pembaca,
            // `AiQuota::snapshotFor()`, bukan hitungan ketiga yang bisa
            // berbeda dari keduanya.
            'aiQuota' => $quota->snapshotFor($tenant),
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
                'can_switch' => $verdict['eligible'],
                // Alasannya, bukan cuma boleh-tidaknya (`[BL-055]`(c)). Tombol
                // yang mati tanpa sebab membuat tenant menebak yang mana dari
                // tiga penghalang yang sedang berlaku — dan tebakan yang salah
                // berakhir di tiket dukungan atas sesuatu yang sistem sudah tahu
                // jawabannya.
                'reason' => $verdict['reason'],
                'ceiling' => $verdict['ceiling'],
                'measured_revenue' => $verdict['revenue'],
                'switch_available_at' => $verdict['available_at'],
                'reverts_at' => $subscription->track_reverts_at?->toDateString(),
                // Pemindahan karena omzet melewati ambang dibedakan dari
                // pencabutan sukarela: yang satu menaikkan tagihan bulan depan,
                // yang satu memang diminta tenant sendiri. Satu kalimat untuk
                // keduanya akan salah pada separuh pembacanya.
                'revert_reason' => $subscription->track_revert_reason,
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
                // Peninggalan: tagihan `KIND_UPGRADE` yang terbit sebelum
                // 2026-08-07 dan belum selesai. Tak ada lagi yang lahir, tapi
                // yang menggantung masih menahan pembelian seat baru — lihat
                // `UpgradeController::store()`.
                'has_open_request' => $subscriptions->openUpgradeInvoice($tenant) !== null,
                // Paling banyak berapa yang boleh dilepas sekarang. Dikirim
                // sebagai angka, bukan sebagai boolean "boleh/tidak": formulir
                // yang menawarkan pengurangan lalu menolaknya membuat tenant
                // menebak sendiri berapa yang sebenarnya bisa.
                'releasable_seats' => $subscriptions->seatReleaseCeiling($subscription),
                // Pelepasan yang sudah dijadwalkan tapi belum berlaku
                // (`[BL-053]`). Tanggalnya ikut, karena sampai hari itu kursinya
                // masih boleh dipakai — dan tenant yang tidak tahu tanggalnya
                // akan mengira kursinya sudah hilang hari ini.
                'scheduled_seats' => $subscription->hasPendingSeatRelease()
                    ? $subscription->scheduled_extra_seats
                    : null,
                'release_at' => $subscription->seat_release_at?->toDateString(),
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
