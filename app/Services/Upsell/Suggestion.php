<?php

namespace App\Services\Upsell;

use App\Models\UpsellEvent;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Satu saran upsell, siap dikirim ke POS atau ke consumer self-order.
 *
 * Skornya dihitung di server dan ikut di sini supaya client cukup menyortir —
 * ia tidak perlu tahu apa pun soal kedaluwarsa, ko-okurensi, atau selisih harga.
 *
 * @implements Arrayable<string, mixed>
 */
final class Suggestion implements Arrayable
{
    public function __construct(
        public readonly string $type,
        public readonly string $reason,
        public readonly string $label,
        public readonly string $note,
        public readonly float $extraAmount,
        public readonly float $score,
        public readonly ?int $triggerVariantId = null,
        public readonly ?int $suggestedVariantId = null,
        public readonly ?int $suggestedModifierId = null,
        public readonly ?string $suggestedVariantName = null,
        public readonly ?float $suggestedVariantPrice = null,
    ) {}

    /**
     * Identitas stabil satu saran — dipakai client untuk mengingat mana yang
     * sudah ditutup kasir, dan untuk mencocokkan event saat checkout.
     */
    public function key(): string
    {
        return implode(':', [
            $this->type,
            $this->triggerVariantId ?? 'cart',
            $this->suggestedModifierId !== null ? 'm'.$this->suggestedModifierId : 'v'.$this->suggestedVariantId,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'key' => $this->key(),
            'type' => $this->type,
            'reason' => $this->reason,
            'label' => $this->label,
            'note' => $this->note,
            'extra_amount' => round($this->extraAmount, 2),
            'score' => round($this->score, 2),
            'trigger_variant_id' => $this->triggerVariantId,
            'suggested_variant_id' => $this->suggestedVariantId,
            'suggested_modifier_id' => $this->suggestedModifierId,
            'suggested_variant_name' => $this->suggestedVariantName,
            'suggested_variant_price' => $this->suggestedVariantPrice !== null
                ? round($this->suggestedVariantPrice, 2)
                : null,
        ];
    }

    /**
     * Apakah saran ini menukar varian di keranjang (naik ukuran), bukan
     * menambah baris baru? Client memakainya untuk memilih aksi.
     */
    public function replacesTrigger(): bool
    {
        return $this->type === UpsellEvent::TYPE_UPSIZE;
    }
}
