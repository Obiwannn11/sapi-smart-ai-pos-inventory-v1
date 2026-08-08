<?php

namespace App\Services\Billing\Gateways;

use App\Models\Invoice;
use App\Models\PaymentAttempt;
use App\Services\Billing\InvoiceSettlement;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Payment gateway tiruan — untuk development dan peragaan, bukan untuk uang.
 *
 * Ia menirukan penyedia sungguhan sampai ke bentuk kabelnya: menerbitkan nomor
 * transaksi, mengembalikan instruksi per kanal, memberi tenggat, lalu MEMANGGIL
 * BALIK lewat webhook yang bertanda tangan. Tombol peragaan di halaman
 * pembayaran tidak melunasi apa pun sendiri — ia menyusun notifikasi dan
 * mengirimkannya ke pemroses webhook yang sama.
 *
 * Itulah satu-satunya alasan kelas ini ditulis begini alih-alih memanggil
 * `InvoiceSettlement::settle()` dua baris: yang diperagakan besok harus jalur
 * yang sama dengan yang akan dipakai produksi. Gateway palsu yang melunasi
 * dengan caranya sendiri hanya membuktikan bahwa jalur palsunya bekerja.
 *
 * **Tidak pernah hidup di produksi.** Gerbangnya ada di `PaymentGatewayManager`
 * dan berupa exception saat resolve, bukan diam-diam jatuh ke jalur manual —
 * jalur yang bisa melunasi tagihan tanpa uang tidak boleh terbuka karena satu
 * variabel `.env` salah setel.
 */
class FakeGateway implements PaymentGateway
{
    /**
     * Kanal yang ditawarkan. Bentuknya sengaja menyerupai daftar kanal penyedia
     * sungguhan (kode mesin + label manusia + petunjuk singkat) supaya halaman
     * pemilih kanal tidak perlu berubah ketika daftarnya datang dari Sumopod.
     */
    private const CHANNELS = [
        ['code' => 'qris', 'label' => 'QRIS', 'hint' => 'Pindai dengan aplikasi bank atau e-wallet apa pun'],
        ['code' => 'va_bca', 'label' => 'Virtual Account BCA', 'hint' => 'Transfer ke nomor VA lewat m-BCA atau ATM'],
        ['code' => 'va_bni', 'label' => 'Virtual Account BNI', 'hint' => 'Transfer ke nomor VA lewat BNI Mobile atau ATM'],
        ['code' => 'va_mandiri', 'label' => 'Virtual Account Mandiri', 'hint' => 'Transfer ke nomor VA lewat Livin atau ATM'],
        ['code' => 'ewallet_dana', 'label' => 'DANA', 'hint' => 'Bayar dari saldo DANA'],
    ];

    public function key(): string
    {
        return 'fake';
    }

    public function settlementSource(): string
    {
        return InvoiceSettlement::SOURCE_GATEWAY_FAKE;
    }

    public function availableChannels(): array
    {
        return self::CHANNELS;
    }

    public function createCharge(Invoice $invoice, string $channel): PaymentAttempt
    {
        $externalId = 'FAKE-'.now()->format('ymd').'-'.Str::upper(Str::random(8));

        return PaymentAttempt::create([
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'gateway' => $this->key(),
            'channel' => $channel,
            'external_id' => $externalId,
            'amount' => $invoice->amount,
            'status' => PaymentAttempt::STATUS_PENDING,
            'expires_at' => now()->addMinutes((int) config('subscription.payment.attempt_ttl_minutes')),
            'payload' => $this->instructionsFor($channel, $invoice, $externalId),
        ]);
    }

    public function verifyCallback(Request $request): CallbackResult
    {
        // Ditandatangani atas BADAN MENTAHNYA, bukan atas hasil parse. Menghitung
        // tanda tangan dari array yang sudah di-decode berarti menandatangani
        // tafsiran kita atas pesan itu, bukan pesannya — dan dua tafsiran yang
        // berbeda atas byte yang sama adalah celah, bukan detail.
        $body = $request->getContent();
        $signature = (string) $request->header(self::signatureHeader());

        if ($signature === '' || ! hash_equals($this->sign($body), $signature)) {
            throw new InvalidCallbackSignature('Tanda tangan notifikasi tidak cocok.');
        }

        /** @var array<string, mixed> $data */
        $data = json_decode($body, true) ?: [];

        return new CallbackResult(
            externalId: (string) ($data['external_id'] ?? ''),
            // Keadaan yang tidak dikenal diperlakukan sebagai gagal, bukan
            // diabaikan: mengabaikannya meninggalkan percobaan menggantung di
            // `pending` selamanya tanpa seorang pun tahu kenapa.
            outcome: match ($data['status'] ?? null) {
                PaymentAttempt::STATUS_PAID => PaymentAttempt::STATUS_PAID,
                PaymentAttempt::STATUS_EXPIRED => PaymentAttempt::STATUS_EXPIRED,
                default => PaymentAttempt::STATUS_FAILED,
            },
            amount: (float) ($data['amount'] ?? 0),
            payload: $data,
        );
    }

