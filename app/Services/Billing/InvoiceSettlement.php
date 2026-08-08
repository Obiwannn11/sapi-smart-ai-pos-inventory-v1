<?php

namespace App\Services\Billing;

use App\Models\Invoice;
use App\Models\Tenant;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya pintu menuju keadaan `active`.
 *
 * Sebelum ini isinya tinggal di dalam `Platform\InvoiceController::verify()`,
 * dan itu cukup selama hanya ada satu cara tagihan dilunasi. Begitu ada cara
 * kedua — tombol simulasi (`[BL-045]`), dan kelak webhook payment gateway —
 * menyalin logikanya berarti menyalin juga aturan periode, penguncian harga,
 * dan reset puncak seat. Salinan yang bercabang di jalur uang adalah jenis
 * kesalahan yang paling lama tidak terlihat.
 *
 * **Payment gateway sudah terpasang** lewat jalur itu juga (`[BL-059]`):
 * `PaymentWebhookController` memanggil `settle()` dengan `SOURCE_GATEWAY` /
 * `SOURCE_GATEWAY_FAKE`, dan tidak menulis `Tenant::STATUS_ACTIVE` di mana pun.
 * Penyedia berikutnya cukup mengisi kontrak `PaymentGateway`; tak ada logika
 * pelunasan yang perlu ikut ditulis ulang.
 *
 * Tiga hal yang dulu dicatat di sini sebagai "belum dijawab", dan di mana
 * jawabannya sekarang tinggal:
 *
 *   1. **Idempotensi webhook.** Unik `(gateway, external_id)` pada
 *      `payment_attempts` + penguncian baris + penjaga `isPaid()` di bawah.
 *      Notifikasi kedua tidak melunasi apa pun dan tetap dijawab 200 supaya
 *      penyedia berhenti mengulang — lihat `PaymentWebhookController`.
 *   2. **Nominal yang benar-benar diterima.** Dibandingkan dengan
 *      `invoice->amount` SEBELUM `settle()` dipanggil; selisih berapa pun
 *      menghentikan pelunasan dan meninggalkan percobaan berstatus `mismatch`
 *      untuk diputuskan orang. `settle()` sendiri tetap mengunci `price_locked`
 *      dari yang DITAGIH, dan itu hanya benar bila keduanya sudah dipastikan
 *      sama.
 *   3. **Keaslian panggilan.** `PaymentGateway::verifyCallback()` melempar
 *      `InvalidCallbackSignature` sebelum apa pun dibaca. `settle()` tetap
 *      mempercayai pemanggilnya sepenuhnya — karena itu pemeriksaannya harus
 *      selesai sebelum sampai ke sini.
 */
class InvoiceSettlement
{
    /** Pemilik SaaS memeriksa bukti transfer di panel platform. */
    public const SOURCE_PLATFORM_VERIFY = 'platform_verify';

    /** Tombol peragaan; hanya hidup untuk tenant demo di luar produksi. */
    public const SOURCE_SIMULATION = 'simulation';

    /** Nominalnya nol — tidak ada yang perlu ditransfer, apalagi dibuktikan. */
    public const SOURCE_ZERO_AMOUNT = 'zero_amount';

    /** Payment gateway sungguhan mengabarkan uangnya masuk. */
    public const SOURCE_GATEWAY = 'gateway';

    /**
     * Gateway TIRUAN mengabarkan uangnya masuk — tidak ada uang yang berpindah.
     *
     * Dipisah dari `SOURCE_GATEWAY`, bukan dibedakan lewat kolom lain: laporan
     * pendapatan mana pun yang menjumlahkan tagihan lunas harus bisa
     * mengeluarkan yang ini tanpa perlu tahu driver mana yang sedang aktif saat
     * itu. Satu nilai `gateway` untuk keduanya membuat uang peragaan tak
     * terbedakan dari uang sungguhan begitu drivernya diganti.
     */
    public const SOURCE_GATEWAY_FAKE = 'gateway_fake';

    public function __construct(private readonly SubscriptionService $subscriptions) {}

