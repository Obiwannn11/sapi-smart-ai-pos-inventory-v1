<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\PaymentAttempt;
use App\Models\PlatformAuditLog;
use App\Services\Billing\Gateways\InvalidCallbackSignature;
use App\Services\Billing\Gateways\PaymentGatewayManager;
use App\Services\Billing\InvoiceSettlement;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Satu-satunya tempat kabar dari penyedia pembayaran diproses — `[BL-059]`(e).
 *
 * Terbuka untuk publik dan dikecualikan dari CSRF, karena yang memanggilnya
 * adalah mesin di luar sana tanpa sesi dan tanpa token. Yang menggantikan
 * keduanya adalah tanda tangan: `verifyCallback()` dijalankan sebelum satu
 * medan pun dibaca, dan tanpa itu endpoint ini akan melunasi tagihan atas
 * perintah siapa saja yang menemukan alamatnya.
 *
 * **Tenant ditentukan dari `payment_attempts`, bukan dari sesi.** Tidak ada
 * pengguna yang masuk di sini dan `TenantScope` tidak aktif, jadi satu-satunya
 * cara menjawab "ini tagihan siapa" adalah nomor transaksi yang dikirim
 * penyedia.
 *
 * **Hampir semuanya dijawab 200.** Notifikasi berulang, transaksi tak dikenal,
 * pembayaran gagal — semuanya "sudah kami terima, jangan kirim lagi". Yang
 * dijawab 4xx hanya tanda tangan yang tidak cocok, karena hanya itu yang
 * memang tidak boleh dianggap pernah sampai. Menjawab galat untuk hal lain
 * membuat penyedia mengulang selamanya sesuatu yang tidak akan pernah berubah.
 */
class PaymentWebhookController extends Controller
{
    public function __construct(
        private readonly PaymentGatewayManager $gateways,
        private readonly InvoiceSettlement $settlement,
    ) {}

    public function handle(Request $request, string $gateway): JsonResponse
    {
        // 404, bukan 400: di produksi, alamat webhook driver tiruan sebaiknya
        // tidak terlihat pernah ada.
        abort_unless($this->gateways->has($gateway), 404);

        $driver = $this->gateways->driver($gateway);

        try {
            $result = $driver->verifyCallback($request);
        } catch (InvalidCallbackSignature) {
            return response()->json(['message' => 'invalid signature'], 403);
        }

        $attempt = PaymentAttempt::query()
            ->where('gateway', $gateway)
            ->where('external_id', $result->externalId)
            ->first();

        if ($attempt === null) {
            // Nomor transaksi yang tidak kami kenal. 200 karena mengulanginya
            // tidak akan membuatnya jadi dikenal.
            return response()->json(['message' => 'ignored'], 200);
        }

        return DB::transaction(function () use ($attempt, $result, $driver) {
            // Dikunci lalu dibaca ULANG di dalam transaksi: dua notifikasi yang
            // tiba bersamaan adalah hal biasa, dan keduanya akan melihat status
            // `pending` bila yang dibaca adalah salinan dari sebelum kunci.
            $locked = PaymentAttempt::query()->whereKey($attempt->id)->lockForUpdate()->first();

            if (! $locked->isPending()) {
                return response()->json(['message' => 'already processed'], 200);
            }

            $locked->payload = [...($locked->payload ?? []), 'callback' => $result->payload];

            if ($result->outcome !== PaymentAttempt::STATUS_PAID) {
                $locked->status = $result->outcome;
                $locked->save();

                return response()->json(['message' => 'recorded'], 200);
            }

            // Tenggat sudah lewat. Tidak dilunasi, dan sengaja meninggalkan
            // jejak sensitif: uangnya mungkin benar-benar masuk, dan yang
            // memutuskan nasib pembayaran terlambat adalah orang — jalur
            // verifikasi manual pemilik SaaS tetap terbuka untuk itu.
            if ($locked->hasExpired()) {
                $locked->status = PaymentAttempt::STATUS_EXPIRED;
                $locked->save();

                $this->log('payments.late', $locked, $result->amount);

                return response()->json(['message' => 'expired'], 200);
            }

            $invoice = $locked->invoice;

            // Selisih berapa pun menghentikan pelunasan. `settle()` mengunci
            // `price_locked` dari nominal yang DITAGIH, jadi melunasi kurang
            // bayar berarti diam-diam menetapkan tarif yang tak pernah
            // disepakati — dan menutup selisihnya dari uang sendiri.
            if (abs($result->amount - (float) $invoice->amount) >= 0.01) {
                $locked->status = PaymentAttempt::STATUS_MISMATCH;
                $locked->save();

                $this->log('payments.mismatch', $locked, $result->amount);

                return response()->json(['message' => 'amount mismatch'], 200);
            }

            // Tagihan yang sudah lunas lewat jalur lain (bukti transfer yang
            // keburu diperiksa, misalnya). Percobaannya tetap ditandai lunas —
            // uangnya memang masuk — tapi tidak ada yang perlu dilunasi lagi.
            if (! $invoice->isPaid()) {
                $this->settlement->settle($invoice, $driver->settlementSource());
            }

            $locked->status = PaymentAttempt::STATUS_PAID;
            $locked->paid_at = now();
            $locked->save();

            $this->log('payments.settled', $locked, $result->amount);

            return response()->json(['message' => 'settled'], 200);
        });
    }

    /**
     * Jejak sensitif untuk tiap kejadian uang.
     *
     * `platform_user_id` akan null — pelakunya mesin, bukan akun platform — dan
     * `meta` yang menyebut tenant, tagihan, serta nomor transaksinya menutup
     * celah itu.
     */
    private function log(string $action, PaymentAttempt $attempt, float $receivedAmount): void
    {
        PlatformAuditLog::record($action, $attempt, [
            'tenant_id' => $attempt->tenant_id,
            'invoice_id' => $attempt->invoice_id,
            'gateway' => $attempt->gateway,
            'channel' => $attempt->channel,
            'external_id' => $attempt->external_id,
            'billed_amount' => (float) $attempt->amount,
            'received_amount' => $receivedAmount,
        ]);
    }
}
