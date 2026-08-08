<?php

namespace App\Http\Controllers\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Services\Billing\Gateways\FakeGateway;
use App\Services\Billing\Gateways\PaymentGateway;
use App\Services\Billing\Gateways\PaymentGatewayManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Response;

/**
 * Alur bayar sisi tenant — `[BL-059]`(c).
 *
 * Tiga langkah, sengaja terpisah menjadi tiga halaman/rute: pilih kanal →
 * terbitkan instruksi → tunggu. Langkah ketiga berdiri sendiri karena ia satu-
 * satunya yang punya umur: tenant menutup laptopnya, membayar dari ponsel, lalu
 * kembali — dan alamat yang sama harus menunjukkan keadaan terbarunya.
 *
 * Yang TIDAK ada di kelas ini: melunasi tagihan. Bahkan tombol peragaan pun
 * tidak — ia menyusun notifikasi bertanda tangan dan menyerahkannya ke
 * `PaymentWebhookController`, jalur yang sama persis dengan penyedia sungguhan.
 * Tanpa aturan itu, yang diperagakan besok hanyalah membuktikan bahwa jalur
 * peragaan bekerja.
 */
class PaymentController extends Controller
{
    public function __construct(
        private readonly PaymentGateway $gateway,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * Pilih kanal pembayaran.
     */
    public function create(Request $request, Invoice $invoice): Response|RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        if ($invoice->isPaid()) {
            return redirect()->route('billing.show')->with('error', 'Tagihan ini sudah lunas.');
        }

        // Instruksi yang masih hidup selalu menang atas yang baru. Menerbitkan
        // nomor VA kedua untuk tagihan yang sama adalah cara termudah membuat
        // tenant mentransfer ke nomor yang sudah tidak ditunggu siapa-siapa.
        $open = $this->openAttemptFor($invoice);

        if ($open !== null) {
            return redirect()->route('billing.payment.show', $open);
        }

        return inertia('Billing/Pay', [
            'invoice' => $this->invoicePayload($invoice),
            'channels' => $this->gateway->availableChannels(),
            'gateway' => [
                'key' => $this->gateway->key(),
                'is_simulated' => $this->gateways->isSimulated($this->gateway->key()),
            ],
        ]);
    }

    /**
     * Terbitkan instruksi bayar lewat penyedia.
     */
    public function store(Request $request, Invoice $invoice): RedirectResponse
    {
        $this->authorizeInvoice($request, $invoice);

        $validated = $request->validate([
            'channel' => ['required', 'string', Rule::in(array_column($this->gateway->availableChannels(), 'code'))],
        ]);

        if ($invoice->isPaid()) {
            return redirect()->route('billing.show')->with('error', 'Tagihan ini sudah lunas.');
        }

        $open = $this->openAttemptFor($invoice);

        if ($open !== null) {
            return redirect()->route('billing.payment.show', $open);
        }

        $attempt = $this->gateway->createCharge($invoice, $validated['channel']);

        return redirect()->route('billing.payment.show', $attempt);
    }

    /**
     * Instruksi bayar berikut keadaannya.
     */
    public function show(Request $request, PaymentAttempt $attempt): Response
    {
        $this->authorizeAttempt($request, $attempt);

        $attempt->loadMissing('invoice');

        return inertia('Billing/PaymentInstruction', [
            'attempt' => [
                'id' => $attempt->id,
                'channel' => $attempt->channel,
                'channel_label' => $this->channelLabel($attempt->channel),
                'external_id' => $attempt->external_id,
                'amount' => (float) $attempt->amount,
                // Keadaan dihitung, bukan dibaca mentah: percobaan yang
                // tenggatnya lewat masih tertulis `pending` di basis data
                // sampai ada notifikasi yang menyentuhnya, dan menampilkan
                // "menunggu pembayaran" untuk nomor VA yang sudah mati adalah
                // berbohong kepada orang yang sedang memegang ponselnya.
                'status' => $attempt->hasExpired() && $attempt->isPending()
                    ? PaymentAttempt::STATUS_EXPIRED
                    : $attempt->status,
                'expires_at' => $attempt->expires_at?->toIso8601String(),
                'instructions' => $attempt->payload,
            ],
            'invoice' => $this->invoicePayload($attempt->invoice),
            'gateway' => [
                'key' => $attempt->gateway,
                // Panel peragaan hanya dirender ketika drivernya memang tiruan
                // DAN masih tiruan sekarang — tagihan lama yang dibayar lewat
                // gateway tiruan tidak boleh menghidupkan kembali tombolnya
                // setelah penyedia sungguhan dipasang.
                'is_simulated' => $simulated = $this->gateways->isSimulated($attempt->gateway)
                    && $attempt->gateway === $this->gateway->key(),
                // Detik sampai "pembayaran" datang sendiri. 0 = tidak pernah,
                // dan halamannya kembali menunggu tombol.
                'auto_settle_seconds' => $simulated && $this->gateway instanceof FakeGateway
                    ? $this->gateway->autoSettleSeconds()
                    : 0,
            ],
        ]);
    }