    /**
     * Tanda tangan sebuah badan notifikasi.
     *
     * Publik karena panel peragaan memakainya untuk menyusun notifikasi yang
     * BENAR-BENAR sah — kalau ia boleh mengirim tanpa tanda tangan, verifikasi
     * di atas tidak pernah ikut diperagakan, dan justru itu bagian yang paling
     * perlu terbukti bekerja sebelum penyedia sungguhan dipasang.
     */
    public function sign(string $body): string
    {
        return hash_hmac('sha256', $body, (string) config('subscription.payment.fake.secret'));
    }

    public static function signatureHeader(): string
    {
        return 'X-Payment-Signature';
    }

    /**
     * Berapa detik sesudah instruksi terbit sebelum "pembayaran" datang sendiri.
     *
     * Ada karena peragaan di depan calon klien tidak boleh menuntut siapa pun
     * menekan tombol bernama "Bayar penuh" — yang diperagakan adalah orang
     * membayar dari aplikasi banknya, dan di layar ini yang seharusnya terlihat
     * cuma penantian yang berakhir berhasil.
     *
     * `0` mematikannya, dan panel alat pengembangan jadi satu-satunya cara
     * menggerakkan keadaan — berguna saat yang sedang diuji adalah kurang bayar
     * atau kedaluwarsa, bukan alur normalnya.
     */
    public function autoSettleSeconds(): int
    {
        return max(0, (int) config('subscription.payment.fake.auto_settle_seconds'));
    }

    /**
     * Susun notifikasi bertanda tangan, persis seperti penyedia mengirimnya.
     *
     * Tinggal di sini, bukan di controller, karena bentuk kabelnya milik driver:
     * begitu ada penyedia kedua, controller yang menyusun sendiri badan JSON-nya
     * akan menyusun badan yang salah.
     */
    public function callbackRequest(PaymentAttempt $attempt, string $outcome): Request
    {
        $body = json_encode([
            'external_id' => $attempt->external_id,
            'status' => $outcome === 'underpaid' ? PaymentAttempt::STATUS_PAID : $outcome,
            // "Kurang bayar" memang perlu bisa diperagakan: itu satu-satunya
            // cara menunjukkan bahwa nominal yang tidak cocok TIDAK melunasi
            // apa pun, dan pemilik SaaS-lah yang memutuskan sisanya.
            'amount' => $outcome === 'underpaid'
                ? round((float) $attempt->amount / 2, 2)
                : (float) $attempt->amount,
            'occurred_at' => now()->toIso8601String(),
        ], JSON_THROW_ON_ERROR);

        return Request::create(
            uri: route('payment.webhook', ['gateway' => $this->key()]),
            method: 'POST',
            server: [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_'.str_replace('-', '_', strtoupper(self::signatureHeader())) => $this->sign($body),
            ],
            content: $body,
        );
    }

    /**
     * Instruksi bayar per kanal — nomor VA, isi QR, atau tautan e-wallet.
     *
     * Angkanya diturunkan dari id tagihan supaya tetap sama bila halamannya
     * dibuka ulang; nomor VA yang berubah tiap refresh adalah hal pertama yang
     * membuat sebuah peragaan terlihat palsu.
     *
     * @return array<string, mixed>
     */
    private function instructionsFor(string $channel, Invoice $invoice, string $externalId): array
    {
        $virtualAccount = '8808'.str_pad((string) $invoice->id, 10, '0', STR_PAD_LEFT);

        return match (true) {
            $channel === 'qris' => [
                'type' => 'qris',
                // Bukan QRIS sungguhan dan tidak berpura-pura menjadi satu:
                // isinya menyebutkan dirinya peragaan supaya tidak ada yang
                // mencoba memindainya dengan aplikasi bank.
                'qr_payload' => 'SAPI-DEMO|'.$externalId.'|'.number_format((float) $invoice->amount, 0, '', ''),
            ],
            str_starts_with($channel, 'va_') => [
                'type' => 'virtual_account',
                'bank' => Str::upper(Str::after($channel, 'va_')),
                'virtual_account' => $virtualAccount,
            ],
            default => [
                'type' => 'ewallet',
                'reference' => $externalId,
            ],
        };
    }
}