    /**
     * Lunasi tagihan, lalu bawa langganannya ke keadaan yang seharusnya.
     *
     * `$verifiedBy` adalah id akun platform bila ada orangnya. Pelunasan yang
     * tidak diperiksa manusia — simulasi hari ini, gateway kelak — meninggalkan
     * kolom itu null, dan itu disengaja: kolomnya menjawab "siapa yang
     * memeriksa", dan mengisinya dengan siapa pun yang kebetulan lewat akan
     * membuat jejaknya berbohong.
     */
    public function settle(Invoice $invoice, string $source, ?int $verifiedBy = null): void
    {
        DB::transaction(function () use ($invoice, $source, $verifiedBy) {
            $invoice->update([
                'status' => Invoice::STATUS_PAID,
                'paid_at' => now(),
                'verified_by' => $verifiedBy,
                'verified_at' => now(),
                'rejection_reason' => null,
                'settled_via' => $source,
            ]);

            $subscription = $invoice->subscription;

            if ($invoice->isUpgrade()) {
                // Upgrade hanya menambah seat. Ia TIDAK memperpanjang periode
                // dan TIDAK mengubah tarif bulanan — biaya sekali-bayar untuk
                // kasir tambahan bukan harga langganan, dan menukar keduanya
                // akan membuat tagihan bulan depan salah.
                if ($invoice->grants_seats !== null) {
                    $subscription->update(['seats' => $invoice->grants_seats]);
                }

                return;
            }

            $subscription->update([
                // Harga DIKUNCI dari nominal yang benar-benar dibayar, bukan
                // dibaca ulang dari tabel tarif. Inilah grandfathering:
                // mengubah tarif besok tidak boleh mengubah apa yang sudah
                // disepakati hari ini.
                'price_locked' => $invoice->amount,
                // Tanggalnya dihitung service, bukan di sini. Periode
                // menyambung dari periode sebelumnya dan mengikuti jangkar
                // tanggal tagih — tiga aturan yang harus jalan bersama, dan
                // tempatnya satu.
                ...$this->subscriptions->renewPeriod($subscription),
                // Puncak seat direset di awal periode baru — ia mengukur
                // pemakaian periode berjalan, bukan sepanjang masa.
                'seat_high_water' => $subscription->activeSeatsUsed(),
            ]);

            $invoice->tenant->update(['status' => Tenant::STATUS_ACTIVE]);
        });
    }

    /**
     * Lunasi seketika bila tagihannya tidak menagih apa pun.
     *
     * Tagihan Rp 0 yang menunggu bukti transfer meminta tenant membuktikan
     * bahwa ia sudah mentransfer nol rupiah, lalu meminta pemilik SaaS
     * memeriksa bukti itu. Kodenya bekerja persis seperti dirancang; yang
     * salah adalah menuntut pembuktian atas sesuatu yang tidak pernah
     * dibayarkan. Lihat `[BL-049]`.
     *
     * **Hanya tagihan upgrade**, dan batas itu disengaja. Tagihan langganan
     * bernilai nol tidak pernah lahir sendiri — `issueDuePeriodInvoices()`
     * menolak menerbitkannya selama tarifnya belum ditetapkan. Yang masih bisa
     * melahirkannya hanyalah pemilik SaaS yang mengetiknya sendiri di panel
     * (`min:0` di `Platform\InvoiceController::store()`), dan melunasinya di
     * sini akan menulis `price_locked = 0` lalu memperpanjang periodenya —
     * diam-diam mewariskan tarif nol yang belum pernah diputuskan siapa pun
     * (`[BL-041]`). Keputusan sebesar itu milik orang, bukan efek samping.
     *
     * **`provisional_blocked` sengaja tidak diperiksa.** Penjaga itu ada untuk
     * menahan seat yang naik tanpa dibayar; ketika tak ada yang harus dibayar,
     * ia tidak menjaga apa pun — dan menegakkannya justru mengunci tenant di
     * jalan buntu, karena bukti transfer nol rupiah tidak akan pernah ada yang
     * bisa mengunggahnya.
     *
     * @return bool apakah tagihannya benar-benar dilunasi di sini
     */
    public function settleIfFree(Invoice $invoice): bool
    {
        if (! $invoice->isUpgrade() || $invoice->isPaid() || (float) $invoice->amount > 0.0) {
            return false;
        }

        $this->settle($invoice, self::SOURCE_ZERO_AMOUNT);

        return true;
    }

    /**
     * Boleh tenant ini melunasi tagihannya sendiri lewat tombol peragaan?
     *
     * DUA syarat, dan keduanya wajib. Penanda `is_demo` saja tidak cukup: ia
     * ikut terbawa kalau basis data peragaan pernah disalin ke produksi, dan
     * satu salah setel akan membuka jalur yang melunasi tagihan tanpa bukti
     * apa pun — persis lubang yang `provisional_blocked` dibangun untuk
     * menutup. Syarat lingkungan membuat jalur itu tidak pernah ADA di
     * produksi, bukan sekadar sulit dicapai.
     */
    public function canSimulate(Tenant $tenant): bool
    {
        return $tenant->is_demo && ! app()->environment('production');
    }
}
