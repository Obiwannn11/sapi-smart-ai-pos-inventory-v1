<?php

namespace App\Services\Billing\Gateways;

/**
 * Notifikasi penyedia setelah bentuknya diseragamkan.
 *
 * Tiap penyedia menamai medannya sendiri-sendiri; yang dibutuhkan pemroses
 * webhook selalu empat hal yang sama. Menerjemahkannya di driver membuat
 * `PaymentWebhookController` tidak perlu tahu penyedia mana yang sedang bicara.
 */
final readonly class CallbackResult
{
    /**
     * @param  string  $externalId  nomor transaksi di sisi penyedia
     * @param  string  $outcome  salah satu `PaymentAttempt::STATUS_PAID|FAILED|EXPIRED`
     * @param  float  $amount  nominal yang BENAR-BENAR diterima, bukan yang ditagih
     * @param  array<string, mixed>  $payload  notifikasi mentah, apa adanya
     */
    public function __construct(
        public string $externalId,
        public string $outcome,
        public float $amount,
        public array $payload = [],
    ) {}
}
