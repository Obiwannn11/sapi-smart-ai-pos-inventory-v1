<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\TenantConsent;
use App\Services\Ai\AiQuota;
use App\Services\Billing\Gateways\PaymentGatewayManager;
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
                'post_trial_plan' => $subscriptions->postTrialPlanFor($subscription),
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
            // Yang dibutuhkan panel beli/lepas kuota (`[BL-069]`), terpisah
            // dari `aiQuota` di atasnya dan pemisahan itu disengaja: yang di
            // atas menjawab "berapa jatah saya hari ini" dan dibaca tiga layar
            // lewat satu bentuk bersama; yang di bawah menjawab "apa yang bisa
            // saya lakukan terhadap jatah itu", dan hanya berlaku di sini.
            'aiQuotaOffer' => [
                'block_size' => (int) config('subscription.ai_quota.block_size'),
                'block_price' => (float) config('subscription.ai_quota.block_price'),
                'max_blocks' => (int) config('subscription.ai_quota.max_blocks'),
                // Dikirim sebagai angka, bukan sebagai boleh/tidak, dengan
                // alasan yang sama seperti `releasable_seats`: formulir yang
                // menawarkan lalu menolak membuat tenant menebak berapa yang
                // sebenarnya bisa.
                'purchasable_blocks' => $subscriptions->aiQuotaPurchaseCeiling($subscription),
                'releasable_blocks' => $subscriptions->aiQuotaReleaseCeiling($subscription),
                // Pelepasan yang sudah dijadwalkan tapi belum berlaku. Tanggalnya
                // ikut, karena sampai hari itu jatahnya masih penuh — dan tenant
                // yang tidak tahu tanggalnya akan mengira kuotanya sudah hilang
                // hari ini.
                'scheduled_blocks' => $subscription->hasPendingAiQuotaRelease()
                    ? $subscription->scheduled_ai_blocks
                    : null,
                'release_at' => $subscription->ai_quota_release_at?->toDateString(),
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
                // Dibandingkan terhadap tarif yang benar-benar bersaing, bukan
                // terhadap Rp 0 yang dibayar tenant masa coba (`[BL-044]`(c)).
                // Sebelum ini setiap tenant masa coba dijawab "Harga Tetap
                // masih lebih menguntungkan" — dibandingkan dengan gratis,
                // memang selalu.
                'estimate' => $subscription->isSubsidized()
                    ? null
                    : $estimator->estimateFor($tenant, $subscriptions->comparisonPriceFor($subscription)),
            ],
            // Kelas harga bagi tenant jalur Harga Tetap (`[BL-041]`(b)).
            // Sebelum ini hanya tenant Harga Adaptif yang bisa melihat kelasnya
            // sendiri, sehingga tenant bayar-penuh tidak punya penjelasan apa
            // pun mengapa tarifnya sekian.
            //
            // Diletakkan di luar `subsidy`, bukan menumpang `bracket` di
            // dalamnya, karena dua alasan. Bentuknya berbeda — `bracket` memuat
            // `period` dan `revenue` yang tidak berlaku di sini, dan halaman
            // ini membacanya sebagai "Dihitung dari omzet ...". Dan artinya
            // berbeda: yang satu keadaan jalur adaptif, yang satu justru
            // keadaan tenant yang tidak membuka apa-apa.
            //
            // `null` untuk tenant adaptif: mereka sudah punya `bracket`, dan
            // dua kartu yang menjawab pertanyaan yang sama dengan angka yang
            // sama hanya membuat pembacanya bertanya mana yang benar.
            'classification' => $subscription->isSubsidized()
                ? null
                : $pricing->classificationFor($tenant),
            'invoices' => $tenant->invoices()
                ->latest('id')
                ->take(12)
                ->get()
                ->map(fn ($invoice) => [
                    'id' => $invoice->id,
                    'period' => $invoice->period,
                    'kind' => $invoice->kind,
                    'amount' => (float) $invoice->amount,
                    // Terlihat tenant dengan sengaja (`[BL-057]`(a)): nominal
                    // yang berbeda dari daftar harga tanpa penjelasan adalah
                    // pertanyaan yang pasti datang, dan menjawabnya di layar
                    // lebih murah daripada menjawabnya lewat percakapan.
                    'amount_reason' => $invoice->amount_reason,
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
            // Payment gateway (`[BL-059]`). Ditanyakan lewat manager, BUKAN
            // dengan me-resolve drivernya: driver tiruan melempar exception di
            // produksi, dan halaman langganan adalah tempat terakhir yang boleh
            // mati gara-gara satu variabel `.env` salah setel — di sanalah
            // tenant yang ditangguhkan mencari jalan keluarnya.
            'payment' => [
                'enabled' => $paymentEnabled = $gateways->has($configuredDriver = (string) config('subscription.payment.driver')),
                'is_simulated' => $gateways->isSimulated($configuredDriver),
                // Kanal ikut dikirim supaya modal bayar di halaman ini bisa
                // menawarkan pilihan tanpa satu pun kunjungan halaman
                // tambahan. Aman di-resolve di sini justru karena dijaga
                // `enabled` di atasnya: `has()` sudah menjawab false untuk
                // driver tiruan di produksi, sehingga `driver()` yang melempar
                // exception di sana tidak pernah terpanggil.
                'channels' => $paymentEnabled ? $gateways->driver($configuredDriver)->availableChannels() : [],
            ],
            // Jalan keluar dari penangguhan (`[BL-051]` opsi (ii)). `null`
            // untuk tenant yang tidak ditangguhkan — lihat
            // `SubscriptionService::reactivationStateFor()`.
            //
            // Statusnya ikut, bukan cuma boleh/tidaknya, dengan alasan yang
            // sama seperti `subsidy.reason` di atas: tombol yang mati tanpa
            // sebab membuat tenant menebak, dan tenant yang seluruh
            // aplikasinya sudah tertutup adalah yang paling tidak punya ruang
            // untuk menebak.
            'reactivation' => $subscriptions->reactivationStateFor($tenant),
        ]);
    }
}