    /**
     * Kirim notifikasi seolah-olah datang dari penyedia.
     *
     * Dua pemakainya, dan keduanya lewat pintu ini: halaman instruksi yang
     * memanggilnya sendiri setelah `auto_settle_seconds` (itulah yang membuat
     * peragaan berakhir berhasil tanpa siapa pun menekan tombol bernama "Bayar
     * penuh"), dan panel alat pengembangan untuk keadaan yang tidak berakhir
     * berhasil — kurang bayar, gagal, kedaluwarsa.
     *
     * Notifikasinya ditandatangani sungguhan dan diserahkan ke pemroses webhook
     * yang sama, sehingga verifikasi tanda tangan, penjaga idempotensi,
     * pemeriksaan nominal, dan tenggat semuanya ikut dilewati. Yang tidak ikut
     * teruji lewat sini hanyalah routing dan CSRF — sisanya jalur produksi.
     *
     * Sengaja POST, dan pelunasan otomatis pun memakainya alih-alih menumpang
     * di `show()`: `show()` adalah GET yang dipanggil berulang oleh polling dan
     * bisa ikut terpanggil prefetch Inertia. GET yang melunasi tagihan berarti
     * sekadar mengarahkan kursor ke tautannya sudah cukup untuk membayar.
     */
    public function simulate(Request $request, PaymentAttempt $attempt, PaymentWebhookController $webhook): RedirectResponse
    {
        $this->authorizeAttempt($request, $attempt);

        $gateway = $this->gateway;

        // 404 di luar driver tiruan, mengikuti alasan yang sama seperti
        // `SimulatedPaymentController`: menjawab "terlarang" memberi tahu
        // penanyanya bahwa ada sesuatu di sini yang bisa dibuka.
        abort_unless($gateway instanceof FakeGateway && $attempt->gateway === $gateway->key(), 404);

        $validated = $request->validate([
            'outcome' => ['required', 'string', Rule::in(['paid', 'underpaid', 'failed', 'expired'])],
        ]);

        $webhook->handle($gateway->callbackRequest($attempt, $validated['outcome']), $attempt->gateway);

        return back()->with('success', match ($attempt->fresh()->status) {
            PaymentAttempt::STATUS_PAID => 'Pembayaran diterima. Akses langganan dipulihkan.',
            PaymentAttempt::STATUS_MISMATCH => 'Nominal yang masuk tidak sama dengan tagihan, jadi tagihannya belum dilunasi. Pemilik layanan akan menindaklanjuti.',
            PaymentAttempt::STATUS_EXPIRED => 'Instruksi pembayaran ini sudah kedaluwarsa. Terbitkan yang baru.',
            default => 'Pembayaran gagal. Silakan coba lagi atau pilih kanal lain.',
        });
    }

    /**
     * Percobaan yang masih menunggu pembayaran dan belum lewat tenggatnya.
     */
    private function openAttemptFor(Invoice $invoice): ?PaymentAttempt
    {
        return PaymentAttempt::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentAttempt::STATUS_PENDING)
            ->where('expires_at', '>', now())
            ->latest('id')
            ->first();
    }

    private function channelLabel(string $channel): string
    {
        $match = collect($this->gateway->availableChannels())->firstWhere('code', $channel);

        return $match['label'] ?? $channel;
    }

    /**
     * @return array<string, mixed>
     */
    private function invoicePayload(Invoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'period' => $invoice->period,
            'kind' => $invoice->kind,
            'amount' => (float) $invoice->amount,
            'status' => $invoice->status,
            'due_date' => $invoice->due_date?->toDateString(),
        ];
    }

    /**
     * Pemisahan tenant ditegakkan terang-terangan, bukan lewat scope global —
     * mengikuti `Invoice`, yang dibaca dari webhook tanpa konteks tenant.
     */
    private function authorizeInvoice(Request $request, Invoice $invoice): void
    {
        abort_if($invoice->tenant_id !== $request->user()->tenant_id, 403);
    }

    private function authorizeAttempt(Request $request, PaymentAttempt $attempt): void
    {
        abort_if($attempt->tenant_id !== $request->user()->tenant_id, 403);
    }
}
